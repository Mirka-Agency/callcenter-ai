<?php

namespace App\Livewire\Employee\Customers\Reviews;

use App\Models\CustomerIdentityReview;
use App\Services\EmployeeContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employee')]
#[Title('نیازمند بررسی')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $organizationId = EmployeeContext::membership()->organization_id;

        return view('livewire.shared.customers.reviews.index', [
            'reviews' => CustomerIdentityReview::query()
                ->where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->latest()
                ->paginate(20),
            'canResolve' => false,
            'portal' => 'employee',
        ]);
    }
}
