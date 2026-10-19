<?php

namespace Tests\Feature;

use App\Filament\Resources\LandingCalculatorModels\Pages\CreateLandingCalculatorModel;
use App\Filament\Resources\LandingCalculatorModels\Pages\EditLandingCalculatorModel;
use App\Models\LandingCalculatorModel;
use App\Models\User;
use App\Services\LandingCostCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingCalculatorModelAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sets_a_per_minute_price_used_by_the_landing_calculator(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateLandingCalculatorModel::class)
            ->fillForm([
                'name' => 'Gemini',
                'cost_per_minute_tomans' => '640',
                'position' => '1',
                'is_default' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $model = LandingCalculatorModel::query()->where('name', 'Gemini')->first();

        $this->assertNotNull($model);
        $this->assertSame(640, $model->cost_per_minute_tomans);
        $this->assertTrue($model->is_default);

        $quote = app(LandingCostCalculator::class)->present();

        $this->assertSame($model->id, $quote['selectedId']);
        $this->assertSame(12_800_000.0, $quote['total']);
    }

    public function test_marking_a_model_as_default_clears_the_previous_default(): void
    {
        $first = LandingCalculatorModel::query()->create([
            'name' => 'اول',
            'cost_per_minute_tomans' => 100,
            'is_default' => true,
            'is_active' => true,
        ]);
        $second = LandingCalculatorModel::query()->create([
            'name' => 'دوم',
            'cost_per_minute_tomans' => 200,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditLandingCalculatorModel::class, ['record' => $second->id])
            ->fillForm(['is_default' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_employer_cannot_open_the_calculator_tariff_page(): void
    {
        $this->actingAs(User::factory()->employer()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateLandingCalculatorModel::class)
            ->assertForbidden();
    }
}
