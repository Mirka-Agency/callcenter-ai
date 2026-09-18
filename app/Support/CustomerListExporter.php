<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerListExporter
{
    /** @var list<string> */
    public const FORMATS = ['xlsx', 'pdf'];

    public static function download(
        string $entity,
        int $organizationId,
        string $format,
        string $search = '',
        string $sort = 'last_contact',
    ): StreamedResponse {
        abort_unless(in_array($entity, ['contacts', 'companies'], true), 404);
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $title = $entity === 'contacts' ? 'لیست اشخاص' : 'لیست شرکت‌ها';
        $headers = $entity === 'contacts' ? self::contactHeaders() : self::companyHeaders();
        $rows = $entity === 'contacts'
            ? self::contactRows($organizationId, $search, $sort)
            : self::companyRows($organizationId, $search, $sort);

        return $format === 'xlsx'
            ? self::excel($entity, $headers, $rows)
            : self::pdf($entity, $title, $headers, $rows);
    }

    /** @return list<string> */
    private static function contactHeaders(): array
    {
        return [
            'نام',
            'شرکت',
            'سمت',
            'تلفن',
            'ایمیل',
            'تعداد تماس',
            'آخرین تماس',
            'سطح لید',
            'امتیاز لید',
            'تمایل خرید',
            'روند مکالمه',
            'اقدام بعدی',
        ];
    }

    /** @return list<string> */
    private static function companyHeaders(): array
    {
        return [
            'نام',
            'صنعت',
            'تلفن',
            'ایمیل',
            'وب‌سایت',
            'تعداد اشخاص',
            'تعداد تماس',
            'آخرین تماس',
            'سطح لید',
            'امتیاز لید',
            'روند مکالمه',
            'اقدام بعدی',
        ];
    }

    /** @return list<list<string|int|float|null>> */
    private static function contactRows(int $organizationId, string $search, string $sort): array
    {
        return CustomerListQuery::contacts($organizationId, $search, $sort)
            ->get()
            ->map(fn ($customer) => [
                $customer->displayName(),
                $customer->companyLabel() ?: '—',
                $customer->job_title ?: '—',
                $customer->phone_number ?: '—',
                $customer->email ?: '—',
                (int) $customer->total_calls,
                shamsi($customer->last_contact_at),
                AnalysisInsightPresenter::leadLevelLabel($customer->latest_lead_level),
                $customer->latest_lead_score ?: '—',
                $customer->purchase_intent ?: '—',
                CustomerPresenter::trendLabel($customer->conversation_trend),
                $customer->recommended_next_action ?: '—',
            ])
            ->all();
    }

    /** @return list<list<string|int|float|null>> */
    private static function companyRows(int $organizationId, string $search, string $sort): array
    {
        return CustomerListQuery::companies($organizationId, $search, $sort)
            ->get()
            ->map(fn ($company) => [
                $company->displayName(),
                $company->industry ?: '—',
                $company->phone ?: '—',
                $company->email ?: '—',
                $company->website ?: '—',
                (int) $company->contacts_count,
                (int) $company->total_calls,
                shamsi($company->last_contact_at),
                AnalysisInsightPresenter::leadLevelLabel($company->latest_lead_level),
                $company->latest_lead_score ?: '—',
                CustomerCompanyPresenter::trendLabel($company->conversation_trend),
                $company->recommended_next_action ?: '—',
            ])
            ->all();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function excel(string $entity, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $writer = new XlsxWriter;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(Collection::make($row)->map(fn ($value) => $value ?? '')->all()));
            }

            $writer->close();
        }, self::filename($entity, 'xlsx'));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function pdf(string $entity, string $title, array $headers, array $rows): StreamedResponse
    {
        $html = View::make('exports.customer-list-pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
        ])->render();

        return response()->streamDownload(
            fn () => print ($html),
            self::filename($entity, 'html'),
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private static function filename(string $entity, string $extension): string
    {
        return sprintf('%s-%s.%s', $entity, now()->format('Y-m-d'), $extension);
    }
}
