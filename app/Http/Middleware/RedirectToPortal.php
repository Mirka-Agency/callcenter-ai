<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($request->user()->role->canAccessAdminPanel() && $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        return match ($request->user()->role) {
            UserRole::Employer => redirect()->route('employer.dashboard'),
            UserRole::Employee => redirect()->route('employee.dashboard'),
            UserRole::SuperAdmin, UserRole::Admin => redirect('/admin'),
            UserRole::Writer => redirect($request->user()->role->homeRoute()),
            default => $next($request),
        };
    }
}
