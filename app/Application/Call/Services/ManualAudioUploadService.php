<?php

namespace App\Application\Call\Services;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\Contracts\CallRepositoryInterface;
use App\Domain\Call\DTOs\ManualUploadMetadata;
use App\Domain\Call\DTOs\UnifiedCallData;
use App\Domain\Call\Enums\UploaderType;
use App\Domain\Recording\Contracts\RecordingRepositoryInterface;
use App\Domain\Recording\DTOs\RecordingData;
use App\Models\Call;
use App\Services\AiBillingService;
use App\Services\AudioUploadValidationService;
use App\Services\CallProcessingTracker;
use App\Services\RecordingStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ManualAudioUploadService
{
    public function __construct(
        private CallRepositoryInterface $calls,
        private RecordingRepositoryInterface $recordings,
        private AudioUploadValidationService $validator,
        private CallProcessingTracker $tracker,
        private RecordingStorage $recordingStorage,
    ) {}

    public function upload(
        int $organizationId,
        int $uploaderUserId,
        UploaderType $uploaderType,
        ?int $organizationUserId,
        UploadedFile $file,
        ManualUploadMetadata $metadata,
    ): int {
        app(AiBillingService::class)->assertCanAnalyze($organizationId);

        $validated = $this->validator->validate($file);

        return DB::transaction(function () use (
            $organizationId,
            $uploaderUserId,
            $uploaderType,
            $organizationUserId,
            $file,
            $metadata,
            $validated,
        ) {
            $callId = $this->calls->upsert(UnifiedCallData::forManualUpload(
                organizationId: $organizationId,
                organizationUserId: $organizationUserId,
                uploaderId: $uploaderUserId,
                uploaderType: $uploaderType,
                metadata: $metadata,
                durationSeconds: $validated['duration_seconds'],
            ));

            $call = Call::query()->findOrFail($callId);
            $processingJob = $this->tracker->startUpload($call, $file->getClientOriginalName(), $uploaderUserId);

            Log::info('Queue job created', [
                'call_id' => $callId,
                'job_uuid' => $processingJob->job_uuid,
                'file_name' => $processingJob->file_name,
            ]);

            $storagePath = $this->storeFile($file, $callId, $validated['extension']);

            $this->recordings->create(new RecordingData(
                callId: $callId,
                storageDisk: $this->recordingStorage->disk(),
                storagePath: $storagePath,
                mimeType: $validated['mime_type'],
                fileSizeBytes: $file->getSize(),
                durationSeconds: $validated['duration_seconds'],
                status: 'completed',
            ));

            $this->tracker->markUploaded($processingJob);

            AnalyzeAudioJob::dispatchChain($callId);

            return $callId;
        });
    }

    public function uploadFromSample(
        int $organizationId,
        int $uploaderUserId,
        UploaderType $uploaderType,
        ?int $organizationUserId,
        string $absolutePath,
        string $displayFilename,
        ManualUploadMetadata $metadata,
    ): int {
        app(AiBillingService::class)->assertCanAnalyze($organizationId);

        $validated = $this->validator->validatePath($absolutePath, $displayFilename);
        $fileSize = filesize($absolutePath) ?: 0;

        return DB::transaction(function () use (
            $organizationId,
            $uploaderUserId,
            $uploaderType,
            $organizationUserId,
            $absolutePath,
            $displayFilename,
            $metadata,
            $validated,
            $fileSize,
        ) {
            $callId = $this->calls->upsert(UnifiedCallData::forManualUpload(
                organizationId: $organizationId,
                organizationUserId: $organizationUserId,
                uploaderId: $uploaderUserId,
                uploaderType: $uploaderType,
                metadata: $metadata,
                durationSeconds: $validated['duration_seconds'],
            ));

            $call = Call::query()->findOrFail($callId);
            $processingJob = $this->tracker->startUpload($call, $displayFilename, $uploaderUserId);

            Log::info('Queue job created from sample conversation', [
                'call_id' => $callId,
                'job_uuid' => $processingJob->job_uuid,
                'file_name' => $processingJob->file_name,
            ]);

            $storagePath = $this->storeFileFromPath(
                $absolutePath,
                $callId,
                $validated['extension'],
                $validated['mime_type'],
            );

            $this->recordings->create(new RecordingData(
                callId: $callId,
                storageDisk: $this->recordingStorage->disk(),
                storagePath: $storagePath,
                mimeType: $validated['mime_type'],
                fileSizeBytes: $fileSize,
                durationSeconds: $validated['duration_seconds'],
                status: 'completed',
            ));

            $this->tracker->markUploaded($processingJob);

            AnalyzeAudioJob::dispatchChain($callId);

            return $callId;
        });
    }

    private function storeFile(UploadedFile $file, int $callId, string $extension): string
    {
        return $this->storeFileFromPath(
            $file->getRealPath(),
            $callId,
            $extension,
            $file->getMimeType() ?: 'audio/mpeg',
        );
    }

    private function storeFileFromPath(string $sourcePath, int $callId, string $extension, string $mimeType): string
    {
        $path = sprintf(
            'recordings/%d/%s.%s',
            $callId,
            now()->format('YmdHis').'-'.Str::lower(Str::random(8)),
            $extension,
        );

        $this->recordingStorage->put(
            $path,
            file_get_contents($sourcePath),
            $mimeType,
        );

        return $path;
    }
}
