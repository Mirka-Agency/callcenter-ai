<?php

namespace App\Application\Call\Services;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\StoreSampleRecordingJob;
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
use App\Support\SampleConversationAnalysisCache;
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

        $callId = DB::transaction(function () use (
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

            $storagePath = $this->storeFile($file, $callId, $validated['extension'], $validated['mime_type']);

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

            return $callId;
        });

        $this->dispatchAnalysis($callId);

        return $callId;
    }

    public function uploadFromSample(
        int $organizationId,
        int $uploaderUserId,
        UploaderType $uploaderType,
        ?int $organizationUserId,
        string $sampleId,
        string $absolutePath,
        string $displayFilename,
        ManualUploadMetadata $metadata,
    ): int {
        $usesCachedAnalysis = SampleConversationAnalysisCache::has($sampleId);

        if (! $usesCachedAnalysis) {
            app(AiBillingService::class)->assertCanAnalyze($organizationId);
        }

        $validated = $this->validator->validatePath($absolutePath, $displayFilename);
        $fileSize = filesize($absolutePath) ?: 0;

        if ($usesCachedAnalysis) {
            return $this->uploadFromSampleWithCachedAnalysis(
                $organizationId, $uploaderUserId, $uploaderType, $organizationUserId,
                $sampleId, $absolutePath, $displayFilename, $metadata, $validated, $fileSize,
            );
        }

        $callId = DB::transaction(function () use (
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

            return $callId;
        });

        $this->dispatchAnalysis($callId);

        return $callId;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function uploadFromSampleWithCachedAnalysis(
        int $organizationId,
        int $uploaderUserId,
        UploaderType $uploaderType,
        ?int $organizationUserId,
        string $sampleId,
        string $absolutePath,
        string $displayFilename,
        ManualUploadMetadata $metadata,
        array $validated,
        int $fileSize,
    ): int {
        $recordingId = 0;
        $storagePath = '';

        $callId = DB::transaction(function () use (
            $organizationId,
            $uploaderUserId,
            $uploaderType,
            $organizationUserId,
            $displayFilename,
            $metadata,
            $validated,
            $fileSize,
            &$recordingId,
            &$storagePath,
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

            $storagePath = $this->buildStoragePath($callId, $validated['extension']);

            $recordingId = $this->recordings->create(new RecordingData(
                callId: $callId,
                storageDisk: $this->recordingStorage->disk(),
                storagePath: $storagePath,
                mimeType: $validated['mime_type'],
                fileSizeBytes: $fileSize,
                durationSeconds: $validated['duration_seconds'],
                status: 'uploading',
            ));

            $this->tracker->markUploaded($processingJob);

            return $callId;
        });

        app(SampleConversationAnalysisService::class)->apply($callId, $sampleId);

        StoreSampleRecordingJob::dispatch($callId, $recordingId, $absolutePath, $storagePath, $validated['mime_type']);

        return $callId;
    }

    private function dispatchAnalysis(int $callId): void
    {
        AnalyzeAudioJob::dispatchChain($callId);
    }

    private function storeFile(UploadedFile $file, int $callId, string $extension, string $mimeType): string
    {
        $path = $this->buildStoragePath($callId, $extension);
        $sourcePath = $file->getRealPath();

        if ($sourcePath === false || ! is_readable($sourcePath)) {
            throw new \RuntimeException('Upload file is not readable.');
        }

        $this->recordingStorage->putFromLocalPath($sourcePath, $path, $mimeType);

        return $path;
    }

    private function storeFileFromPath(string $sourcePath, int $callId, string $extension, string $mimeType): string
    {
        $path = $this->buildStoragePath($callId, $extension);

        $this->recordingStorage->putFromLocalPath($sourcePath, $path, $mimeType);

        return $path;
    }

    private function buildStoragePath(int $callId, string $extension): string
    {
        return sprintf(
            'recordings/%d/%s.%s',
            $callId,
            now()->format('YmdHis').'-'.Str::lower(Str::random(8)),
            $extension,
        );
    }
}
