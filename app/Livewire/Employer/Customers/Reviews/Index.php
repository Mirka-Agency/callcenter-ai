<?php

namespace App\Livewire\Employer\Customers\Reviews;

use App\Application\Customer\Actions\ResolveIdentityReview;
use App\Models\CustomerIdentityReview;
use App\Services\EmployerContext;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employer')]
#[Title('نیازمند بررسی')]
class Index extends Component
{
    use WithPagination;

    public function accept(int $reviewId, ResolveIdentityReview $resolver): void
    {
        $this->resolve($reviewId, $resolver, accept: true);
    }

    public function dismiss(int $reviewId, ResolveIdentityReview $resolver): void
    {
        $this->resolve($reviewId, $resolver, accept: false);
    }

    public function render()
    {
        $organizationId = EmployerContext::organizationId();

        return view('livewire.shared.customers.reviews.index', [
            'reviews' => CustomerIdentityReview::query()
                ->where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->latest()
                ->paginate(20),
            'canResolve' => true,
            'portal' => 'employer',
        ]);
    }

    private function resolve(int $reviewId, ResolveIdentityReview $resolver, bool $accept): void
    {
        $review = CustomerIdentityReview::query()
            ->where('organization_id', EmployerContext::organizationId())
            ->findOrFail($reviewId);

        try {
            if ($accept) {
                $resolver->accept($review, EmployerContext::organizationId(), auth()->id());
            } else {
                $resolver->dismiss($review, EmployerContext::organizationId(), auth()->id());
            }
        } catch (ValidationException $exception) {
            $this->addError('review', $exception->validator->errors()->first());

            return;
        }

        session()->flash('status', $accept ? 'پیشنهاد ثبت شد.' : 'مورد بدون تغییر داده بسته شد.');
    }
}
