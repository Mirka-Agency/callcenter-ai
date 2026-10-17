<?php

namespace Tests\Feature;

use App\Models\DemoRequest;
use App\Support\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_hero_opens_the_demo_request_form(): void
    {
        $this->get('http://avayar.org/')
            ->assertOk()
            ->assertSee('data-demo-open', false)
            ->assertSee('درخواست دمو')
            ->assertDontSee('مشاهده داشبورد')
            ->assertSee('حوزه کسب و کار')
            ->assertSee('لینک وب‌سایت', false)
            ->assertSee('name="website"', false)
            ->assertSee(route('demo-requests.store'), false);
    }

    public function test_demo_request_is_stored_without_a_website(): void
    {
        Http::fake();

        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'business_domain' => 'بیمه',
            'company_name' => 'شرکت نمونه',
        ])->assertOk();

        $this->assertDatabaseHas('demo_requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'بیمه',
            'company_name' => 'شرکت نمونه',
            'website_url' => null,
        ]);

        Http::assertNothingSent();
    }

    public function test_demo_request_normalizes_a_website_without_a_scheme(): void
    {
        Http::fake();

        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'فروش',
            'company_name' => 'شرکت نمونه',
            'website_url' => 'example.com/about',
        ])->assertOk();

        $this->assertDatabaseHas('demo_requests', [
            'phone' => '09121234567',
            'website_url' => 'https://example.com/about',
        ]);
    }

    public function test_demo_request_rejects_an_invalid_website(): void
    {
        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'فروش',
            'company_name' => 'شرکت نمونه',
            'website_url' => 'javascript:alert(1)',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['website_url']);

        $this->assertSame(0, DemoRequest::query()->count());
    }

    public function test_demo_request_sends_sms_to_applicant_and_owner(): void
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

        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'آموزش',
            'company_name' => 'شرکت نمونه',
            'website_url' => 'https://example.com',
        ])->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['mobiles'] === ['09121234567']
            && str_contains($request['messageText'], 'درخواست دمو شما با موفقیت ثبت شد')
            && str_contains($request['messageText'], 'آوایار | هوشمندتر بشنوید، بهتر تصمیم بگیرید')
            && str_contains($request['messageText'], 'لغو ۱۱')
            && $request->hasHeader('X-API-KEY', 'test-key')
            && $request['lineNumber'] === 3000123456);
        Http::assertSent(fn ($request) => $request['mobiles'] === ['09351234567']
            && str_contains($request['messageText'], 'سارا محمدی')
            && str_contains($request['messageText'], 'آموزش')
            && str_contains($request['messageText'], 'https://example.com')
            && str_contains($request['messageText'], '09121234567'));
    }

    public function test_demo_request_requires_contact_fields(): void
    {
        $this->postJson('http://avayar.org/demo-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'phone', 'business_domain', 'company_name']);

        $this->assertSame(0, DemoRequest::query()->count());
    }

    public function test_honeypot_submissions_are_ignored(): void
    {
        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'ربات',
            'phone' => '09120000000',
            'business_domain' => 'فروش',
            'company_name' => 'اسپم',
            'website_url' => 'https://real.example',
            'website' => 'https://spam.example',
        ])->assertOk();

        $this->assertSame(0, DemoRequest::query()->count());
    }

    public function test_landing_shows_the_demo_recaptcha_notice_when_enabled(): void
    {
        $this->enableRecaptcha();

        $this->get('http://avayar.org/')
            ->assertOk()
            ->assertSee('data-recaptcha-action="demo"', false)
            ->assertSee('data-recaptcha-site-key="test-site-key"', false);
    }

    public function test_demo_request_requires_a_valid_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => Recaptcha::CONSULTATION_ACTION,
            ]),
        ]);

        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'فروش',
            'company_name' => 'شرکت نمونه',
            'captcha' => 'wrong-action',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['captcha']);

        $this->assertSame(0, DemoRequest::query()->count());
    }

    public function test_demo_request_is_stored_when_recaptcha_passes(): void
    {
        $this->enableRecaptcha();

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => Recaptcha::DEMO_ACTION,
            ]),
        ]);

        $this->postJson('http://avayar.org/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'فروش',
            'company_name' => 'شرکت نمونه',
            'captcha' => 'good-token',
        ])->assertOk();

        $this->assertDatabaseHas('demo_requests', [
            'phone' => '09121234567',
            'business_domain' => 'فروش',
        ]);
    }

    public function test_demo_form_is_rejected_off_the_public_domain(): void
    {
        $this->postJson('http://app.example/demo-requests', [
            'full_name' => 'سارا محمدی',
            'phone' => '09121234567',
            'business_domain' => 'فروش',
            'company_name' => 'شرکت نمونه',
        ])->assertNotFound();

        $this->assertSame(0, DemoRequest::query()->count());
    }

    private function enableRecaptcha(): void
    {
        config([
            'onprem.enabled' => false,
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret',
        ]);
    }
}
