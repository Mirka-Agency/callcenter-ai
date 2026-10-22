<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_shows_the_form_and_channels(): void
    {
        $this->get('http://avayar.org/contact')
            ->assertOk()
            ->assertSee('همیشه')
            ->assertSee('در کنار شما هستیم')
            ->assertSee('ارسال پیام')
            ->assertSee('نام و نام خانوادگی')
            ->assertSee('ایمیل')
            ->assertSee('موضوع')
            ->assertSee('متن پیام شما')
            ->assertSee('info@avayar.ir')
            ->assertSee('011-91011903')
            ->assertSee('مازندران، محمودآباد')
            ->assertSee('خیابان آزادی، بلوار شالیزار')
            ->assertSee('images/contact/communication-icons.png', false)
            ->assertSee(route('contact-messages.store'), false);
    }

    public function test_contact_page_on_another_domain_goes_to_login(): void
    {
        $this->get('http://app.example/contact')
            ->assertRedirect(route('login'));
    }

    public function test_landing_navigation_links_to_contact(): void
    {
        $this->get('http://avayar.org/')
            ->assertOk()
            ->assertSee(route('contact'), false)
            ->assertSee('تماس با ما');
    }

    public function test_contact_message_is_stored(): void
    {
        $this->postJson('http://avayar.org/contact-messages', [
            'full_name' => 'سارا محمدی',
            'email' => 'sara@example.com',
            'subject' => 'consultation',
            'message' => 'برای تیم فروش نیاز به تحلیل تماس داریم.',
        ])->assertOk()
            ->assertJsonPath('message', 'پیام شما ارسال شد. در سریع‌ترین زمان پاسخ می‌دهیم.');

        $this->assertDatabaseHas('contact_messages', [
            'full_name' => 'سارا محمدی',
            'email' => 'sara@example.com',
            'subject' => 'consultation',
            'message' => 'برای تیم فروش نیاز به تحلیل تماس داریم.',
        ]);
    }

    public function test_contact_message_requires_valid_fields(): void
    {
        $this->postJson('http://avayar.org/contact-messages', [
            'full_name' => 'س',
            'email' => 'not-an-email',
            'subject' => 'unknown',
            'message' => 'کوتاه',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'email', 'subject', 'message']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_honeypot_submissions_are_ignored(): void
    {
        $this->postJson('http://avayar.org/contact-messages', [
            'full_name' => 'ربات',
            'email' => 'bot@example.com',
            'subject' => 'other',
            'message' => 'این یک پیام اسپم آزمایشی است.',
            'website' => 'https://spam.example',
        ])->assertOk();

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_contact_message_is_rejected_off_the_landing_host(): void
    {
        $this->postJson('http://app.example/contact-messages', [
            'full_name' => 'سارا محمدی',
            'email' => 'sara@example.com',
            'subject' => 'support',
            'message' => 'برای تیم فروش نیاز به تحلیل تماس داریم.',
        ])->assertNotFound();
    }
}
