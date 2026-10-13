<?php

namespace App\Livewire\Employer\Customers\Contacts;

use App\Livewire\Concerns\HasCustomerListPerPage;
use App\Livewire\Concerns\HasCustomerListSort;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Support\CustomerListQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employer')]
#[Title('اشخاص')]
class Index extends Component
{
    use HasCustomerListPerPage;
    use HasCustomerListSort;
    use WithPagination;

    public string $search = '';

    public string $ownerFilter = 'all';

    public string $specificOwnerId = '';

    public string $stageFilter = 'all';

    public bool $assignFromCall = false;

    public function mount(): void
    {
        $this->assignFromCall = (bool) EmployerContext::organization()->assign_customer_owner_from_call;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOwnerFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSpecificOwnerId(): void
    {
        $this->resetPage();
    }

    public function updatedStageFilter(): void
    {
        $this->resetPage();
    }

    public function saveOwnerPolicy(): void
    {
        EmployerContext::organization()->update([
            'assign_customer_owner_from_call' => $this->assignFromCall,
        ]);

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'سیاست مسئول مشتری ذخیره شد.' } }))");
    }

    public function render()
    {
        $organizationId = EmployerContext::organizationId();
        $specificOwnerId = ctype_digit($this->specificOwnerId) ? (int) $this->specificOwnerId : null;
        $membershipId = $this->viewerMembershipId();
        $contacts = CustomerListQuery::contacts(
            $organizationId,
            $this->search,
            $this->sort,
            $this->ownerFilter,
            $membershipId,
            $specificOwnerId,
            $this->stageFilter,
        )->paginate($this->currentPerPage());

        return view('livewire.shared.customers.contacts.index', [
            'contacts' => $contacts,
            'portal' => 'employer',
            'ownerChoices' => CustomerListQuery::ownerChoices($organizationId),
            'lifecycleCounts' => CustomerListQuery::lifecycleCounts(
                $organizationId,
                $this->search,
                $this->ownerFilter,
                $membershipId,
                $specificOwnerId,
            ),
        ]);
    }

    private function viewerMembershipId(): ?int
    {
        $id = OrganizationUser::query()
            ->where('organization_id', EmployerContext::organizationId())
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->value('id');

        return $id ? (int) $id : null;
    }
}
