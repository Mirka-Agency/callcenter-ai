<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employer\Intelligence\Index as IntelligenceIndex;
use App\Livewire\Employer\Intelligence\Performance;
use App\Livewire\Employer\Intelligence\PerformanceShow;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployerDateFilterPresetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_page_shows_all_date_presets_without_more_toggle(): void
    {
        $this->actingAsEmployer();

        $component = Livewire::test(Performance::class)
            ->assertDontSeeHtml("showMore ? 'بستن' : 'بیشتر'")
            ->call('setDatePreset', 'current_year')
            ->assertSet('datePreset', 'current_year');

        $this->assertVisibleDatePresets($component->html());
    }

    public function test_analysis_page_shows_all_date_presets_without_more_toggle(): void
    {
        $this->actingAsEmployer();

        $this->assertVisibleDatePresets(Livewire::test(IntelligenceIndex::class)->html());
    }

    public function test_employee_performance_page_shows_all_date_presets_without_more_toggle(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $this->assertVisibleDatePresets(
            Livewire::test(PerformanceShow::class, ['employee' => $employee])->html(),
        );
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function assertVisibleDatePresets(string $html): void
    {
        $this->assertStringNotContainsString("showMore ? 'بستن' : 'بیشتر'", $html);

        $labels = [
            'امروز',
            'دیروز',
            '۷ روز گذشته',
            '۳۰ روز گذشته',
            'این ماه',
            'ماه قبل',
            'فصل جاری',
            'سال جاری',
            'بازه دلخواه',
        ];

        $previous = 0;

        foreach ($labels as $label) {
            $needle = '>'.$label.'</button>';
            $position = mb_strpos($html, $needle, $previous);

            $this->assertNotFalse($position, "Missing date preset button: {$label}");
            $this->assertGreaterThanOrEqual($previous, $position);

            $previous = $position + mb_strlen($needle);
        }
    }
}
