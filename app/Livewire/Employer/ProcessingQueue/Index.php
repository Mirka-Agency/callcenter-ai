<?php

namespace App\Livewire\Employer\ProcessingQueue;

use App\Livewire\Concerns\InteractsWithProcessingQueue;
use App\Livewire\Concerns\ManagesProcessingQueueJob;
use App\Models\CallProcessingJob;
use App\Services\EmployerContext;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Services\Reports\ProcessingQueueCallStats;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('صف پردازش')]
class Index extends Component
{
    use InteractsWithProcessingQueue;
    use ManagesProcessingQueueJob;

    protected function queueOrganizationId(): int
    {
        return EmployerContext::organizationId();
    }

    protected function scopeProcessingJobs(Builder $query): Builder
    {
        $organizationId = EmployerContext::organizationId();

        return app(DefinedExtensionCallConstraint::class)->applyToProcessingJobs(
            $query->where('organization_id', $organizationId),
            $organizationId,
        );
    }

    protected function jobShowRoute(CallProcessingJob $job): string
    {
        return route('employer.processing-queue.show', $job);
    }

    protected function uploadShowRoute(CallProcessingJob $job): string
    {
        return route('employer.manual-analyses.show', $job->call_id);
    }

    /** @return array{queued: int, processing: int, completed: int, failed: int, total: int} */
    protected function queueStats(): array
    {
        return app(ProcessingQueueCallStats::class)->forOrganization($this->queueOrganizationId());
    }

    public function render()
    {
        return view('livewire.shared.processing-queue.index', [
            'jobs' => $this->jobsQuery()->paginate(15),
            'stats' => $this->queueStats(),
            'statusOptions' => $this->statusOptions(),
            'organizationId' => $this->queueOrganizationId(),
            'jobShowRoute' => fn (CallProcessingJob $job) => $this->jobShowRoute($job),
            'uploadShowRoute' => fn (CallProcessingJob $job) => $this->uploadShowRoute($job),
        ]);
    }
}
