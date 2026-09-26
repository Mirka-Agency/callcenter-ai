<?php

namespace App\Services\Reports;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\Call;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employer queue cards count calls, using the latest processing job when one exists.
 * A finished analysis with no queue row still counts as completed.
 */
class ProcessingQueueCallStats
{
    public function __construct(private DefinedExtensionCallConstraint $definedExtensions) {}

    /**
     * @return array{queued: int, processing: int, completed: int, failed: int, total: int}
     */
    public function forOrganization(int $organizationId): array
    {
        $query = $this->definedExtensions->applyToQueueCalls(
            Call::query()->where('organization_id', $organizationId),
            $organizationId,
        );

        $queued = $this->countBucket($query, 'queued');
        $processing = $this->countBucket($query, 'processing');
        $completed = $this->countBucket($query, 'completed');
        $failed = $this->countBucket($query, 'failed');

        return [
            'queued' => $queued,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'total' => (clone $query)->count(),
        ];
    }

    /** @param  Builder<Call>  $query */
    private function countBucket(Builder $query, string $bucket): int
    {
        return (clone $query)
            ->whereRaw('('.$this->bucketSql().') = ?', [$bucket])
            ->count();
    }

    /**
     * Latest job state wins over an older successful analysis.
     * A call whose analysis finished and has no job is completed.
     */
    private function bucketSql(): string
    {
        $latest = '(select cpj.status from call_processing_jobs as cpj where cpj.call_id = calls.id order by cpj.id desc limit 1)';
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
