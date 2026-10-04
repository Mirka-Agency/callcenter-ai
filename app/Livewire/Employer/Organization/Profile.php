<?php

namespace App\Livewire\Employer\Organization;

use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;
use App\Services\CallIntake\CallIntakeFilterRegistry;
use App\Services\CallIntake\CallIntakeSettings;
use App\Services\EmployerContext;
use App\Support\CompanyWorkCalendar;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('پروفایل سازمان')]
class Profile extends Component
{
    /** @var list<int|string> */
    public array $holidayWeekdays = [];

    /** @var array<string, bool> */
    public array $intakeFilters = [];

    public function mount(CallIntakeSettings $settings): void
    {
        $organization = EmployerContext::organization();

        $this->holidayWeekdays = array_map(
            'strval',
            $organization->holidayWeekdays(),
        );
        $this->intakeFilters = $settings->resolved((int) $organization->id);
    }

    public function save(): void
    {
        $data = $this->validate([
            'holidayWeekdays' => ['array'],
            'holidayWeekdays.*' => ['integer', 'between:0,6'],
        ]);

        $weekdays = CompanyWorkCalendar::normalizeWeekdays($data['holidayWeekdays']);
        $organization = EmployerContext::organization();

        $organization->update([
            'holiday_weekdays' => $weekdays,
        ]);

        $this->holidayWeekdays = array_map('strval', $weekdays);

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: '".__('ui.success.holidays_saved')."' } }))");
    }

    public function saveIntakeFilters(CallIntakeSettings $settings): void
    {
        $data = $this->validate([
            'intakeFilters' => ['array'],
            'intakeFilters.*' => ['boolean'],
        ]);

        $organization = EmployerContext::organization();
        $stored = $settings->normalize($data['intakeFilters']);

        $organization->update([
            'call_intake_filters' => $stored,
        ]);

        $this->intakeFilters = $stored;
        $settings->forget((int) $organization->id);

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: '".__('ui.success.call_filters_saved')."' } }))");
    }

    public function render()
    {
        $organization = EmployerContext::organization();
        $defaultVoip = $organization->voipConnections()
            ->with('provider')
            ->where('is_default', true)
            ->first();

        $crmConnections = $organization->crmConnections()
            ->with('provider')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $selected = CompanyWorkCalendar::normalizeWeekdays($this->holidayWeekdays);
        $holidayLabels = [];
        $workdayLabels = [];

        foreach (CompanyWorkCalendar::weekdayOptions() as $weekday => $label) {
            if (in_array($weekday, $selected, true)) {
                $holidayLabels[] = $label;
            } else {
                $workdayLabels[] = $label;
            }
        }

        return view('livewire.employer.organization.profile', [
            'organizationTitle' => $organization->title,
            'voipProviderName' => $this->voipProviderName($defaultVoip),
            'defaultConnectionName' => $defaultVoip?->name,
            'webhookUrl' => $defaultVoip?->inbound_webhook_url,
            'crmConnections' => $this->crmRows($crmConnections),
            'weekdayOptions' => CompanyWorkCalendar::weekdayOptions(),
            'selectedWeekdays' => $selected,
            'holidayLabels' => $holidayLabels,
            'workdayLabels' => $workdayLabels,
            'intakeFilterOptions' => collect(app(CallIntakeFilterRegistry::class)->all())
                ->map(fn ($filter) => [
                    'key' => $filter->key(),
                    'label' => $filter->label(),
                    'description' => $filter->description(),
                ])
                ->all(),
        ]);
    }

    private function voipProviderName(?OrganizationVoipConnection $connection): ?string
    {
        $provider = $connection?->provider;

        if ($provider === null) {
            return null;
        }

        return VoipProviderCode::tryFrom((string) $provider->code)?->label() ?? $provider->name;
    }

    /**
     * @param  Collection<int, OrganizationCrmConnection>  $connections
     * @return list<array{name: string, provider: string, is_default: bool, is_active: bool}>
     */
    private function crmRows(Collection $connections): array
    {
        return $connections->map(function (OrganizationCrmConnection $connection): array {
            $provider = $connection->provider;

            return [
                'name' => $connection->name,
                'provider' => $provider === null
                    ? 'تعریف نشده'
                    : (CrmProviderCode::tryFrom((string) $provider->code)?->label() ?? $provider->name),
                'is_default' => (bool) $connection->is_default,
                'is_active' => (bool) $connection->is_active,
            ];
        })->all();
    }
}
