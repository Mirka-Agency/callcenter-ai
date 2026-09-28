<?php

namespace App\Livewire\Employer\Intelligence;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Services\AiBillingService;
use App\Services\EmployerContext;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('تحلیل')]
class Show extends Component
{
    use ResolvesRecordingPlayback;

    public ConversationAnalysis $analysis;

    public int $assignEmployeeId = 0;

    public function mount(ConversationAnalysis $analysis): void
    {
        abort_unless($analysis->organization_id === EmployerContext::organizationId(), 404);
        $this->analysis = $analysis;
        $this->loadAnalysis();
    }

    public function reanalyze(): void
    {
        $call = $this->analysis->call;

        if ($call === null) {
            session()->flash('error', __('ui.intelligence.reanalyze_one_unavailable'));

            return;
        }

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call, forceReanalyze: true);

        $this->analysis->refresh();
        $this->loadAnalysis();

        if ($queued) {
            session()->flash('status', __('ui.intelligence.reanalyze_one_queued'));

            return;
        }

        session()->flash('error', $this->reanalyzeFailureMessage($call->fresh(['recording', 'processingJob', 'voipCallLog'])));
    }

    public function recordingPlayback(): array
    {
        return $this->recordingPlaybackState(
            $this->analysis->call?->recording,
            $this->analysis->callLog?->recording_url,
        );
    }

    public function assignCallEmployee(): void
    {
        if ($this->assignEmployeeId <= 0) {
            throw ValidationException::withMessages([
                'assignEmployeeId' => __('ui.voip.unmatched_extension_employee_required'),
            ]);
        }

        $organization = EmployerContext::organization();

        $employee = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->whereKey($this->assignEmployeeId)
            ->where('is_active', true)
            ->firstOrFail();

        // Always attach this analysis (and its call) to the selected employee.
        $this->analysis->update(['organization_user_id' => $employee->id]);

        if ($this->analysis->call) {
            $this->analysis->call->update(['organization_user_id' => $employee->id]);
        }

        $callLog = $this->analysis->callLog;
        $extension = $callLog
            ? app(UnmatchedVoipExtensionService::class)->primaryExtension($callLog)
            : null;

        // If we can resolve an extension, also map it for future calls and backfill.
        if ($callLog && $extension) {
            app(UnmatchedVoipExtensionService::class)->assignExtensionToEmployee(
                organization: $organization,
                extension: $extension,
                connectionId: (int) $callLog->organization_voip_connection_id,
                organizationUserId: $employee->id,
            );
        }

        $this->analysis->refresh();
        $this->loadAnalysis();
        $this->assignEmployeeId = 0;

        session()->flash('status', $extension
            ? __('ui.voip.unmatched_extension_assigned', ['count' => 1])
            : __('ui.voip.analysis_employee_assigned'));
    }

    public function render()
    {
        $playback = $this->recordingPlayback();
        $organization = EmployerContext::organization();
        $callLog = $this->analysis->callLog;
        $extension = $callLog ? app(UnmatchedVoipExtensionService::class)->primaryExtension($callLog) : null;
        $resolvedEmployeeId = $callLog ? app(CallEmployeeResolver::class)->resolveFromCallLog($callLog) : null;

        return view('livewire.employer.intelligence.show', [
            'recordingUrl' => $playback['url'],
            'recordingExpired' => $playback['expired'],
            'deletedRecordingUrl' => $this->deletedRecordingUrl(
                $playback['expired'],
                $this->analysis->call?->voipCallLog?->recording_url,
                $this->analysis->callLog?->recording_url,
            ),
            'callLog' => $callLog,
            'extension' => $extension,
            'resolvedEmployeeId' => $resolvedEmployeeId,
            'canAssignEmployee' => true,
            'employees' => OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'createEmployeeUrl' => route('employer.employees.create'),
            'canReanalyze' => $this->analysis->call !== null,
        ]);
    }

    private function loadAnalysis(): void
    {
        $this->analysis->load([
            'employee.user',
            'call.recording',
            'call.voipCallLog',
            'call.processingJob',
            'call.customer.company',
            'callLog.connection',
            'crmSyncs.crmConnection.provider',
        ]);
    }

    private function reanalyzeFailureMessage(Call $call): string
    {
        $job = $call->processingJob;

        if ($job && in_array($job->status, [
            ProcessingJobStatus::Queued,
            ProcessingJobStatus::Processing,
            ProcessingJobStatus::Uploading,
        ], true)) {
            return __('ui.intelligence.reanalyze_one_already_queued');
        }

        if ($call->processing_status === CallProcessingStatus::Skipped && filled($call->processing_error)) {
            return (string) $call->processing_error;
        }

        $recording = $call->recording;
        $hasLocalRecording = $recording
            && $recording->status === 'completed'
            && filled($recording->storage_path)
            && ! $recording->is_expired;
        $hasRecordingUrl = filled($call->voipCallLog?->recording_url) || filled($recording?->source_url);

        if (! $hasLocalRecording && ! $hasRecordingUrl) {
            return __('ui.processing.retry_missing_recording');
        }

        if (! $call->organization_user_id) {
            return __('ui.intelligence.reanalyze_one_employee_required');
        }

        try {
            app(AiBillingService::class)->assertCanAnalyze($call->organization_id);
        } catch (InsufficientWalletBalanceException) {
            return __('ui.wallet.insufficient');
        }

        return __('ui.intelligence.reanalyze_one_failed');
    }
}
