<?php

namespace Tests\Feature;

use App\Filament\Resources\ConsultationRequests\Pages\ListConsultationRequests;
use App\Filament\Resources\ConsultationRequests\Pages\ViewConsultationRequest;
use App\Filament\Resources\DemoRequests\Pages\ListDemoRequests;
use App\Models\ConsultationRequest;
use App\Models\DemoRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboundRequestArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_consultation_requests_can_be_archived_and_reviewed_from_the_same_page(): void
    {
        $open = $this->consultation('سارا محمدی');
        $archived = $this->consultation('رضا کریمی', archived: true);

        Livewire::test(ListConsultationRequests::class)
            ->assertSuccessful()
            ->assertSee('آرشیوها')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$archived])
            ->callTableAction('archive', $open)
            ->assertCanNotSeeTableRecords([$open]);

        $this->assertNotNull($open->fresh()->archived_at);

        Livewire::test(ListConsultationRequests::class)
            ->callAction('archives')
            ->assertSet('archived', true)
            ->assertSee('آرشیو درخواست‌های مشاوره')
            ->assertSee('درخواست‌های جاری')
            ->assertCanSeeTableRecords([$open->fresh(), $archived])
            ->callTableAction('restore', $archived);

        $this->assertNull($archived->fresh()->archived_at);
    }

    public function test_consultation_request_page_has_an_archive_button(): void
    {
        $open = $this->consultation('سارا محمدی');

        Livewire::test(ViewConsultationRequest::class, ['record' => $open->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('آرشیو')
            ->callAction('archive');

        $this->assertNotNull($open->fresh()->archived_at);
    }

    public function test_demo_requests_can_be_archived_and_reviewed_from_the_same_page(): void
    {
        $open = $this->demo('شرکت روشن');
        $archived = $this->demo('شرکت پنهان', archived: true);

        Livewire::test(ListDemoRequests::class)
            ->assertSuccessful()
            ->assertSee('آرشیوها')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$archived])
            ->callTableAction('archive', $open);

        $this->assertNotNull($open->fresh()->archived_at);

        Livewire::test(ListDemoRequests::class)
            ->callAction('archives')
            ->assertSet('archived', true)
            ->assertSee('آرشیو درخواست‌های دمو')
            ->assertCanSeeTableRecords([$open->fresh(), $archived])
            ->callTableAction('restore', $open->fresh());

        $this->assertNull($open->fresh()->archived_at);
    }

    private function consultation(string $name, bool $archived = false): ConsultationRequest
    {
        return ConsultationRequest::query()->create([
            'full_name' => $name,
            'phone' => '09121234567',
            'company_name' => 'شرکت نمونه',
            'department' => 'فروش',
            'archived_at' => $archived ? now() : null,
        ]);
    }

    private function demo(string $company, bool $archived = false): DemoRequest
    {
        return DemoRequest::query()->create([
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'فروش',
            'company_name' => $company,
            'archived_at' => $archived ? now() : null,
        ]);
    }
}
