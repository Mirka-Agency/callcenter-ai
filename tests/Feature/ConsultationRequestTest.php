<?php

namespace Tests\Feature;

use App\Models\ConsultationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_opens_consultation_form_instead_of_demo_login(): void
    {
        $this->get('http://avayar.org/')
            ->assertOk()
            ->assertSee('درخواست مشاوره')
            ->assertDontSee('درخواست دمو')
            ->assertSee('نام و نام خانوادگی')
            ->assertSee('شماره تماس')
            ->assertSee('نام شرکت')
            ->assertSee('واحدی که نیاز به آوایار دارد')
            ->assertSee('توضیحات')
            ->assertSee(route('consultation-requests.store'), false);
    }

    public function test_consultation_request_is_stored(): void
    {
        Http::fake();

        $this->postJson('http://avayar.org/consultation-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '0912 123 4567',
            'company_name' => 'شرکت نمونه',
            'department' => 'فروش',
            'notes' => 'برای تیم فروش نیاز به تحلیل تماس داریم.',
        ])->assertOk();

        $this->assertDatabaseHas('consultation_requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'company_name' => 'شرکت نمونه',
            'department' => 'فروش',
            'notes' => 'برای تیم فروش نیاز به تحلیل تماس داریم.',
        ]);

        Http::assertNothingSent();
    }

    public function test_consultation_request_sends_sms_to_applicant_and_owner(): void
    {
        config([
            'services.smsir.api_key' => 'test-key',
            'services.smsir.line_number' => '3000123456',
            'services.smsir.notify_mobile' => '09351234567',
            'services.smsir.base_url' => 'https://api.sms.ir/v1',
        ]);

        Http::fake([
            'https://api.sms.ir/v1/send/bulk' => Http::response([
                'status' => 1,
                'message' => 'موفق',
                'data' => ['packId' => '1'],
            ]),
        ]);

        $this->postJson('http://avayar.org/consultation-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'company_name' => 'شرکت نمونه',
            'department' => 'فروش',
        ])->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['mobiles'] === ['09121234567']
            && str_contains($request['messageText'], 'درخواست مشاوره شما ثبت شد')
            && $request->hasHeader('X-API-KEY', 'test-key')
            && $request['lineNumber'] === 3000123456);
        Http::assertSent(fn ($request) => $request['mobiles'] === ['09351234567']
            && str_contains($request['messageText'], 'سارا محمدی')
            && str_contains($request['messageText'], '09121234567'));
    }

    public function test_consultation_request_is_stored_when_sms_fails(): void
    {
        config([
            'services.smsir.api_key' => 'test-key',
            'services.smsir.line_number' => '3000123456',
            'services.smsir.notify_mobile' => '09351234567',
        ]);

        Http::fake([
            'https://api.sms.ir/*' => Http::response(['status' => 0, 'message' => 'خطا'], 400),
        ]);

        $this->postJson('http://avayar.org/consultation-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'company_name' => 'شرکت نمونه',
            'department' => 'فروش',
        ])->assertOk();

        $this->assertDatabaseHas('consultation_requests', [
            'phone' => '09121234567',
        ]);
    }

    public function test_consultation_request_requires_contact_fields(): void
    {
        $this->postJson('http://avayar.org/consultation-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'phone', 'company_name', 'department']);

        $this->assertSame(0, ConsultationRequest::query()->count());
    }

    public function test_honeypot_submissions_are_ignored(): void
    {
        $this->postJson('http://avayar.org/consultation-requests', [
            'full_name' => 'ربات',
            'phone' => '09120000000',
            'company_name' => 'اسپم',
            'department' => 'فروش',
            'website' => 'https://spam.example',
        ])->assertOk();

        $this->assertSame(0, ConsultationRequest::query()->count());
    }

    public function test_consultation_form_is_rejected_off_the_public_domain(): void
    {
        $this->postJson('http://app.example/consultation-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'company_name' => 'شرکت نمونه',
            'department' => 'فروش',
        ])->assertNotFound();

        $this->assertSame(0, ConsultationRequest::query()->count());
    }
}
