<?php

namespace App\Livewire\Crm\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\EmployeeContext;
use App\Services\EmployerContext;

trait ResolvesCrmContext
{
    protected function crmUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    protected function crmPortal(): string
    {
        return $this->crmUser()->role === UserRole::Employee ? 'employee' : 'employer';
    }

    protected function crmOrganizationId(): int
    {
        return $this->crmPortal() === 'employee'
            ? EmployeeContext::organizationId()
            : EmployerContext::organizationId();
    }

    protected function crmLayout(): string
    {
        return $this->crmPortal() === 'employee' ? 'layouts.employee' : 'layouts.employer';
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $payload = json_encode(['type' => $type, 'message' => $message], JSON_UNESCAPED_UNICODE);
        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: {$payload} }))");
    }
}
