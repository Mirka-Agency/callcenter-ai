<?php

namespace App\Livewire\Crm;

use App\Application\Crm\Settings\OrganizationCrmPreferencesService;
use App\Domain\Crm\Deals\DealAutomationPolicy;
use App\Domain\Crm\DTOs\OrganizationCrmPreferences;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use App\Models\Organization;
use App\Services\EmployerContext;
use App\Support\PersianNumber;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('تنظیمات مشتریان و پیگیری')]
class Settings extends Component
{
    use ResolvesCrmContext;

    public string $callSummaryPipelineId = '';

    public bool $aiCatalogSearchEnabled = true;

    public bool $requireNationalIdForNewNumbers = false;

    public bool $aiProformaEnabled = true;

    public bool $salesFollowUpEnabled = false;

    public string $salesFollowUpDefaultDays = '3, 10';

    public string $salesFollowUpMaxCount = '2';

    public string $salesFollowUpMinDayInterval = '3';

    public string $salesFollowUpEarliestDays = '2';

    public bool $salesFollowUpAiCanChangeDays = false;

    public string $salesFollowUpReplanIntervalDays = '7';

    public string $legalName = '';

    public string $tradeName = '';

    public string $nationalId = '';

    public string $economicCode = '';

    public string $registrationNumber = '';

    public string $postalCode = '';

    public string $companyPhone = '';

    public string $companyEmail = '';

    public string $companyAddress = '';

    public string $defaultTaxPercent = '10';

    public function mount(OrganizationCrmPreferencesService $preferences): void
    {
        if ($this->crmPortal() !== 'employer') {
            abort(403);
        }

        $this->hydrateFromPreferences($preferences->forOrganization($this->organization()));
    }

    public function saveSalesFollowUp(OrganizationCrmPreferencesService $preferences): void
    {
        $this->validate([
            'salesFollowUpDefaultDays' => ['required', 'string', 'max:80'],
            'salesFollowUpMaxCount' => ['required', 'integer', 'min:1', 'max:20'],
            'salesFollowUpMinDayInterval' => ['required', 'integer', 'min:1', 'max:90'],
            'salesFollowUpEarliestDays' => ['required', 'integer', 'min:0', 'max:90'],
            'salesFollowUpReplanIntervalDays' => ['required', 'integer', 'min:1', 'max:365'],
            'salesFollowUpEnabled' => ['boolean'],
            'salesFollowUpAiCanChangeDays' => ['boolean'],
        ]);

        $prefs = $preferences->saveSalesFollowUp($this->organization(), [
            'enabled' => $this->salesFollowUpEnabled,
            'default_days' => $this->salesFollowUpDefaultDays,
            'max_count' => (int) $this->salesFollowUpMaxCount,
            'min_day_interval' => (int) $this->salesFollowUpMinDayInterval,
            'earliest_days' => (int) $this->salesFollowUpEarliestDays,
            'ai_can_change_days' => $this->salesFollowUpAiCanChangeDays,
            'replan_interval_days' => (int) $this->salesFollowUpReplanIntervalDays,
        ]);

        $this->hydrateFromPreferences($prefs);
        $this->toast('تنظیمات پیگیری فروش ذخیره شد.');
    }

    public function saveSettings(OrganizationCrmPreferencesService $preferences): void
    {
        $this->validate([
            'callSummaryPipelineId' => ['nullable', 'integer'],
            'aiCatalogSearchEnabled' => ['boolean'],
            'requireNationalIdForNewNumbers' => ['boolean'],
            'aiProformaEnabled' => ['boolean'],
            'legalName' => ['nullable', 'string', 'max:180'],
            'tradeName' => ['nullable', 'string', 'max:180'],
            'nationalId' => ['nullable', 'string', 'max:64'],
            'economicCode' => ['nullable', 'string', 'max:64'],
            'registrationNumber' => ['nullable', 'string', 'max:64'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'companyPhone' => ['nullable', 'string', 'max:40'],
            'companyEmail' => ['nullable', 'email', 'max:180'],
            'companyAddress' => ['nullable', 'string', 'max:500'],
            'defaultTaxPercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $prefs = $preferences->saveGeneral($this->organization(), [
            'call_summary_pipeline_id' => $this->callSummaryPipelineId !== '' ? (int) $this->callSummaryPipelineId : null,
            'ai_catalog_search_enabled' => $this->aiCatalogSearchEnabled,
            'require_national_id_for_new_numbers' => $this->requireNationalIdForNewNumbers,
            'ai_proforma_enabled' => $this->aiProformaEnabled,
            'company_identity' => [
                'legal_name' => $this->legalName,
                'trade_name' => $this->tradeName,
                'national_id' => $this->nationalId,
                'economic_code' => $this->economicCode,
                'registration_number' => $this->registrationNumber,
                'postal_code' => $this->postalCode,
                'phone' => $this->companyPhone,
                'email' => $this->companyEmail,
                'address' => $this->companyAddress,
            ],
            'default_tax_percent' => $this->defaultTaxPercent,
        ]);

        $this->hydrateFromPreferences($prefs);
        $this->toast('تنظیمات ذخیره شد.');
    }

    public function render()
    {
        $organizationId = $this->crmOrganizationId();
        $pipelines = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->withCount('stages')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('livewire.crm.settings', [
            'pipelines' => $pipelines,
            'autoCreate' => DealAutomationPolicy::autoCreateFromAnalysis(),
            'taxLabel' => PersianNumber::format((float) $this->defaultTaxPercent) ?? $this->defaultTaxPercent,
            'replanLabel' => PersianNumber::format((int) $this->salesFollowUpReplanIntervalDays) ?? $this->salesFollowUpReplanIntervalDays,
            'maxCountLabel' => PersianNumber::format((int) $this->salesFollowUpMaxCount) ?? $this->salesFollowUpMaxCount,
            'minIntervalLabel' => PersianNumber::format((int) $this->salesFollowUpMinDayInterval) ?? $this->salesFollowUpMinDayInterval,
            'earliestLabel' => PersianNumber::format((int) $this->salesFollowUpEarliestDays) ?? $this->salesFollowUpEarliestDays,
        ])->layout($this->crmLayout());
    }

    private function organization(): Organization
    {
        return EmployerContext::organization();
    }

    private function hydrateFromPreferences(OrganizationCrmPreferences $prefs): void
    {
        $this->callSummaryPipelineId = $prefs->callSummaryPipelineId
            ? (string) $prefs->callSummaryPipelineId
            : '';
        $this->aiCatalogSearchEnabled = $prefs->aiCatalogSearchEnabled;
        $this->requireNationalIdForNewNumbers = $prefs->requireNationalIdForNewNumbers;
        $this->aiProformaEnabled = $prefs->aiProformaEnabled;
        $this->salesFollowUpEnabled = $prefs->salesFollowUpEnabled;
        $this->salesFollowUpDefaultDays = $prefs->salesFollowUpDefaultDaysCsv();
        $this->salesFollowUpMaxCount = (string) $prefs->salesFollowUpMaxCount;
        $this->salesFollowUpMinDayInterval = (string) $prefs->salesFollowUpMinDayInterval;
        $this->salesFollowUpEarliestDays = (string) $prefs->salesFollowUpEarliestDays;
        $this->salesFollowUpAiCanChangeDays = $prefs->salesFollowUpAiCanChangeDays;
        $this->salesFollowUpReplanIntervalDays = (string) $prefs->salesFollowUpReplanIntervalDays;

        $identity = $prefs->companyIdentity;
        $this->legalName = $identity['legal_name'];
        $this->tradeName = $identity['trade_name'];
        $this->nationalId = $identity['national_id'];
        $this->economicCode = $identity['economic_code'];
        $this->registrationNumber = $identity['registration_number'];
        $this->postalCode = $identity['postal_code'];
        $this->companyPhone = $identity['phone'];
        $this->companyEmail = $identity['email'];
        $this->companyAddress = $identity['address'];
        $this->defaultTaxPercent = rtrim(rtrim(number_format($prefs->defaultTaxPercent, 2, '.', ''), '0'), '.') ?: '0';
    }
}
