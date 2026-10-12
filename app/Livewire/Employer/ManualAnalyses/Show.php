<?php

namespace App\Livewire\Employer\ManualAnalyses;

use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Call\Enums\ConversationSource;
use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\Call;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('تحلیل دستی')]
class Show extends Component
{
    use ResolvesRecordingPlayback;

    public Call $upload;

    public int $assignEmployeeId = 0;

    public function mount(Call $upload): void
    {
        abort_unless($upload->source === ConversationSource::ManualUpload, 404);
        abort_unless($upload->organization_id === EmployerContext::organizationId(), 404);

        $this->upload = $upload->load(['employee', 'uploader', 'latestAnalysis', 'recording', 'processingJob', 'customer.company']);
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(array $job = []): void
    {
        if (($job['call_id'] ?? $job['job']['call_id'] ?? null) === $this->upload->id) {
            $this->upload->refresh()->load(['employee', 'uploader', 'latestAnalysis', 'recording', 'processingJob', 'customer.company']);
        }
    }

    public function recordingPlayback(): array
    {
        return $this->recordingPlaybackState($this->upload->recording);
    }

    public function assignCallEmployee(): void
    {
        if ($this->assignEmployeeId <= 0) {
            throw ValidationException::withMessages([
                'assignEmployeeId' => __('ui.voip.unmatched_extension_employee_required'),
            ]);
        }

        $organizationId = EmployerContext::organizationId();

        $employee = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->whereKey($this->assignEmployeeId)
            ->where('is_active', true)
            ->firstOrFail();

        $this->upload->update(['organization_user_id' => $employee->id]);

        $this->upload->analyses()->update(['organization_user_id' => $employee->id]);

        $call = $this->upload->fresh(['recording', 'voipCallLog']);
        $queued = $call && $call->analyses()->exists()
            ? app(CallAnalysisQueueService::class)->dispatchForCall($call, forceReanalyze: true)
            : false;

        if (! $queued) {
            UpdateEmployeeMetricsJob::dispatch($this->upload->id);
        }

        $this->upload->refresh()->load(['employee', 'uploader', 'latestAnalysis', 'recording', 'processingJob', 'customer.company']);
        $this->assignEmployeeId = 0;

        session()->flash('status', $queued
            ? __('ui.voip.analysis_employee_assigned_reanalyze')
            : __('ui.voip.analysis_employee_assigned'));
    }

    public function render()
    {
        $playback = $this->stableRecordingPlaybackState($this->upload->recording);
        $organizationId = EmployerContext::organizationId();

        return view('livewire.employer.manual-analyses.show', [
            'recordingUrl' => $playback['url'],
            'recordingExpired' => $playback['expired'],
            'analysis' => $this->upload->latestAnalysis,
            'call' => $this->upload,
            'organizationId' => $organizationId,
            'employees' => OrganizationUser::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'createEmployeeUrl' => route('employer.employees.create'),
            'queueUrl' => $this->upload->processingJob
                ? route('employer.processing-queue.show', $this->upload->processingJob)
                : route('employer.processing-queue.index'),
        ]);
    }
}
