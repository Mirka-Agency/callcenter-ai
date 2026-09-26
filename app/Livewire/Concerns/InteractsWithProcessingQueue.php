<?php

namespace App\Livewire\Concerns;

use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\CallProcessingJob;
use App\Services\ProcessingQueueFlusher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\WithPagination;

trait InteractsWithProcessingQueue
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $autoRefresh = true;

    abstract protected function queueOrganizationId(): int;

    abstract protected function scopeProcessingJobs(Builder $query): Builder;

    abstract protected function jobShowRoute(CallProcessingJob $job): string;

    abstract protected function uploadShowRoute(CallProcessingJob $job): string;

    public function refreshQueue(): void
    {
        app(ProcessingQueueFlusher::class)->syncOrphans($this->queueOrganizationId());
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(): void
    {
        // Re-render queue stats and job list when broadcasts arrive.
    }

    protected function jobsQuery(): Builder
    {
        $query = $this->scopeProcessingJobs(
            CallProcessingJob::query()
                ->with(['call.latestAnalysis', 'employee', 'uploader'])
                ->when($this->statusFilter, fn (Builder $q) => $q->where('status', $this->statusFilter))
                ->when($this->search, function (Builder $q) {
                    $term = '%'.$this->search.'%';
                    $q->where(function (Builder $inner) use ($term) {
                        $inner->where('file_name', 'like', $term)
                            ->orWhere('job_uuid', 'like', $term);
                    });
                })
                ->latest(),
        );

        Log::info('Queue fetch result', [
            'organization_id' => $this->queueOrganizationId(),
            'active_count' => (clone $query)->whereIn('status', ['uploading', 'queued', 'processing'])->count(),
            'total_count' => (clone $query)->count(),
        ]);

        return $query;
    }

    /**
     * Card counts use the same rows as the queue table.
     * Uploading is still in progress, and a cancelled job did not finish,
     * so those two statuses sit in the processing and failed cards.
     * Together the four cards equal the total.
     *
     * @return array{queued: int, processing: int, completed: int, failed: int, total: int}
     */
    protected function queueStats(): array
    {
        $query = $this->scopeProcessingJobs(CallProcessingJob::query());

        $queued = (clone $query)->where('status', ProcessingJobStatus::Queued)->count();
        $processing = (clone $query)->whereIn('status', [
            ProcessingJobStatus::Processing,
            ProcessingJobStatus::Uploading,
        ])->count();
        $completed = (clone $query)->where('status', ProcessingJobStatus::Completed)->count();
        $failed = (clone $query)->whereIn('status', [
            ProcessingJobStatus::Failed,
            ProcessingJobStatus::Cancelled,
        ])->count();

        return [
            'queued' => $queued,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'total' => $queued + $processing + $completed + $failed,
        ];
    }

    protected function statusOptions(): array
    {
        return collect(ProcessingJobStatus::cases())
            ->mapWithKeys(fn (ProcessingJobStatus $status) => [$status->value => $status->label()])
            ->all();
    }
}
