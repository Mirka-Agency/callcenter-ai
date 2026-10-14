<?php

namespace App\Application\Crm\Deals;

use App\Enums\UserRole;
use App\Models\CrmDeal;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DealAuthorization
{
    public function isManager(User $actor): bool
    {
        return $actor->role === UserRole::Employer;
    }

    public function membershipId(User $actor, int $organizationId): ?int
    {
        $id = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function sameTenant(User $actor, int $organizationId): bool
    {
        if ($this->isManager($actor)) {
            return (int) $actor->primaryOrganization()?->id === $organizationId;
        }

        if ($actor->role === UserRole::Employee) {
            return $this->membershipId($actor, $organizationId) !== null;
        }

        return false;
    }

    public function organization(int $organizationId): Organization
    {
        $organization = Organization::query()->find($organizationId);

        if (! $organization instanceof Organization) {
            throw new AuthorizationException('سازمان معامله یافت نشد.');
        }

        return $organization;
    }

    public function assertInternalCrm(int $organizationId): void
    {
        $organization = $this->organization($organizationId);

        if (! $organization->hasConfiguredCrmMode()) {
            throw new AuthorizationException('حالت CRM این سازمان تنظیم نشده است.');
        }

        if (! $organization->usesInternalCrm()) {
            throw new AuthorizationException('این سازمان از CRM داخلی آوایار استفاده نمی‌کند.');
        }
    }

    public function canManagePipelines(User $actor, int $organizationId): bool
    {
        return $this->isManager($actor)
            && $this->sameTenant($actor, $organizationId)
            && $this->organization($organizationId)->usesInternalCrm();
    }

    public function canViewAllDeals(User $actor, int $organizationId): bool
    {
        return $this->canManagePipelines($actor, $organizationId);
    }

    public function canCreate(User $actor, int $organizationId): bool
    {
        return $this->sameTenant($actor, $organizationId)
            && $this->organization($organizationId)->usesInternalCrm();
    }

    public function canView(User $actor, CrmDeal $deal): bool
    {
        if (! $this->organization((int) $deal->organization_id)->usesInternalCrm()) {
            return false;
        }

        if (! $this->sameTenant($actor, (int) $deal->organization_id)) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        return (int) $deal->owner_organization_user_id === (int) $this->membershipId($actor, (int) $deal->organization_id);
    }

    public function canUpdate(User $actor, CrmDeal $deal): bool
    {
        return $this->canView($actor, $deal);
    }

    public function canChangeStage(User $actor, CrmDeal $deal): bool
    {
        return $this->canUpdate($actor, $deal);
    }

    public function canClose(User $actor, CrmDeal $deal): bool
    {
        return $this->canUpdate($actor, $deal);
    }

    public function canAssignOwner(User $actor, CrmDeal $deal): bool
    {
        return $this->isManager($actor) && $this->canView($actor, $deal);
    }

    public function canReopen(User $actor, CrmDeal $deal): bool
    {
        return $this->isManager($actor) && $this->canView($actor, $deal);
    }

    public function canArchive(User $actor, CrmDeal $deal): bool
    {
        return $this->canUpdate($actor, $deal);
    }

    public function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('مجوز این عملیات را ندارید.');
        }
    }
}
