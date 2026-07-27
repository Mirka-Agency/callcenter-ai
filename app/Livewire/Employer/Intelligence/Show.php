<?php

namespace App\Livewire\Employer\Intelligence;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
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
        EmployerIntegrationGate::authorizeFullManagement();

        if ($this->assignEmployeeId <= 0) {
            throw ValidationException::withMessages([
                'assignEmployeeId' => __('ui.voip.unmatched_extension_employee_required'),
            ]);
        }

        $organization = EmployerContext::organization();
        $callLog = $this->analysis->callLog;

        if (! $callLog) {
            throw ValidationException::withMessages([
                'assignEmployeeId' => 'این تحلیل به یک VoIP call log متصل نیست.',
            ]);
        }

        $extension = app(UnmatchedVoipExtensionService::class)->primaryExtension($callLog);

        if (! $extension) {
            throw ValidationException::withMessages([
                'assignEmployeeId' => 'داخلی قابل استخراج نیست.',
            ]);
        }

        app(UnmatchedVoipExtensionService::class)->assignExtensionToEmployee(
            organization: $organization,
            extension: $extension,
            connectionId: (int) $callLog->organization_voip_connection_id,
            organizationUserId: $this->assignEmployeeId,
        );

        $this->analysis->refresh()->load(['employee.user', 'callLog.connection', 'call.recording', 'call.processingJob', 'crmSyncs.crmConnection.provider']);
        $this->assignEmployeeId = 0;

        session()->flash('status', __('ui.voip.unmatched_extension_assigned', [
            'count' => 1,
        ]));
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
            'canManageIntegrations' => EmployerIntegrationGate::allowsFullManagement($organization),
            'employees' => $callLog
                ? OrganizationUser::query()
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get()
                : collect(),
            'createEmployeeUrl' => route('employer.employees.create'),
        ]);
    }
}
