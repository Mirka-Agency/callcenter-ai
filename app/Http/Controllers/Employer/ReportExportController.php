<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Support\EmployerReportExporter;
use App\Support\PerformanceReportExporter;
use App\Support\ReportFilterFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    /** @var list<string> */
    private const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function teamPerformance(Request $request, string $format): Response
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $filter = ReportFilterFactory::fromRequest($request);

        return match ($format) {
            'csv' => PerformanceReportExporter::downloadTeamCsv($filter),
            'xlsx' => PerformanceReportExporter::downloadTeamExcel($filter),
            'pdf' => PerformanceReportExporter::downloadTeamPdf($filter),
        };
    }

    public function employeePerformance(Request $request, OrganizationUser $employee, string $format): Response
    {
        abort_unless($employee->organization_id === EmployerContext::organizationId(), 404);
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $filter = ReportFilterFactory::fromRequest($request, $employee->id);

        return match ($format) {
            'csv' => PerformanceReportExporter::downloadEmployeeCsv($filter, $employee),
            'xlsx' => PerformanceReportExporter::downloadEmployeeExcel($filter, $employee),
            'pdf' => PerformanceReportExporter::downloadEmployeePdf($filter, $employee),
        };
    }

    public function reports(Request $request, string $format): Response
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $filter = ReportFilterFactory::fromRequest($request);

        return match ($format) {
            'csv' => EmployerReportExporter::downloadCsv($filter),
            'xlsx' => EmployerReportExporter::downloadExcel($filter),
            'pdf' => EmployerReportExporter::downloadPdf($filter),
        };
    }
}
