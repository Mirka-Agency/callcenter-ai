<?php

namespace App\Livewire\Employee\ProcessingQueue;

use App\Livewire\Concerns\InteractsWithProcessingQueue;
use App\Livewire\Concerns\ManagesProcessingQueueJob;
use App\Models\CallProcessingJob;
use App\Services\EmployeeContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('صف پردازش')]
class Index extends Component
{
    use InteractsWithProcessingQueue;
    use ManagesProcessingQueueJob;

    protected function queueOrganizationId(): int
    {
        return EmployeeContext::organizationId();
    }

    protected function queueEmployeeScope(): ?int
    {
        return EmployeeContext::membership()->id;
    }

    protected function scopeProcessingJobs(Builder $query): Builder
    {
        $membershipId = EmployeeContext::membership()->id;

        return $query->where(function (Builder $q) use ($membershipId) {
            $q->where('organization_user_id', $membershipId)
                ->orWhere('uploader_id', auth()->id());
        });
    }

    protected function jobShowRoute(CallProcessingJob $job): string
    {
        return route('employee.processing-queue.show', $job);
    }

    protected function uploadShowRoute(CallProcessingJob $job): string
    {
        return route('employee.uploads.show', $job->call_id);
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
