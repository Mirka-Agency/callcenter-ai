<?php

namespace App\Support;

use App\DTOs\ReportFilter;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Support\Pdf\PerformanceReportCharts;
use App\Support\Pdf\PersianPdf;
use Illuminate\Support\Facades\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceReportExporter
{
    public static function downloadTeamCsv(ReportFilter $filter): StreamedResponse
    {
        return self::stream($filter, 'team', 'csv', self::teamHeaders(), self::teamRows($filter));
    }

    public static function downloadTeamExcel(ReportFilter $filter): StreamedResponse
    {
        return self::stream($filter, 'team', 'xlsx', self::teamHeaders(), self::teamRows($filter), true);
    }

    public static function downloadTeamPdf(ReportFilter $filter): StreamedResponse
    {
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);
        $charts = PerformanceReportCharts::forTeam($dashboard);
        $html = View::make('exports.performance-team-pdf', [
            'filter' => $filter,
            'dashboard' => $dashboard,
            'charts' => $charts,
            'organizationTitle' => Organization::query()->find($filter->organizationId)?->title,
        ])->render();

        return self::pdfResponse(
            PersianPdf::render($html, $charts, 'A4-L', 'گزارش عملکرد کارشناسان'),
            self::filename($filter, 'team', 'pdf'),
        );
    }

    public static function downloadEmployeeCsv(ReportFilter $filter, OrganizationUser $employee): StreamedResponse
    {
        return self::stream($filter, 'employee-'.$employee->id, 'csv', self::employeeHeaders(), self::employeeRows($filter, $employee));
    }

    public static function downloadEmployeeExcel(ReportFilter $filter, OrganizationUser $employee): StreamedResponse
    {
        return self::stream($filter, 'employee-'.$employee->id, 'xlsx', self::employeeHeaders(), self::employeeRows($filter, $employee), true);
    }

    public static function downloadEmployeePdf(ReportFilter $filter, OrganizationUser $employee): StreamedResponse
    {
        $analytics = app(EmployeePerformanceAnalytics::class);
        $profile = $analytics->employeeProfile($filter, $employee);

        $charts = PerformanceReportCharts::forEmployee($profile);
        $html = View::make('exports.performance-employee-pdf', [
            'filter' => $filter,
            'profile' => $profile,
            'employee' => $employee,
            'charts' => $charts,
        ])->render();

        return self::pdfResponse(
            PersianPdf::render($html, $charts, 'A4', 'گزارش عملکرد '.$employee->full_name),
            self::filename($filter, 'employee-'.$employee->id, 'pdf'),
        );
    }

    private static function pdfResponse(string $binary, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($binary),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @param  list<string>  $headers  */
    /** @param  list<list<string|int|float|null>>  $rows  */
    private static function stream(
        ReportFilter $filter,
        string $scope,
        string $extension,
        array $headers,
        array $rows,
        bool $excel = false,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($headers, $rows, $excel): void {
            $writer = $excel ? new XlsxWriter : new CsvWriter;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, self::filename($filter, $scope, $extension));
    }

    /** @return list<string> */
    private static function teamHeaders(): array
    {
        return ['کارشناس', 'بخش', 'تماس‌های برقرارشده', 'مکالمات تحلیل‌شده', 'امتیاز مکالمه', 'امتیاز لید فروش', 'شاخص رضایت مشتری'];
    }

    /** @return list<list<string|int|float|null>> */
    private static function teamRows(ReportFilter $filter): array
    {
        return app(EmployeePerformanceAnalytics::class)->exportTeamRows($filter);
    }

    /** @return list<string> */
    private static function employeeHeaders(): array
    {
        return ['تاریخ تماس', 'مشتری', 'مدت مکالمه', 'امتیاز مکالمه', 'امتیاز لید', 'احساس مشتری', 'خلاصه مکالمه'];
    }

    /** @return list<list<string|int|float|null>> */
    private static function employeeRows(ReportFilter $filter, OrganizationUser $employee): array
    {
        return app(EmployeePerformanceAnalytics::class)->exportEmployeeRows($filter, $employee);
    }

    private static function filename(ReportFilter $filter, string $scope, string $extension): string
    {
        return sprintf(
            'performance-%s-%s-%s.%s',
            $scope,
            $filter->from->format('Ymd'),
            $filter->to->format('Ymd'),
            $extension,
        );
    }
}
