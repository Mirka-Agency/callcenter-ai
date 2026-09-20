<?php

namespace App\Livewire\Employer\Voip;

use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Services\EmployerContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('داخلی‌ها')]
class Extensions extends Component
{
    public string $newExtension = '';

    public ?int $newConnectionId = null;

    public int $newEmployeeId = 0;

    public bool $showAddForm = false;

    /** @var array<string, int|string> */
    public array $employeeSelections = [];

    public function mount(): void
    {
        $connections = $this->activeConnections();

        if ($connections->count() === 1) {
            $this->newConnectionId = (int) $connections->first()->id;
        }
    }

    public function addExtension(): void
    {
        $organization = EmployerContext::organization();
        $service = app(UnmatchedVoipExtensionService::class);

        $this->newExtension = $service->normalizeExtension($this->newExtension);

        $this->validate([
            'newExtension' => ['required', 'string', 'max:32'],
            'newConnectionId' => ['required', 'integer'],
            'newEmployeeId' => ['required', 'integer', 'min:1'],
        ], [
            'newExtension.required' => __('ui.voip.extensions_number_required'),
            'newConnectionId.required' => __('ui.voip.extensions_connection_required'),
            'newEmployeeId.required' => __('ui.voip.unmatched_extension_employee_required'),
            'newEmployeeId.min' => __('ui.voip.unmatched_extension_employee_required'),
        ]);

        try {
            $backfilled = $service->createExtension(
                organization: $organization,
                extension: $this->newExtension,
                connectionId: (int) $this->newConnectionId,
                organizationUserId: (int) $this->newEmployeeId,
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (isset($errors['extension'])) {
                $errors['newExtension'] = $errors['extension'];
                unset($errors['extension']);
            }

            throw ValidationException::withMessages($errors);
        }

        $this->resetAddForm();
        $this->showAddForm = false;

        session()->flash('status', __('ui.voip.extensions_created', ['count' => $backfilled]));
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;

        if (! $this->showAddForm) {
            $this->resetAddForm();
        }
    }

    public function updateEmployee(string $extension, int $connectionId): void
    {
        $organization = EmployerContext::organization();
        $selectionKey = $extension.'__'.$connectionId;
        $organizationUserId = (int) ($this->employeeSelections[$selectionKey] ?? 0);

        if ($organizationUserId <= 0) {
            throw ValidationException::withMessages([
                'employeeSelections.'.$selectionKey => __('ui.voip.unmatched_extension_employee_required'),
            ]);
        }

        try {
            $backfilled = app(UnmatchedVoipExtensionService::class)->reassignExtension(
                organization: $organization,
                extension: $extension,
                connectionId: $connectionId,
                organizationUserId: $organizationUserId,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: __('ui.voip.unmatched_extension_conflict');

            throw ValidationException::withMessages([
                'employeeSelections.'.$selectionKey => $message,
            ]);
        }

        session()->flash('status', __('ui.voip.extensions_updated', ['count' => $backfilled]));
    }

    public function deleteExtension(string $extension, int $connectionId): void
    {
        app(UnmatchedVoipExtensionService::class)->removeExtension(
            organization: EmployerContext::organization(),
            extension: $extension,
            connectionId: $connectionId,
        );

        unset($this->employeeSelections[$extension.'__'.$connectionId]);

        session()->flash('status', __('ui.voip.extensions_deleted'));
    }

    public function render()
    {
        $organization = EmployerContext::organization();
        $extensions = app(UnmatchedVoipExtensionService::class)->listAssigned($organization);
        $connections = $this->activeConnections();
        $employees = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        foreach ($extensions as $row) {
            $selectionKey = $row['extension'].'__'.$row['connection_id'];

            if (! array_key_exists($selectionKey, $this->employeeSelections)) {
                $this->employeeSelections[$selectionKey] = $row['employee_id'];
            }
        }

        return view('livewire.employer.voip.extensions', [
            'extensions' => $extensions,
            'connections' => $connections,
            'employees' => $employees,
        ]);
    }

    /**
     * @return Collection<int, OrganizationVoipConnection>
     */
    private function activeConnections(): Collection
    {
        return EmployerContext::organization()
            ->voipConnections()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function resetAddForm(): void
    {
        $this->newExtension = '';
        $this->newEmployeeId = 0;
        $this->resetValidation();

        $connections = $this->activeConnections();

        if ($connections->count() === 1) {
            $this->newConnectionId = (int) $connections->first()->id;

            return;
        }

        $this->newConnectionId = null;
    }
}
