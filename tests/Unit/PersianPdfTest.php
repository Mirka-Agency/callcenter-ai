<?php

namespace Tests\Unit;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Models\OrganizationUser;
use App\Support\Pdf\PerformanceReportCharts;
use App\Support\Pdf\PersianPdf;
use App\Support\Pdf\ReportChart;
use Tests\TestCase;

class PersianPdfTest extends TestCase
{
    public function test_renders_a_pdf_with_an_embedded_chart(): void
    {
        $binary = PersianPdf::render(
            '<h1>گزارش عملکرد کارشناسان</h1><img src="var:quality" style="width:100%">',
            ['quality' => ReportChart::line([
                ['label' => '۱ مهر', 'value' => 72.5],
                ['label' => '۲ مهر', 'value' => 80],
                ['label' => '۳ مهر', 'value' => null],
                ['label' => '۴ مهر', 'value' => 68],
            ], 'امتیاز مکالمه (۰ تا ۱۰۰)', 'بازه زمانی', '#059669', 0, 100)],
            'A4-L',
            'گزارش عملکرد کارشناسان',
        );

        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertGreaterThan(2000, strlen($binary));
    }

    public function test_team_report_includes_dashboard_sections_and_charts(): void
    {
        $filter = ReportFilter::make(1, ReportDatePreset::Last30);
        $dashboard = [
            'kpis' => [
                'active_employees' => 2,
                'total_employees' => 3,
                'total_calls' => 12,
                'total_analyzed' => 8,
                'average_quality_score' => 78.4,
                'average_lead_score' => 64.0,
                'average_sentiment' => 71.0,
                'quality_sample_count' => 8,
                'lead_sample_count' => 6,
                'sentiment_sample_count' => 8,
            ],
            'kpis_delta' => [
                'total_calls' => 10.0,
                'total_analyzed' => -5.0,
                'average_quality_score' => 4.2,
                'average_lead_score' => 0.0,
                'average_sentiment' => 3.1,
            ],
            'executive_summary' => 'تیم در این بازه عملکرد پایدار داشته است.',
            'progress_insights' => ['امتیاز کیفیت مکالمه تیم ۴٫۲٪ نسبت به دوره قبل بهبود یافته است.'],
            'quality_trend' => [
                ['label' => '۱ مهر', 'avg_score' => 70.0],
                ['label' => '۲ مهر', 'avg_score' => 82.0],
            ],
            'volume_trend' => [
                ['label' => '۱ مهر', 'count' => 4],
                ['label' => '۲ مهر', 'count' => 8],
            ],
            'lead_trend' => [
                ['label' => '۱ مهر', 'avg_score' => 60.0],
                ['label' => '۲ مهر', 'avg_score' => 68.0],
            ],
            'sentiment_trend' => [
                ['label' => '۱ مهر', 'positive' => 2, 'neutral' => 1, 'negative' => 0, 'mixed' => 1],
            ],
            'quality_trend_insights' => [
                'a' => [
                    'direction' => 'up',
                    'headline' => 'افزایش کیفیت',
                    'label' => '۲ مهر',
                    'reason' => 'روند کیفیت در ۲ مهر افزایش داشت.',
                    'current_score' => 82.0,
                    'score_delta' => 12.0,
                    'analyzed_count' => 3,
                    'agents' => [['name' => 'نگار رضایی']],
                ],
            ],
            'quality_distribution' => [
                ['label' => 'عالی — ۸۰ به بالا', 'count' => 3],
                ['label' => 'خوب — ۶۰ تا ۷۹', 'count' => 5],
            ],
            'lead_distribution' => ['high' => 2, 'medium' => 3, 'low' => 1, 'total' => 6, 'average_score' => 64.0],
            'rankings' => [
                'best_quality' => [['name' => 'نگار رضایی', 'average_score' => 88]],
                'most_improved' => [['name' => 'نگار رضایی', 'improvement_percent' => 12.5]],
                'most_calls' => [['name' => 'سامان کریمی', 'total_calls' => 9]],
                'best_lead' => [['name' => 'نگار رضایی', 'average_lead_score' => 80]],
                'best_sentiment' => [['name' => 'سامان کریمی', 'average_sentiment' => 90]],
            ],
            'attention_employees' => [[
                'name' => 'سامان کریمی',
                'department' => 'فروش',
                'average_score' => 61,
                'total_analyzed' => 4,
                'weakness_rate' => 0.75,
                'repeated_weaknesses' => [['item' => 'قطع کردن مشتری', 'count' => 3]],
            ]],
            'team_weaknesses' => [
                ['item' => 'قطع کردن مشتری', 'count' => 4, 'trend' => 25],
            ],
            'employees' => [[
                'rank' => 1,
                'name' => 'نگار رضایی',
                'tier' => 'top',
                'department' => 'فروش',
                'total_calls' => 6,
                'total_analyzed' => 4,
                'answer_rate' => 80,
                'average_duration_label' => '۳:۲۰',
                'average_score' => 88,
                'improvement_percent' => 12.5,
                'average_lead_score' => 80,
                'average_sentiment' => 75,
                'effectiveness_score' => 81,
                'trend' => 'improving',
                'top_strength' => 'لحن محترمانه',
                'top_weakness' => null,
            ]],
        ];
        $charts = PerformanceReportCharts::forTeam($dashboard);

        $html = view('exports.performance-team-pdf', [
            'filter' => $filter,
            'dashboard' => $dashboard,
            'charts' => $charts,
            'organizationTitle' => 'کلینیک نمونه',
        ])->render();

        $this->assertStringContainsString('نگار رضایی', $html);
        $this->assertStringContainsString('قطع کردن مشتری', $html);
        foreach (['quality', 'volume', 'lead', 'sentiment'] as $chart) {
            $this->assertMatchesRegularExpression(
                '/<div class="keep">(?:(?!<\\/div>).)*var:'.$chart.'/s',
                $html,
            );
        }

        $binary = PersianPdf::render($html, $charts, 'A4-L', 'گزارش عملکرد کارشناسان');
        $this->assertStringStartsWith('%PDF', $binary);
    }

    public function test_employee_report_template_renders(): void
    {
        $employee = new OrganizationUser([
            'first_name' => 'نگار',
            'last_name' => 'رضایی',
            'position' => 'کارشناس فروش',
            'department' => 'فروش',
        ]);
        $filter = ReportFilter::make(1, ReportDatePreset::Last7);
        $profile = [
            'metrics' => [
                'total_calls' => 4,
                'total_analyzed' => 3,
                'average_quality_score' => 81,
                'average_lead_score' => 70,
                'average_sentiment' => 66,
                'effectiveness_score' => 74,
                'answered_calls' => 3,
                'missed_calls' => 1,
                'average_duration_label' => '۴:۱۰',
            ],
            'metrics_delta' => [
                'quality_improvement_percent' => 6.0,
                'lead_improvement_percent' => -2.0,
                'sentiment_improvement_percent' => null,
            ],
            'executive_summary' => 'نگار در این هفته کیفیت مکالمه بهتری داشته است.',
            'progress_insights' => [],
            'quality_trend' => [['label' => 'شنبه', 'avg_score' => 81]],
            'volume_trend' => [['label' => 'شنبه', 'count' => 2]],
            'lead_trend' => [['label' => 'شنبه', 'avg_score' => 70]],
            'dimension_averages' => ['communication_skills' => 86],
            'strengths' => ['لحن آرام'],
            'weaknesses' => ['پیگیری دیرهنگام'],
            'coaching' => ['coaching_plan' => ['تمرکز بر پیگیری و بستن تماس']],
            'recent_calls' => [[
                'date' => '۱۴۰۵/۰۷/۱۱',
                'customer' => 'مشتری نمونه',
                'duration_label' => '۲:۰۰',
                'quality_score' => 80,
                'lead_score' => 70,
                'sentiment' => 'مثبت',
                'summary' => 'مشتری برای تمدید اشتراک تماس گرفت.',
            ]],
        ];
        $charts = PerformanceReportCharts::forEmployee($profile);
        $html = view('exports.performance-employee-pdf', compact('filter', 'profile', 'employee', 'charts'))->render();

        $this->assertStringContainsString('نگار رضایی', $html);
        $this->assertStringContainsString('مهارت ارتباطی', $html);
        foreach (['quality', 'volume', 'lead'] as $chart) {
            $this->assertMatchesRegularExpression(
                '/<div class="keep">(?:(?!<\\/div>).)*var:'.$chart.'/s',
                $html,
            );
        }
        $this->assertStringStartsWith('%PDF', PersianPdf::render($html, $charts, 'A4', 'گزارش عملکرد نگار رضایی'));
    }
}
