<?php

namespace App\Livewire\Employer\Voip;

use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Livewire\Employer\Voip\Concerns\AssignsUnmatchedVoipExtensions;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('داخلی‌های بدون کارشناس')]
class UnmatchedExtensions extends Component
{
    use AssignsUnmatchedVoipExtensions;

    public function render()
    {
        $organization = EmployerContext::organization();

        $unmatchedExtensions = app(UnmatchedVoipExtensionService::class)->listUnmatched($organization);

        return view('livewire.employer.voip.unmatched-extensions', [
            'unmatchedExtensions' => $unmatchedExtensions,
            'waitingCallCount' => collect($unmatchedExtensions)->sum('call_count'),
            'employees' => OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }
}
