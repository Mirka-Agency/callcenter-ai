<?php

namespace App\Services;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\CallProcessingJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessingQueueFlusher
{
    private const PROCESSING_JOB_CLASSES = [
        AnalyzeAudioJob::class,
        UpdateEmployeeMetricsJob::class,
        SyncCrmJob::class,
    ];

    public function __construct(
        private CallProcessingTracker $tracker,
    ) {}

    /**
     * @return array{laravel_jobs_deleted: int, failed_jobs_deleted: int, tracking_jobs_cancelled: int}
     */
    public function flush(?int $organizationId = null, bool $includeHistory = false): array
    {
        $laravelJobsDeleted = $this->clearLaravelProcessingJobs();
        $failedJobsDeleted = $this->clearFailedJobs();
        $trackingJobsCancelled = $this->cancelActiveTrackingJobs($organizationId);

        if ($includeHistory) {
            $historyQuery = CallProcessingJob::query();

            if ($organizationId) {
                $historyQuery->where('organization_id', $organizationId);
            }

            $historyQuery->delete();
        }

        $result = [
            'laravel_jobs_deleted' => $laravelJobsDeleted,
            'failed_jobs_deleted' => $failedJobsDeleted,
            'tracking_jobs_cancelled' => $trackingJobsCancelled,
        ];

        Log::info('Processing queue flushed', $result);

        return $result;
    }

    /**
     * Cancel UI-tracked jobs that no longer have a matching Laravel queue entry.
     *
     * @return int Number of jobs reconciled.
     */
    public function syncOrphans(?int $organizationId = null): int
    {
        $query = CallProcessingJob::query()
            ->where('status', ProcessingJobStatus::Queued)
            ->where('queued_at', '<', now()->subMinutes(3));

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $reconciled = 0;

        foreach ($query->get() as $job) {
            if ($this->hasLaravelJobForCall($job->call_id)) {
                continue;
            }

            if ($this->hasFailedJobForCall($job->call_id)) {
                $this->tracker->markFailed($job, 'تحلیل در صف سیستم ناموفق بود. از دکمه «تلاش دوباره» استفاده کنید.');
                $reconciled++;

                continue;
            }

            $this->tracker->markCancelled($job, 'کار در صف سیستم یافت نشد و به‌عنوان لغوشده علامت‌گذاری شد.');
            $reconciled++;
        }

        if ($reconciled > 0) {
            Log::info('Processing queue orphans reconciled', [
                'reconciled' => $reconciled,
                'organization_id' => $organizationId,
            ]);
        }

        return $reconciled;
    }

    public function clearLaravelJobsForCall(int $callId): int
    {
        return $this->deleteLaravelJobsForCall($callId);
    }

    public function syncAfterQueueCommand(string $command): void
    {
        if (! in_array($command, ['queue:flush', 'queue:clear'], true)) {
            return;
        }

        $this->syncOrphans();
        $this->cancelActiveTrackingJobs();
    }

    private function clearLaravelProcessingJobs(): int
    {
        $deleted = 0;

        foreach (self::PROCESSING_JOB_CLASSES as $class) {
            $deleted += DB::table('jobs')
                ->where('payload', 'like', '%'.str_replace('\\', '\\\\', $class).'%')
                ->delete();
        }

        return $deleted;
    }

    private function clearFailedJobs(): int
    {
        $count = (int) DB::table('failed_jobs')->count();

        app('queue.failer')->flush();

        return $count;
    }

    private function cancelActiveTrackingJobs(?int $organizationId = null): int
    {
        $query = CallProcessingJob::query()
            ->whereIn('status', [
                ProcessingJobStatus::Uploading,
                ProcessingJobStatus::Queued,
                ProcessingJobStatus::Processing,
            ]);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $cancelled = 0;

        foreach ($query->get() as $job) {
            $this->tracker->markCancelled($job, 'صف پردازش پاک‌سازی شد.');
            $cancelled++;
        }

        return $cancelled;
    }

    private function hasLaravelJobForCall(int $callId): bool
    {
        foreach (self::PROCESSING_JOB_CLASSES as $class) {
            if ($this->countLaravelJobsForCall($callId, $class) > 0) {
                return true;
            }
        }

        return false;
    }

    private function hasFailedJobForCall(int $callId): bool
    {
        foreach (self::PROCESSING_JOB_CLASSES as $class) {
            $escapedClass = str_replace('\\', '\\\\', $class);

            $exists = DB::table('failed_jobs')
                ->where('payload', 'like', '%'.$escapedClass.'%')
                ->where(function ($query) use ($callId) {
                    foreach ($this->callIdPayloadPatterns($callId) as $pattern) {
                        $query->orWhere('payload', 'like', '%'.$pattern.'%');
                    }
                })
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return false;
    }

    private function deleteLaravelJobsForCall(int $callId, ?string $onlyClass = null): int
    {
        $classes = $onlyClass ? [$onlyClass] : self::PROCESSING_JOB_CLASSES;
        $deleted = 0;

        foreach ($classes as $class) {
            $deleted += $this->countLaravelJobsForCall($callId, $class, delete: true);
        }

        return $deleted;
    }

    private function countLaravelJobsForCall(int $callId, string $class, bool $delete = false): int
    {
        $escapedClass = str_replace('\\', '\\\\', $class);

        $query = DB::table('jobs')
            ->where('payload', 'like', '%'.$escapedClass.'%')
            ->where(function ($query) use ($callId) {
                foreach ($this->callIdPayloadPatterns($callId) as $pattern) {
                    $query->orWhere('payload', 'like', '%'.$pattern.'%');
                }
            });

        if ($delete) {
            return $query->delete();
        }

        return $query->exists() ? 1 : 0;
    }

    /** @return list<string> */
    private function callIdPayloadPatterns(int $callId): array
    {
        return [
            '"callId";i:'.$callId,
            'callId\";i:'.$callId,
            'callId";i:'.$callId,
            '"callId":'.$callId,
            '"callId": '.$callId,
        ];
    }
}
