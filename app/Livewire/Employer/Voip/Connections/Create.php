<?php

namespace App\Livewire\Employer\Voip\Connections;

use App\Application\Voip\Services\VoipConnectionLifecycleService;
use App\Models\VoipProvider;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.employer')]
#[Title('افزودن اتصال VoIP')]
class Create extends VoipConnectionForm
{
    public function mount(): void
    {
        $this->ensureAuthorized();

        $firstProvider = VoipProvider::query()->where('is_active', true)->value('id');
        $this->voip_provider_id = (int) ($firstProvider ?? 0);
        $this->updatedVoipProviderId();
    }

    public function save(): void
    {
        $this->ensureAuthorized();

        $data = $this->validate($this->validationRules(creating: true));

        app(VoipConnectionLifecycleService::class)->create(
            organizationId: $this->organizationId(),
            data: $this->buildPayload($data),
        );

        session()->flash('status', __('ui.integrations.voip_created'));

        $this->redirect(route('employer.voip.connections.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.employer.voip.connections.form', [
            'connection' => null,
            'providers' => $this->providers(),
        ]);
    }
}
