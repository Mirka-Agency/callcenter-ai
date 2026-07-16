<?php

namespace App\Livewire\Employer\Crm;

use App\Application\Crm\Services\CrmDealSettingsService;
use App\Enums\IntegrationSetupStatus;
use App\Models\OrganizationCrmConnection;
use App\Services\EmployerContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('CRM')]
class Index extends Component
{
    public ?int $selectedConnectionId = null;

    public string $pipelineId = '';

    public string $pipelineStageId = '';

    public string $dealOwnerId = '';

    /** @var list<array{id: string, title: string, stages: list<array{id: string, title: string, index: int}>}> */
    public array $pipelines = [];

    /** @var list<array{id: string, name: string, email?: string}> */
    public array $users = [];

    public ?string $optionsError = null;

    public function mount(): void
    {
        $organization = EmployerContext::organization();
        $readiness = EmployerContext::integrationReadiness();

        if ($readiness->crmStatus !== IntegrationSetupStatus::Complete) {
            return;
        }

        $connection = $organization->crmConnections()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if (! $connection) {
            return;
        }

        $this->selectedConnectionId = $connection->id;
        $this->hydrateFromConnection($connection);
    }

    public function updatedSelectedConnectionId(): void
    {
        $connection = $this->resolveConnection();
        if (! $connection) {
            $this->resetSettingsState();

            return;
        }

        $this->hydrateFromConnection($connection);
    }

    public function updatedPipelineId(): void
    {
        $this->pipelineStageId = '';
    }

    public function save(): void
    {
        $connection = $this->resolveConnection();
        if (! $connection) {
            throw ValidationException::withMessages([
                'selectedConnectionId' => 'اتصال CRM فعالی پیدا نشد.',
            ]);
        }

        $this->validate([
            'pipelineId' => ['required', 'string', 'max:100'],
            'pipelineStageId' => ['required', 'string', 'max:100'],
            'dealOwnerId' => ['nullable', 'string', 'max:100'],
        ], [
            'pipelineId.required' => 'انتخاب کاریز الزامی است.',
            'pipelineStageId.required' => 'انتخاب مرحله کاریز الزامی است.',
        ]);

        $stageIds = collect($this->stagesForSelectedPipeline())->pluck('id')->all();
        if (! in_array($this->pipelineStageId, $stageIds, true)) {
            throw ValidationException::withMessages([
                'pipelineStageId' => 'مرحله انتخاب‌شده با کاریز هم‌خوانی ندارد.',
            ]);
        }

        if ($this->dealOwnerId !== '') {
            $userIds = collect($this->users)->pluck('id')->all();
            if ($userIds !== [] && ! in_array($this->dealOwnerId, $userIds, true)) {
                throw ValidationException::withMessages([
                    'dealOwnerId' => 'مالک معامله انتخاب‌شده معتبر نیست.',
                ]);
            }
        }

        app(CrmDealSettingsService::class)->updateDealDefaults(
            connection: $connection,
            pipelineId: $this->pipelineId,
            pipelineStageId: $this->pipelineStageId,
            dealOwnerId: $this->dealOwnerId !== '' ? $this->dealOwnerId : null,
        );

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'تنظیمات CRM ذخیره شد.' } }))");
    }

    public function refreshOptions(): void
    {
        $connection = $this->resolveConnection();
        if (! $connection) {
            return;
        }

        $this->loadRemoteOptions($connection);
    }

    /** @return Collection<int, OrganizationCrmConnection> */
    #[Computed]
    public function connections(): Collection
    {
        $organization = EmployerContext::organization();
        $readiness = EmployerContext::integrationReadiness();

        if ($readiness->crmStatus !== IntegrationSetupStatus::Complete) {
            return collect();
        }

        return $organization->crmConnections()
            ->with('provider')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->get();
    }

    /** @return list<array{id: string, title: string, index: int}> */
    public function stagesForSelectedPipeline(): array
    {
        foreach ($this->pipelines as $pipeline) {
            if (($pipeline['id'] ?? null) === $this->pipelineId) {
                $stages = $pipeline['stages'] ?? [];

                usort($stages, fn (array $a, array $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

                return $stages;
            }
        }

        return [];
    }

    public function render()
    {
        $readiness = EmployerContext::integrationReadiness();
        $isComplete = $readiness->crmStatus === IntegrationSetupStatus::Complete;

        return view('livewire.employer.crm.index', [
            'integrationReadiness' => $readiness->toArray(),
            'isComplete' => $isComplete,
            'stages' => $this->stagesForSelectedPipeline(),
        ]);
    }

    private function hydrateFromConnection(OrganizationCrmConnection $connection): void
    {
        $settings = $connection->settings ?? [];
        $this->pipelineId = (string) ($settings['pipeline_id'] ?? '');
        $this->pipelineStageId = (string) ($settings['pipeline_stage_id'] ?? '');
        $this->dealOwnerId = (string) ($settings['deal_owner_id'] ?? '');

        $this->loadRemoteOptions($connection);
    }

    private function loadRemoteOptions(OrganizationCrmConnection $connection): void
    {
        $dealSettings = app(CrmDealSettingsService::class);
        $pipelinesResult = $dealSettings->loadPipelines($connection);
        $usersResult = $dealSettings->loadUsers($connection);

        $this->pipelines = $pipelinesResult['pipelines'];
        $this->users = $usersResult['users'];
        $this->optionsError = collect([$pipelinesResult['error'], $usersResult['error']])
            ->filter()
            ->implode(' ');
        $this->optionsError = $this->optionsError !== '' ? $this->optionsError : null;
    }

    private function resolveConnection(): ?OrganizationCrmConnection
    {
        if (! $this->selectedConnectionId) {
            return null;
        }

        return EmployerContext::organization()
            ->crmConnections()
            ->whereKey($this->selectedConnectionId)
            ->where('is_active', true)
            ->first();
    }

    private function resetSettingsState(): void
    {
        $this->pipelineId = '';
        $this->pipelineStageId = '';
        $this->dealOwnerId = '';
        $this->pipelines = [];
        $this->users = [];
        $this->optionsError = null;
    }
}
