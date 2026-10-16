<?php

namespace App\Application\Crm\Invoices;

use App\Enums\UserRole;
use App\Models\CrmInvoice;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class InvoiceAuthorization
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
            throw new AuthorizationException('سازمان یافت نشد.');
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

    public function canView(User $actor, int $organizationId): bool
    {
        return $this->sameTenant($actor, $organizationId)
            && $this->organization($organizationId)->usesInternalCrm();
    }

    public function canManage(User $actor, int $organizationId): bool
    {
        return $this->canView($actor, $organizationId);
    }

    public function canUpdate(User $actor, CrmInvoice $invoice): bool
    {
        return $this->canManage($actor, (int) $invoice->organization_id);
    }

    public function canDelete(User $actor, CrmInvoice $invoice): bool
    {
        return $this->canUpdate($actor, $invoice);
    }

    public function canIssue(User $actor, CrmInvoice $invoice): bool
    {
        return $this->canUpdate($actor, $invoice);
    }

    public function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('مجوز این عملیات را ندارید.');
        }
    }
}
