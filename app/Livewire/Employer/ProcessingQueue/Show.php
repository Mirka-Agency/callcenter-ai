<?php

namespace App\Livewire\Employer\ProcessingQueue;

use App\Livewire\Concerns\ManagesProcessingQueueJob;
use App\Domain\Processing\Enums\ProcessingLogLevel;
use App\Models\CallProcessingJob;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('جزئیات کار')]
class Show extends Component
{
    use ManagesProcessingQueueJob;

    public CallProcessingJob $job;

    public string $logLevelFilter = '';

    public string $logSearch = '';

    public function mount(CallProcessingJob $job): void
    {
        abort_unless($job->organization_id === EmployerContext::organizationId(), 404);

        $this->job = $job->load(['call.latestAnalysis', 'call.recording', 'logs', 'employee', 'uploader']);
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(array $job = []): void
    {
        $jobUuid = $job['job_uuid'] ?? $job['job']['job_uuid'] ?? null;
        if ($jobUuid === $this->job->job_uuid) {
            $this->job->refresh()->load(['call.latestAnalysis', 'call.recording', 'logs']);
        }
    }

    protected function queueOrganizationId(): int
    {
        return EmployerContext::organizationId();
    }

    protected function scopeProcessingJobs(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('organization_id', EmployerContext::organizationId());
    }

    public function retryProcessingJob(): void
    {
        $this->retryJob($this->job->id);
        $this->job->refresh()->load(['call.latestAnalysis', 'call.recording', 'logs']);
    }

    public function deleteProcessingJob()
    {
        $this->deleteJob($this->job->id);

        return $this->redirect(route('employer.processing-queue.index'), navigate: true);
    }

    public function render()
    {
        $logs = $this->job->logs()
            ->when($this->logLevelFilter, fn ($q) => $q->where('level', $this->logLevelFilter))
            ->when($this->logSearch, fn ($q) => $q->where('message', 'like', '%'.$this->logSearch.'%'))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('livewire.shared.processing-queue.show', [
            'job' => $this->job,
            'logs' => $logs,
            'analysis' => $this->job->call?->latestAnalysis,
            'uploadUrl' => route('employer.manual-analyses.show', $this->job->call_id),
            'queueIndexUrl' => route('employer.processing-queue.index'),
            'organizationId' => EmployerContext::organizationId(),
            'logLevels' => ProcessingLogLevel::cases(),
        ]);
    }
}
