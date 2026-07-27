<?php

namespace App\Livewire\Employer\Intelligence;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
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
        $this->analysis = $analysis->load([
            'employee.user',
            'call.recording',
            'call.processingJob',
            'callLog.connection',
            'crmSyncs.crmConnection.provider',
        ]);
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

        $this->analysis->refresh()->load([
            'employee.user',
            'callLog.connection',
            'call.recording',
            'call.processingJob',
            'crmSyncs.crmConnection.provider',
        ]);
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
        ]);
    }
}
