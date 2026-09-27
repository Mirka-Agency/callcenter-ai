<?php

namespace Tests\Unit;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Models\ConversationAnalysis;
use App\Services\Coaching\AgentCoachingAggregator;
use Carbon\Carbon;
use Tests\TestCase;

class AgentCoachingAggregatorTest extends TestCase
{
    public function test_missing_skill_scores_are_ignored_and_confidence_weights_the_average(): void
    {
        $analyses = collect([
            $this->analysis(1, [
                $this->skill('need_discovery', 40, 0.2),
                $this->skill('empathy', 50, 1),
            ], '2026-06-02'),
            $this->analysis(2, [
                $this->skill('need_discovery', 80, 0.8),
            ], '2026-06-09'),
            $this->analysis(3, [$this->skill('empathy', 70, 1)], '2026-06-16'),
            $this->analysis(4, [$this->skill('empathy', 70, 1)], '2026-06-23'),
            $this->analysis(5, [
                $this->skill('need_discovery', 10, 0),
                $this->skill('empathy', 70, 1),
            ], '2026-06-30'),
        ]);

        $result = app(AgentCoachingAggregator::class)->aggregate($this->filter(), $analyses, collect());
        $scores = collect($result['skills'])->keyBy('skill_key');

        $this->assertSame('ready', $result['status']);
        $this->assertSame(72.0, $scores['need_discovery']['score']);
        $this->assertSame(65.0, $scores['empathy']['score']);
        $this->assertSame(68.5, $result['overall_score']);
        $this->assertNull($result['score_change']);
    }

    public function test_fewer_than_minimum_calls_hides_conclusions(): void
    {
        $result = app(AgentCoachingAggregator::class)->aggregate(
            $this->filter(),
            collect([
                $this->analysis(1, [$this->skill('closing', 40, 1)], '2026-06-02'),
                $this->analysis(2, [$this->skill('closing', 42, 1)], '2026-06-09'),
                $this->analysis(3, [$this->skill('closing', 44, 1)], '2026-06-16'),
            ]),
            collect(),
        );

        $this->assertSame('insufficient', $result['status']);
        $this->assertSame(3, $result['analyzed_calls']);
        $this->assertSame(5, $result['minimum_calls']);
        $this->assertSame([], $result['skills']);
        $this->assertSame('اطلاعات کافی برای ارزیابی مهارت این کارشناس وجود ندارد.', $result['title']);
        $this->assertSame('3 از حداقل 5 تماس مورد نیاز تحلیل شده است.', $result['description']);
    }

    public function test_trend_and_previous_period_change_are_calculated_from_scores(): void
    {
        $current = collect([
            $this->analysis(1, [$this->skill('need_discovery', 52, null, 'پرسش بودجه دیر پرسیده شد.', 'قبل از ارائه دو پرسش کشف نیاز بپرسد.')], '2026-06-02', evidence: true),
            $this->analysis(2, [$this->skill('need_discovery', 57, null)], '2026-06-09'),
            $this->analysis(3, [$this->skill('need_discovery', 61, null)], '2026-06-16'),
            $this->analysis(4, [$this->skill('need_discovery', 68, null)], '2026-06-23'),
            $this->analysis(5, [$this->skill('need_discovery', 70, null)], '2026-06-30'),
        ]);
        $previous = collect([
            $this->analysis(11, [$this->skill('need_discovery', 40, null)], '2026-04-06'),
            $this->analysis(12, [$this->skill('need_discovery', 40, null)], '2026-04-13'),
            $this->analysis(13, [$this->skill('need_discovery', 40, null)], '2026-04-20'),
            $this->analysis(14, [$this->skill('need_discovery', 40, null)], '2026-04-27'),
            $this->analysis(15, [$this->skill('need_discovery', 40, null)], '2026-05-04'),
        ]);

        $result = app(AgentCoachingAggregator::class)->aggregate($this->filter(), $current, $previous);

        $this->assertSame(61.6, $result['overall_score']);
        $this->assertSame(40.0, $result['previous_period_score']);
        $this->assertSame(21.6, $result['score_change']);
        $this->assertTrue($result['trend']['has_data']);
        $this->assertGreaterThanOrEqual(2, count(array_filter(
            $result['trend']['datasets'][0]['data'],
            fn ($value) => $value !== null,
        )));
        $this->assertSame('high', $result['areas_for_improvement'][0]['severity']);
        $this->assertSame('قبل از ارائه دو پرسش کشف نیاز بپرسد.', $result['recommendations'][0]['suggested_action']);
        $this->assertSame('تماس #101', $result['evidence'][0]['call_label']);
        $this->assertSame('03:14 تا 03:42', $result['evidence'][0]['time_label']);
    }

    public function test_calls_without_coaching_do_not_lower_the_score(): void
    {
        $analyses = collect([
            $this->analysis(1, [$this->skill('product_knowledge', 90, 1)], '2026-06-02'),
            $this->analysis(2, [$this->skill('product_knowledge', 90, 1)], '2026-06-09'),
            $this->analysis(3, [$this->skill('product_knowledge', 90, 1)], '2026-06-16'),
            $this->analysis(4, [$this->skill('product_knowledge', 90, 1)], '2026-06-23'),
            $this->analysis(5, [$this->skill('product_knowledge', 90, 1)], '2026-06-30'),
            $this->analysis(6, [], '2026-07-07', score: 20),
        ]);

        $result = app(AgentCoachingAggregator::class)->aggregate($this->filter(), $analyses, collect());

        $this->assertSame(90.0, $result['overall_score']);
        $this->assertSame(5, $result['analyzed_calls']);
        $this->assertSame('strength', $result['skills'][0]['status']);
    }

    /** @param  list<array<string, mixed>>  $skills */
    private function analysis(
        int $id,
        array $skills,
        string $day,
        int $score = 80,
        bool $evidence = false,
    ): ConversationAnalysis {
        if ($evidence && isset($skills[0])) {
            $skills[0]['evidence'] = [[
                'description' => 'ارائه پیش از کشف نیاز شروع شد',
                'quote_or_summary' => 'طرح را همان ابتدا توضیح داد',
                'start_time' => 194,
                'end_time' => 222,
            ]];
        }

        $analysis = new ConversationAnalysis;
        $analysis->id = $id;
        $analysis->call_id = 100 + $id;
        $analysis->score = $score;
        $analysis->is_evaluable = $score > 0;
        $analysis->analyzed_at = Carbon::parse($day.' 10:00:00');
        $analysis->coaching_analysis_json = $skills === [] ? null : ['skills' => $skills];
        $analysis->setRelation('call', null);

        return $analysis;
    }

    /** @return array<string, mixed> */
    private function skill(string $key, float $score, ?float $confidence, string $feedback = '', string $recommendation = ''): array
    {
        return [
            'skill_key' => $key,
            'score' => $score,
            'confidence' => $confidence,
            'feedback' => $feedback,
            'recommendation' => $recommendation,
            'evidence' => [],
        ];
    }

    private function filter(): ReportFilter
    {
        return new ReportFilter(
            organizationId: 1,
            preset: ReportDatePreset::Custom,
            from: Carbon::parse('2026-06-01')->startOfDay(),
            to: Carbon::parse('2026-09-15')->endOfDay(),
        );
    }
}
