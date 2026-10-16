<?php

namespace App\Http\Middleware;

use App\Domain\Crm\OrganizationCrmMode;
use App\Enums\UserRole;
use App\Services\EmployeeContext;
use App\Services\EmployerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationCrmMode
{
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        $expected = OrganizationCrmMode::tryFrom($mode);

        if ($expected === null) {
            abort(404);
        }

        $user = $request->user();
        $organization = $user?->role === UserRole::Employee
            ? EmployeeContext::organization()
            : EmployerContext::organization();

        if (! $organization->hasConfiguredCrmMode() || $organization->crmMode() !== $expected) {
            abort(404);
        }

        return $next($request);
    }
}
