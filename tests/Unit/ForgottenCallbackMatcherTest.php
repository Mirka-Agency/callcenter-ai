<?php

namespace Tests\Unit;

use App\Support\ForgottenCallbackMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ForgottenCallbackMatcherTest extends TestCase
{
    #[DataProvider('phoneCallbacks')]
    public function test_it_accepts_promised_phone_callbacks(string $action): void
    {
        $this->assertTrue(ForgottenCallbackMatcher::matches($action), $action);
    }

    #[DataProvider('nonPhoneFollowUps')]
    public function test_it_rejects_non_phone_follow_ups(string $action): void
    {
        $this->assertFalse(ForgottenCallbackMatcher::matches($action), $action);
    }

    /** @return list<list<string>> */
    public static function phoneCallbacks(): array
    {
        return [
            ['تماس پیگیری فردا'],
            ['تماس پیگیری فردا برای ارسال قرارداد'],
            ['تماس پیگیری در روز بعد برای اعلام تصمیم مشتری'],
            ['تماس مجدد با مشتری'],
            ['تماس بازخورد امروز ساعت ۱۷'],
            ['زنگ زدن به مشتری فردا'],
            ['پیگیری تلفنی فردا'],
            ['کارشناس باید دوباره با مشتری تماس بگیرد'],
            ['تماس چک‌لیست فردا'],
            ['تماس پیگیری ۳ روز دیگر برای اتصال به بخش یا داخلی معرفی‌شده'],
            ['ارسال پیش‌فاکتور و تماس پیگیری فردا'],
            ['ارسال کاتالوگ در واتساپ و تماس پیگیری فردا'],
        ];
    }

    /** @return list<list<string>> */
    public static function nonPhoneFollowUps(): array
    {
        return [
            ['ارسال پیش‌فاکتور'],
            ['ارسال پیش‌فاکتور امروز'],
            ['ارسال فایل کاتالوگ در واتساپ'],
            ['ارسال پیام در اینستاگرام'],
            ['ارسال مستندات فنی در تلگرام'],
            ['ثبت تیکت فوری برای قطع سرویس'],
            ['ثبت تیکت برای مشکل سیستمی'],
            ['پیگیری مشکل سیستمی'],
            ['پیگیری هفته آینده'],
            ['هماهنگی دمو آنلاین'],
            ['یادآور ارسال پیام'],
            ['ارسال پیامک وضعیت مرسوله'],
            ['اطلاع‌رسانی به واحد مالی'],
            ['تماس با واحد فنی'],
            ['تماس پیگیری از طریق واتساپ'],
            ['شماره تماس را در واتساپ بفرستید'],
            ['تماس نگیرید، فایل را بفرستید'],
        ];
    }
}
