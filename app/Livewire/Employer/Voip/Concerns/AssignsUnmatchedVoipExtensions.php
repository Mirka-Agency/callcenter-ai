<?php

namespace App\Livewire\Employer\Voip\Concerns;

use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Services\EmployerContext;
use Illuminate\Validation\ValidationException;

trait AssignsUnmatchedVoipExtensions
{
    /** @var array<string, int|string> */
    public array $unmatchedSelections = [];

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
}
