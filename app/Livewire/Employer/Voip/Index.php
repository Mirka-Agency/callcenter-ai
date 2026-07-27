<?php

namespace App\Livewire\Employer\Voip;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Domain\Voip\Enums\CallStatus;
use App\Enums\IntegrationSetupStatus;
use App\Models\OrganizationUser;
use App\Models\VoipCallLog;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('VoIP')]
class Index extends Component
{
    /** @var array<string, int|string> */
    public array $unmatchedSelections = [];

    public function regenerateWebhookToken(int $connectionId): void
    {
        $connection = EmployerContext::organization()
            ->voipConnections()
            ->whereKey($connectionId)
            ->firstOrFail();

        $connection->regenerateWebhookToken();

        session()->flash('status', __('ui.voip.webhook_token_regenerated'));
    }

    public function assignUnmatchedExtension(string $extension, int $connectionId): void
    {
        $organization = EmployerContext::organization();
        $selectionKey = $extension.'__'.$connectionId;
        $organizationUserId = (int) ($this->unmatchedSelections[$selectionKey] ?? 0);

        if ($organizationUserId <= 0) {
            throw ValidationException::withMessages([
                'unmatchedSelections.'.$selectionKey => __('ui.voip.unmatched_extension_employee_required'),
            ]);
        }

        $backfilled = app(UnmatchedVoipExtensionService::class)->assignExtensionToEmployee(
            organization: $organization,
            extension: $extension,
            connectionId: $connectionId,
            organizationUserId: $organizationUserId,
        );

        unset($this->unmatchedSelections[$selectionKey]);

        session()->flash('status', __('ui.voip.unmatched_extension_assigned', ['count' => $backfilled]));
    }

    public function render()
    {
        $organization = EmployerContext::organization();
        $organizationId = $organization->id;
        $readiness = EmployerContext::integrationReadiness();
        $isComplete = $readiness->voipStatus === IntegrationSetupStatus::Complete;
        $connections = $isComplete
            ? $organization->voipConnections()->with('provider')->get()
            : collect();

        $providerCodes = $connections->pluck('provider.code')->filter()->unique()->values();
        $hasCustom = $providerCodes->contains('custom');
        $hasSimotelFamily = $providerCodes->contains(fn (string $code): bool => in_array($code, ['simotel', 'novatel'], true));

        $recentCallsHint = match (true) {
            $hasCustom && $hasSimotelFamily => __('ui.voip.recent_calls_hint_mixed'),
            $hasCustom => __('ui.voip.recent_calls_hint_custom'),
            default => __('ui.voip.recent_calls_hint_simotel'),
        };

        $resolver = app(CallEmployeeResolver::class);
        $unmatchedService = app(UnmatchedVoipExtensionService::class);

        $recentCalls = $isComplete
            ? VoipCallLog::query()->where('organization_id', $organizationId)->latest('started_at')->limit(10)->get()
            : collect();

        $recentCallRows = $recentCalls->map(function (VoipCallLog $log) use ($resolver, $unmatchedService, $organizationId) {
            $extension = $unmatchedService->primaryExtension($log);
            $employeeId = $resolver->resolveFromCallLog($log);

            return [
                'log' => $log,
                'extension' => $extension,
                'employee_id' => $employeeId,
            ];
        });

        return view('livewire.employer.voip.index', [
            'connections' => $connections,
            'integrationReadiness' => $readiness->toArray(),
            'isComplete' => $isComplete,
            'recentCallsHint' => $recentCallsHint,
            'canManageIntegrations' => EmployerIntegrationGate::allowsFullManagement($organization),
            'todayCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->whereDate('started_at', today())->count()
                : 0,
            'monthCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->whereMonth('started_at', now()->month)->count()
                : 0,
            'missedCalls' => $isComplete
                ? VoipCallLog::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', CallStatus::Missed)
                    ->count()
                : 0,
            'recentCallRows' => $recentCallRows,
            'unmatchedExtensions' => $isComplete
                ? $unmatchedService->listUnmatched($organization)
                : [],
            'employees' => $isComplete
                ? OrganizationUser::query()
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get()
                : collect(),
            'incomingCallEndpoint' => url('/api/voip/incoming-call'),
        ]);
    }
}
