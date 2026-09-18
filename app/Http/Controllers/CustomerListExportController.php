<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\EmployeeContext;
use App\Services\EmployerContext;
use App\Support\CustomerListExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerListExportController extends Controller
{
    public function contacts(Request $request, string $format): StreamedResponse
    {
        return CustomerListExporter::download(
            'contacts',
            $this->organizationId(),
            $format,
            (string) $request->query('search', ''),
            (string) $request->query('sort', 'last_contact'),
        );
    }

    public function companies(Request $request, string $format): StreamedResponse
    {
        return CustomerListExporter::download(
            'companies',
            $this->organizationId(),
            $format,
            (string) $request->query('search', ''),
            (string) $request->query('sort', 'last_contact'),
        );
    }

    private function organizationId(): int
    {
        return match (auth()->user()?->role) {
            UserRole::Employer => EmployerContext::organizationId(),
            UserRole::Employee => EmployeeContext::organizationId(),
            default => abort(403),
        };
    }
}
