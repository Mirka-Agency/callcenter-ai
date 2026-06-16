<?php

namespace App\Livewire\Concerns;

use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\CallProcessingJob;
use App\Services\CallProcessingTracker;
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

    public function bootInteractsWithProcessingQueue(): void
    {
        app(ProcessingQueueFlusher::class)->syncOrphans($this->queueOrganizationId());
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

    /** @return array<string, int> */
    protected function queueStats(): array
    {
        return app(CallProcessingTracker::class)->stats(
            $this->queueOrganizationId(),
            $this->queueEmployeeScope(),
            $this->queueEmployeeScope() ? auth()->id() : null,
        );
    }

    protected function queueEmployeeScope(): ?int
    {
        return null;
    }

    protected function statusOptions(): array
    {
        return collect(ProcessingJobStatus::cases())
            ->mapWithKeys(fn (ProcessingJobStatus $status) => [$status->value => $status->label()])
            ->all();
    }
}
