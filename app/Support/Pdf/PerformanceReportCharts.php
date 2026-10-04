<?php

namespace App\Support\Pdf;

class PerformanceReportCharts
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @return array<string, string>
     */
    public static function forTeam(array $dashboard): array
    {
        return array_filter([
            'quality' => ReportChart::line(
                self::points($dashboard['quality_trend'] ?? [], 'avg_score'),
                'امتیاز مکالمه (۰ تا ۱۰۰)',
                'بازه زمانی',
                '#059669',
                0,
                100,
            ),
            'volume' => ReportChart::columns(
                self::points($dashboard['volume_trend'] ?? [], 'count'),
                'تعداد تماس',
                'بازه زمانی',
                '#4f46e5',
            ),
            'lead' => ReportChart::line(
                self::points($dashboard['lead_trend'] ?? [], 'avg_score', emptyScoreIsGap: true),
                'امتیاز لید (۰ تا ۱۰۰)',
                'بازه زمانی',
                '#d97706',
                0,
                100,
            ),
            'sentiment' => ReportChart::stacked(
                self::sentimentRows($dashboard['sentiment_trend'] ?? []),
                [
                    ['key' => 'negative', 'label' => 'منفی', 'color' => '#dc2626'],
                    ['key' => 'mixed', 'label' => 'ترکیبی', 'color' => '#d97706'],
                    ['key' => 'neutral', 'label' => 'خنثی', 'color' => '#a1a1aa'],
                    ['key' => 'positive', 'label' => 'مثبت', 'color' => '#059669'],
                ],
                'تعداد مکالمه',
                'بازه زمانی',
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, string>
     */
    public static function forEmployee(array $profile): array
    {
        return array_filter([
            'quality' => ReportChart::line(
                self::points($profile['quality_trend'] ?? [], 'avg_score'),
                'امتیاز مکالمه (۰ تا ۱۰۰)',
                'بازه زمانی',
                '#059669',
                0,
                100,
            ),
            'volume' => ReportChart::columns(
                self::points($profile['volume_trend'] ?? [], 'count'),
                'تعداد تماس',
                'بازه زمانی',
                '#4f46e5',
            ),
            'lead' => ReportChart::line(
                self::points($profile['lead_trend'] ?? [], 'avg_score', emptyScoreIsGap: true),
                'امتیاز لید (۰ تا ۱۰۰)',
                'بازه زمانی',
                '#d97706',
                0,
                100,
            ),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: float|null}>
     */
    private static function points(array $rows, string $key, bool $emptyScoreIsGap = false): array
    {
        return array_map(function (array $row) use ($key, $emptyScoreIsGap): array {
            $value = $row[$key] ?? null;
            if (! is_numeric($value) || ($emptyScoreIsGap && (float) $value == 0.0)) {
                $value = null;
            }

            return [
                'label' => (string) ($row['label'] ?? ''),
                'value' => $value === null ? null : (float) $value,
            ];
        }, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, values: array<string, int>}>
     */
    private static function sentimentRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? ''),
            'values' => [
                'positive' => (int) ($row['positive'] ?? 0),
                'neutral' => (int) ($row['neutral'] ?? 0),
                'negative' => (int) ($row['negative'] ?? 0),
                'mixed' => (int) ($row['mixed'] ?? 0),
            ],
        ], $rows);
    }
}
