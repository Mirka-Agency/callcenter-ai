<?php

namespace App\Services;

use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Processing\Enums\ProcessingLogLevel;
use App\Events\CallProcessingUpdated;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\CallProcessingLog;
use Illuminate\Support\Str;

class CallProcessingTracker
{
    public function startUpload(Call $call, string $fileName, ?int $uploaderId = null): CallProcessingJob
    {
        $job = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $call->organization_id,
            'organization_user_id' => $call->organization_user_id,
            'uploader_id' => $uploaderId ?? $call->uploader_id,
            'file_name' => $fileName,
            'status' => ProcessingJobStatus::Uploading,
            'stage' => ProcessingJobStage::Uploaded,
            'progress_percentage' => ProcessingJobStage::Uploaded->progress(),
            'upload_started_at' => now(),
        ]);

        $this->log($job, ProcessingLogLevel::Info, 'upload', 'آپلود آغاز شد');

        $this->broadcast($job);

        return $job;
    }

    public function markUploaded(CallProcessingJob $job): CallProcessingJob
    {
        return $this->transition(
            job: $job,
            status: ProcessingJobStatus::Queued,
            stage: ProcessingJobStage::Queued,
            attributes: [
                'upload_completed_at' => now(),
                'queued_at' => now(),
            ],
            logLevel: ProcessingLogLevel::Info,
            logSource: 'storage',
            logMessage: 'فایل با موفقیت ذخیره شد و به صف پردازش اضافه شد',
        );
    }

    public function markProcessingStarted(CallProcessingJob $job): CallProcessingJob
    {
        $waitingSeconds = $job->queued_at
            ? (int) $job->queued_at->diffInSeconds(now())
            : null;

        return $this->transition(
            job: $job,
            status: ProcessingJobStatus::Processing,
            stage: ProcessingJobStage::Queued,
            attributes: [
                'processing_started_at' => now(),
                'waiting_seconds' => $waitingSeconds,
            ],
            logLevel: ProcessingLogLevel::Info,
            logSource: 'queue',
            logMessage: 'کار تحلیل هوش مصنوعی آغاز شد',
        );
    }

    public function markSendingToAi(CallProcessingJob $job): CallProcessingJob
    {
        return $this->transition(
            job: $job,
            status: ProcessingJobStatus::Processing,
            stage: ProcessingJobStage::SendingToAi,
            logLevel: ProcessingLogLevel::Info,
            logSource: 'analysis',
            logMessage: 'ارسال صوت به مدل هوش مصنوعی',
        );
    }

    public function markWaitingForAi(CallProcessingJob $job): CallProcessingJob
    {
        return $this->transition(
            job: $job,
            status: ProcessingJobStatus::Processing,
            stage: ProcessingJobStage::WaitingForAi,
            logLevel: ProcessingLogLevel::Info,
            logSource: 'analysis',
            logMessage: 'در انتظار پاسخ هوش مصنوعی',
        );
    }

    public function markProcessingResult(CallProcessingJob $job): CallProcessingJob
    {
        return $this->transition(
            job: $job,
            status: ProcessingJobStatus::Processing,
            stage: ProcessingJobStage::ProcessingResult,
            logLevel: ProcessingLogLevel::Info,
            logSource: 'analysis',
            logMessage: 'پردازش نتیجه تحلیل هوش مصنوعی',
        );
    }

    public function markCompleted(CallProcessingJob $job): CallProcessingJob
    {
        $duration = $job->processing_started_at
            ? (int) $job->processing_started_at->diffInSeconds(now())
            : null;

        $job = $this->transition(
            job: $job,
            status: ProcessingJobStatus::Completed,
            stage: ProcessingJobStage::Completed,
            attributes: [
                'completed_at' => now(),
                'processing_duration_seconds' => $duration,
                'error_message' => null,
            ],
            logLevel: ProcessingLogLevel::Info,
            logSource: 'analysis',
            logMessage: 'تحلیل هوش مصنوعی با موفقیت تکمیل شد',
        );

        $this->broadcast($job, notification: [
            'type' => 'success',
            'message' => __('ui.processing.success_toast'),
            'url' => null,
            'call_id' => $job->call_id,
            'job_uuid' => $job->job_uuid,
        ]);

        return $job;
    }

    public function markCancelled(CallProcessingJob $job, string $reason): CallProcessingJob
    {
        $job = $this->transition(
            job: $job,
            status: ProcessingJobStatus::Cancelled,
            stage: $job->stage,
            attributes: [
                'completed_at' => now(),
                'error_message' => $reason,
            ],
            logLevel: ProcessingLogLevel::Info,
            logSource: 'queue',
            logMessage: $reason,
        );

        $this->broadcast($job);

        return $job;
    }

    public function markFailed(CallProcessingJob $job, string $error): CallProcessingJob
    {
        $duration = $job->processing_started_at
            ? (int) $job->processing_started_at->diffInSeconds(now())
            : null;

        $job = $this->transition(
            job: $job,
            status: ProcessingJobStatus::Failed,
            stage: $job->stage,
            attributes: [
                'completed_at' => now(),
                'processing_duration_seconds' => $duration,
                'error_message' => $error,
            ],
            logLevel: ProcessingLogLevel::Error,
            logSource: 'analysis',
            logMessage: $error,
        );

        $this->broadcast($job, notification: [
            'type' => 'error',
            'message' => __('ui.processing.failed_toast'),
            'url' => null,
            'call_id' => $job->call_id,
            'job_uuid' => $job->job_uuid,
        ]);

        return $job;
    }

    public function requeueForAnalysis(CallProcessingJob $job): CallProcessingJob
    {
        $job = $this->transition(
            job: $job,
            status: ProcessingJobStatus::Queued,
            stage: ProcessingJobStage::Queued,
            attributes: [
                'queued_at' => now(),
                'processing_started_at' => null,
                'completed_at' => null,
                'waiting_seconds' => null,
                'processing_duration_seconds' => null,
                'error_message' => null,
            ],
            logLevel: ProcessingLogLevel::Info,
            logSource: 'queue',
            logMessage: 'تحلیل دوباره به صف پردازش اضافه شد',
        );

        $job->call?->update([
            'processing_status' => \App\Domain\Call\Enums\CallProcessingStatus::Pending,
            'processing_error' => null,
        ]);

        return $job;
    }

    public function log(
        CallProcessingJob $job,
        ProcessingLogLevel $level,
        string $source,
        string $message,
        ?array $context = null,
    ): CallProcessingLog {
        return CallProcessingLog::query()->create([
            'call_processing_job_id' => $job->id,
            'call_id' => $job->call_id,
            'level' => $level,
            'source' => $source,
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    public function forCall(int $callId): ?CallProcessingJob
    {
        return CallProcessingJob::query()->where('call_id', $callId)->latest()->first();
    }

    /** @return array{queued: int, processing: int, completed: int, failed: int, total: int} */
    public function stats(int $organizationId, ?int $organizationUserId = null, ?int $uploaderUserId = null): array
    {
        $query = CallProcessingJob::query()->where('organization_id', $organizationId);

        if ($organizationUserId) {
            $query->where(function ($q) use ($organizationUserId, $uploaderUserId) {
                $q->where('organization_user_id', $organizationUserId);

                if ($uploaderUserId) {
                    $q->orWhere('uploader_id', $uploaderUserId);
                }
            });
        }

        return [
            'queued' => (clone $query)->where('status', ProcessingJobStatus::Queued)->count(),
            'processing' => (clone $query)->where('status', ProcessingJobStatus::Processing)->count(),
            'completed' => (clone $query)->where('status', ProcessingJobStatus::Completed)->count(),
            'failed' => (clone $query)->where('status', ProcessingJobStatus::Failed)->count(),
            'total' => (clone $query)->count(),
        ];
    }

    private function transition(
        CallProcessingJob $job,
        ProcessingJobStatus $status,
        ProcessingJobStage $stage,
        ?array $attributes = null,
        ?ProcessingLogLevel $logLevel = null,
        ?string $logSource = null,
        ?string $logMessage = null,
    ): CallProcessingJob {
        $job->update(array_merge([
            'status' => $status,
            'stage' => $stage,
            'progress_percentage' => $stage->progress(),
        ], $attributes ?? []));

        if ($logLevel && $logSource && $logMessage) {
            $this->log($job, $logLevel, $logSource, $logMessage);
        }

        $this->broadcast($job->refresh());

        return $job;
    }

    private function broadcast(CallProcessingJob $job, ?array $notification = null): void
    {
        try {
            broadcast(new CallProcessingUpdated($job, $notification));
        } catch (\Throwable) {
            // Broadcasting is best-effort; polling remains as fallback.
        }
    }
}
