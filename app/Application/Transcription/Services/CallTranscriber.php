<?php

namespace App\Application\Transcription\Services;

use App\Application\Transcription\Jobs\CleanupSonioxTranscription;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Domain\Transcription\Contracts\TranscriptionClient;
use App\Domain\Transcription\DTOs\TranscriptionContextData;
use App\Domain\Transcription\Exceptions\TranscriptionPending;
use App\Domain\Transcription\Exceptions\TranscriptionRejected;
use App\Models\Call;
use App\Models\CallTranscript;
use App\Services\RecordingStorage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CallTranscriber
{
    public function __construct(
        private TranscriptionClient $client,
        private RecordingStorage $storage,
        private SonioxTranscriptNormalizer $normalizer,
    ) {}

    public function transcribe(Call $call): CallTranscript
    {
        $lock = Cache::lock('call-transcription-'.$call->id, 600);
        if (! $lock->get()) {
            throw new TranscriptionPending('Transcription is already being processed.');
        }

        try {
            return $this->resume($call);
        } finally {
            $lock->release();
        }
    }

    private function resume(Call $call): CallTranscript
    {
        $transcript = CallTranscript::query()->where('call_id', $call->id)->latest('id')->first();
        if ($transcript?->status === CallTranscript::STATUS_COMPLETED && trim((string) $transcript->content) !== '') {
            return $transcript;
        }

        $this->client->validateConfiguration();
        if ($transcript?->transcription_provider !== 'soniox') {
            $transcript = null;
        }
        if (! $transcript) {
            $transcript = CallTranscript::query()->create([
                'call_id' => $call->id,
                'organization_id' => $call->organization_id,
                'voip_call_log_id' => $call->voip_call_log_id,
                'organization_user_id' => $call->organization_user_id,
                'transcription_provider' => 'soniox',
                'model_name' => config('soniox.model'),
                'language' => 'fa',
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        if (in_array($transcript->status, ['uploading', 'submitting'], true)) {
            throw new \RuntimeException('Soniox acceptance is unknown. Inspect remote usage before resetting this transcription.');
        }
        if ($transcript->status === CallTranscript::STATUS_FAILED) {
            throw new \RuntimeException($transcript->error_message ?? 'Soniox transcription failed.');
        }
        if ($transcript->submitted_at?->addSeconds((int) config('soniox.deadline_seconds'))->isPast()) {
            $this->fail($transcript, 'Soniox transcription exceeded its processing deadline.');
        }

        $this->uploadRecording($call, $transcript);
        $this->submitTranscription($call, $transcript);

        $status = $this->client->status($transcript->remote_transcription_id);
        if (in_array($status['status'] ?? null, ['failed', 'error'], true)) {
            $this->fail($transcript, 'Soniox transcription failed: '.($status['error_type'] ?? 'processing_error'));
        }
        if (in_array($status['status'] ?? null, ['queued', 'downloading', 'transcribing', 'processing'], true)) {
            throw new TranscriptionPending('Soniox transcription is processing.');
        }
        if (($status['status'] ?? null) !== 'completed') {
            $this->fail($transcript, 'Soniox returned an unrecognized transcription status.');
        }

        $segments = $this->normalizer->segments($this->client->tokens($transcript->remote_transcription_id));
        if ($segments === []) {
            $this->fail($transcript, 'Soniox returned no speech for this recording.');
        }
        $transcript->update([
            'content' => $this->normalizer->render($segments),
            'segments_json' => $segments,
            'status' => CallTranscript::STATUS_COMPLETED,
            'processed_at' => now(),
            'error_message' => null,
            'duration_seconds' => $call->duration_seconds ?? $call->recording?->duration_seconds,
            'processing_duration_ms' => (int) $transcript->submitted_at->diffInMilliseconds(now()),
        ]);
        $this->scheduleCleanup($transcript);

        return $transcript;
    }

    private function uploadRecording(Call $call, CallTranscript $transcript): void
    {
        if ($transcript->remote_file_id) {
            return;
        }

        $recording = $call->recording;
        if (! $recording?->storage_path) {
            throw new \RuntimeException('A stored recording is required for Soniox transcription.');
        }
        $disk = $this->storage->assertExists($recording->storage_path, $recording->storage_disk);
        $audio = Storage::disk($disk)->readStream($recording->storage_path);
        if (! is_resource($audio)) {
            throw new \RuntimeException('Unable to open the stored recording for transcription.');
        }
        $transcript->update(['status' => 'uploading']);
        try {
            $fileId = $this->client->upload($audio, basename($recording->storage_path));
            $transcript->update(['remote_file_id' => $fileId, 'status' => 'pending']);
        } catch (LlmTransientException $e) {
            // An HTTP rejection is known; a connection loss is ambiguous.
            $transcript->update(['status' => 'pending']);
            throw $e;
        } catch (TranscriptionRejected $e) {
            $this->fail($transcript, $e->getMessage());
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Soniox upload acceptance is unknown. Inspect remote files before retrying.', previous: $e);
        } finally {
            fclose($audio);
        }
    }

    private function submitTranscription(Call $call, CallTranscript $transcript): void
    {
        if ($transcript->remote_transcription_id) {
            return;
        }

        $transcript->update(['status' => 'submitting']);
        try {
            $id = $this->client->submit(
                $transcript->remote_file_id,
                $transcript->model_name,
                'call-'.$call->id,
                new TranscriptionContextData(
                    organizationName: $call->organization?->title,
                    organizationBusinessContext: $call->organization?->business_context,
                ),
            );
            $transcript->update(['remote_transcription_id' => $id, 'status' => 'processing']);
            Log::info('Soniox transcription submitted', ['call_id' => $call->id, 'transcription_id' => $id]);
        } catch (LlmTransientException $e) {
            $transcript->update(['status' => 'pending']);
            throw $e;
        } catch (TranscriptionRejected $e) {
            $this->fail($transcript, $e->getMessage());
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Soniox submission acceptance is unknown. Inspect remote usage before retrying.', previous: $e);
        }
    }

    private function scheduleCleanup(CallTranscript $transcript): void
    {
        try {
            CleanupSonioxTranscription::dispatch($transcript->id);
        } catch (\Throwable $e) {
            // The saved transcript remains usable even if the queue is unavailable.
            Log::error('Unable to enqueue Soniox cleanup; retry cleanup for this transcript.', [
                'transcript_id' => $transcript->id,
                'exception_type' => $e::class,
            ]);
        }
    }

    private function fail(CallTranscript $transcript, string $message): never
    {
        $transcript->update(['status' => CallTranscript::STATUS_FAILED, 'error_message' => $message]);
        $this->scheduleCleanup($transcript);
        throw new \RuntimeException($message);
    }
}
