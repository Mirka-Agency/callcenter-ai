<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\EmployeeDashboardAnalytics;
use App\Services\Performance\Calculators\JsonFieldAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsonFieldAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranked_improvement_areas_falls_back_to_dimensions_when_weaknesses_are_empty(): void
    {
        $employee = $this->seedEmployee();

        ConversationAnalysis::query()->create([
            'organization_id' => $employee->organization_id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 88,
            'summary' => 'خلاصه تست',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => ['گوش دادن فعال'],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'concerns_json' => [[
                'type' => 'timing',
                'text' => 'نیاز به زمان برای تأیید مدیریت',
                'severity' => 'low',
            ]],
            'performance_dimensions_json' => [
                'communication_skills' => ['score' => 90],
                'product_knowledge' => ['score' => 82],
                'objection_handling' => ['score' => 88],
                'closing_ability' => ['score' => 71],
                'professionalism' => ['score' => 92],
            ],
            'operational_insights_json' => [
                'missed_opportunities' => ['فرصت پیشنهاد بسته مکمل از دست رفت'],
            ],
            'analyzed_at' => now(),
        ]);

        $result = app(JsonFieldAggregator::class)->rankedImprovementAreas(
            ConversationAnalysis::query()->where('organization_user_id', $employee->id)->get(),
            5,
        );

        $this->assertTrue($result['derived']);
        $this->assertNotEmpty($result['items']);
        $this->assertContains(
            'نیاز به زمان برای تأیید مدیریت',
            collect($result['items'])->pluck('item')->all(),
        );
    }

    public function test_dashboard_shows_derived_improvement_areas_for_high_scoring_employee(): void
    {
        $employee = $this->seedEmployee();

        ConversationAnalysis::query()->create([
            'organization_id' => $employee->organization_id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 90,
            'summary' => 'خلاصه تست',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => ['ارتباط مؤثر'],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'performance_dimensions_json' => [
                'communication_skills' => ['score' => 92],
                'product_knowledge' => ['score' => 84],
                'objection_handling' => ['score' => 88],
                'closing_ability' => ['score' => 74],
                'professionalism' => ['score' => 91],
            ],
            'analyzed_at' => now(),
        ]);

        $analytics = EmployeeDashboardAnalytics::forEmployee($employee);
        $areas = $analytics->topImprovementAreas();

        $this->assertTrue($areas['derived']);
        $this->assertNotEmpty($areas['items']);

        $recommendations = $analytics->recommendations();

        $this->assertNotEmpty($recommendations);
        $this->assertArrayHasKey('topic', $recommendations[0]);
        $this->assertArrayHasKey('tip', $recommendations[0]);
    }

    private function seedEmployee(): OrganizationUser
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'is_active' => true,
        ]);
    }
}
