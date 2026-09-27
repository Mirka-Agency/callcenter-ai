<?php

namespace Tests\Unit;

use App\Application\Llm\Services\AnalysisResponseNormalizer;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Services\Coaching\CoachingAnalysisNormalizer;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CoachingAnalysisNormalizerTest extends TestCase
{
    private CoachingAnalysisNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new CoachingAnalysisNormalizer;
    }

    public function test_missing_coaching_stays_null_without_a_warning(): void
    {
        $messages = $this->coachingLogs();

        $this->assertNull($this->normalizer->normalize(null));
        $this->assertSame([], $messages->getArrayCopy());
    }

    public function test_invalid_coaching_shape_is_dropped_and_logged(): void
    {
        $messages = $this->coachingLogs();

        $this->assertNull($this->normalizer->normalize('not-json'));
        $this->assertContains('coaching_schema_invalid', $messages->getArrayCopy());
    }

    public function test_score_outside_range_is_rejected_without_clamping(): void
    {
        $messages = $this->coachingLogs();

        $result = $this->normalizer->normalize([
            'skills' => [
                ['skill_key' => 'need_discovery', 'score' => 140, 'confidence' => 0.9, 'feedback' => 'متن'],
                ['skill_key' => 'empathy', 'score' => 88, 'confidence' => 0.7, 'feedback' => 'همدلی دیده شد'],
            ],
        ]);

        $this->assertSame(['empathy'], array_column($result['skills'], 'skill_key'));
        $this->assertSame(88.0, $result['skills'][0]['score']);
        $this->assertContains('coaching_skill_rejected', $messages->getArrayCopy());
    }

    private function coachingLogs(): \ArrayObject
    {
        $messages = new \ArrayObject;
        Log::listen(function (MessageLogged $event) use ($messages) {
            if (str_starts_with($event->message, 'coaching_')) {
                $messages->append($event->message);
            }
        });

        return $messages;
    }

    public function test_partial_coaching_keeps_valid_skills_and_drops_unknown_ones(): void
    {
        $result = $this->normalizer->normalize([
            'skills' => [
                [
                    'skill_key' => 'need_discovery',
                    'score' => 54,
                    'status' => 'strength',
                    'confidence' => 0.8,
                    'feedback' => 'پرسش بودجه پرسیده نشد.',
                    'recommendation' => 'پیش از ارائه، درباره بودجه بپرسد.',
                    'evidence' => [[
                        'description' => 'ارائه زود شروع شد',
                        'quote_or_summary' => 'طرح سالانه را توضیح داد',
                        'start_time' => '03:14',
                        'end_time' => '03:42',
                    ]],
                ],
                ['skill_key' => 'invented_skill', 'score' => 10, 'confidence' => 1],
                ['skill_key' => 'closing', 'score' => 40, 'confidence' => 2],
            ],
        ]);

        $this->assertCount(1, $result['skills']);
        $this->assertSame('needs_improvement', $result['skills'][0]['status']);
        $this->assertSame(194, $result['skills'][0]['evidence'][0]['start_time']);
        $this->assertSame(222, $result['skills'][0]['evidence'][0]['end_time']);
    }

    public function test_existing_analysis_survives_invalid_coaching(): void
    {
        $result = (new AnalysisResponseNormalizer)->apply([
            'score' => 76,
            'evaluable' => true,
            'summary' => 'خلاصه سالم مکالمه',
            'sentiment' => 'neutral',
            'strengths' => ['لحن محترمانه'],
            'weaknesses' => ['بستن دیر انجام شد'],
            'coaching_analysis' => ['skills' => 'خراب'],
        ]);

        $this->assertSame(76, $result['score']);
        $this->assertSame('خلاصه سالم مکالمه', $result['summary']);
        $this->assertSame(['لحن محترمانه'], $result['strengths']);
        $this->assertNull($result['coaching_analysis']);
    }

    public function test_historical_response_without_coaching_keeps_the_original_score(): void
    {
        $dto = AnalysisResultData::fromProviderResponse(
            response: [
                'score' => 81,
                'summary' => 'خلاصه قدیمی',
                'sentiment' => 'positive',
                'strengths' => ['شروع خوب'],
            ],
            organizationId: 1,
            organizationUserId: null,
            voipCallLogId: null,
            organizationLlmConnectionId: null,
            llmProvider: 'openai',
            modelName: 'gpt-4o-mini',
            inputTokens: 1,
            outputTokens: 1,
            cost: 0,
            processingDurationMs: 1,
        );

        $this->assertSame(81, $dto->score);
        $this->assertNull($dto->coachingAnalysis);
        $this->assertSame(AnalysisSentiment::Positive, $dto->sentiment);
    }

    public function test_unevaluable_call_clears_coaching(): void
    {
        $result = (new AnalysisResponseNormalizer)->apply([
            'score' => 80,
            'evaluable' => false,
            'summary' => 'مکالمه قابل ارزیابی نبود',
            'coaching_analysis' => [
                'skills' => [
                    ['skill_key' => 'empathy', 'score' => 90, 'confidence' => 0.9, 'feedback' => 'نباید بماند'],
                ],
            ],
        ]);

        $this->assertFalse($result['evaluable']);
        $this->assertSame(0, $result['score']);
        $this->assertNull($result['coaching_analysis']);
    }
}
