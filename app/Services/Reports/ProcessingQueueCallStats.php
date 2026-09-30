<?php

namespace App\Services\Reports;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\Call;
use App\Models\CallProcessingJob;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employer queue cards count calls, using the latest processing job when one exists.
 * A finished analysis with no queue row still counts as completed.
 * Only defined-extension calls that have a recording are included.
 *
 * The intelligence page "تماس‌های تحلیل‌شده" card counts calls that still
 * have an analysis row, so deleting analyses clears that card on its own.
 */
class ProcessingQueueCallStats
{
    public function __construct(private DefinedExtensionCallConstraint $definedExtensions) {}

    /**
     * @return array{queued: int, processing: int, completed: int, failed: int, total: int}
     */
    public function forOrganization(int $organizationId): array
    {
        return $this->forQuery(
            $this->definedExtensions->applyToQueueCalls(
                Call::query()->where('organization_id', $organizationId),
                $organizationId,
            ),
        );
    }

    /**
     * Same completed definition as the queue "تکمیل‌شده" card, for an already-scoped call query.
     *
     * @param  Builder<Call>  $query
     */
    public function completedForQuery(Builder $query): int
    {
        return $this->forQuery($query)['completed'];
    }

    /**
     * @param  Builder<Call>  $query
     * @return array{queued: int, processing: int, completed: int, failed: int, total: int}
     */
    public function forQuery(Builder $query): array
    {
        $latestJobs = CallProcessingJob::query()
            ->select('call_processing_jobs.call_id', 'call_processing_jobs.status')
            ->joinSub(
                CallProcessingJob::query()
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('call_id'),
                'latest_job_ids',
                'latest_job_ids.id',
                '=',
                'call_processing_jobs.id',
            );
        $bucket = $this->bucketSql();
        $counts = (clone $query)
            ->leftJoinSub($latestJobs, 'latest_jobs', 'latest_jobs.call_id', '=', 'calls.id')
            ->selectRaw($bucket.' as bucket, COUNT(*) as aggregate')
            ->groupByRaw($bucket)
            ->pluck('aggregate', 'bucket');

        return [
            'queued' => (int) ($counts['queued'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    /**
     * Latest job state wins over an older successful analysis.
     * A call whose analysis finished and has no job is completed.
     */
    private function bucketSql(): string
    {
        $latest = 'latest_jobs.status';
        $processing = "'".ProcessingJobStatus::Uploading->value."', '".ProcessingJobStatus::Processing->value."'";
        $failed = "'".ProcessingJobStatus::Failed->value."', '".ProcessingJobStatus::Cancelled->value."'";

        return "CASE
            WHEN {$latest} IN ({$processing})
                OR calls.processing_status IN ('".CallProcessingStatus::Downloading->value."', '".CallProcessingStatus::Analyzing->value."') THEN 'processing'
            WHEN {$latest} = '".ProcessingJobStatus::Queued->value."'
                OR calls.processing_status = '".CallProcessingStatus::Pending->value."' THEN 'queued'
            WHEN {$latest} IN ({$failed})
                OR calls.processing_status = '".CallProcessingStatus::Failed->value."' THEN 'failed'
            WHEN calls.processing_status = '".CallProcessingStatus::Analyzed->value."'
                OR {$latest} = '".ProcessingJobStatus::Completed->value."' THEN 'completed'
            ELSE 'other'
        END";
    }
}
