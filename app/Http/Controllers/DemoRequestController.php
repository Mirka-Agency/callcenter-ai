<?php

namespace App\Http\Controllers;

use App\Application\Demo\DemoRequestSmsNotifier;
use App\Http\Middleware\CaptureLeadAttribution;
use App\Models\DemoRequest;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DemoRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json([
                'message' => 'درخواست دمو ثبت شد. به‌زودی با شما تماس می‌گیریم.',
            ]);
        }

        if (Recaptcha::enabled() && ! Recaptcha::verify($this->captchaToken($request), Recaptcha::DEMO_ACTION)) {
            return response()->json([
                'message' => 'تأیید امنیتی انجام نشد. لطفاً دوباره تلاش کنید.',
                'errors' => [
                    'captcha' => ['تأیید امنیتی انجام نشد. لطفاً دوباره تلاش کنید.'],
                ],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'business_domain' => ['required', 'string', 'max:160'],
            'company_name' => ['required', 'string', 'max:160'],
            'website_url' => ['nullable', 'string', 'max:255'],
        ], [
            'full_name.required' => 'نام و نام خانوادگی را وارد کنید.',
            'full_name.min' => 'نام و نام خانوادگی باید حداقل ۳ حرف باشد.',
            'full_name.max' => 'نام و نام خانوادگی طولانی است.',
            'phone.required' => 'شماره تماس را وارد کنید.',
            'business_domain.required' => 'حوزه کسب و کار را وارد کنید.',
            'business_domain.max' => 'حوزه کسب و کار طولانی است.',
            'company_name.required' => 'نام شرکت را وارد کنید.',
            'company_name.max' => 'نام شرکت طولانی است.',
            'website_url.max' => 'لینک وب‌سایت طولانی است.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'اطلاعات واردشده معتبر نیست.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $phone = $this->normalizePhone($validated['phone']);

        if ($phone === null) {
            return response()->json([
                'message' => 'اطلاعات واردشده معتبر نیست.',
                'errors' => [
                    'phone' => ['شماره تماس معتبر نیست.'],
                ],
            ], 422);
        }

        $website = $this->normalizeWebsite($validated['website_url'] ?? null);

        if ($website === false) {
            return response()->json([
                'message' => 'اطلاعات واردشده معتبر نیست.',
                'errors' => [
                    'website_url' => ['لینک وب‌سایت معتبر نیست.'],
                ],
            ], 422);
        }

        $demo = DemoRequest::query()->create([
            'full_name' => trim($validated['full_name']),
            'phone' => $phone,
            'business_domain' => trim($validated['business_domain']),
            'company_name' => trim($validated['company_name']),
            'website_url' => $website,
            'ip_address' => $request->ip(),
            ...CaptureLeadAttribution::forLead($request),
        ]);

        app(DemoRequestSmsNotifier::class)->notify($demo);

        return response()->json([
            'message' => 'درخواست دمو ثبت شد. به‌زودی با شما تماس می‌گیریم.',
        ]);
    }

    private function captchaToken(Request $request): ?string
    {
        $token = $request->input('captcha');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = strtr($phone, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0098')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^0\d{9,10}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    private function normalizeWebsite(mixed $value): string|false|null
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $website = trim($value);

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $website)) {
            $website = 'https://'.$website;
        }

        if (filter_var($website, FILTER_VALIDATE_URL) === false || strlen($website) > 255) {
            return false;
        }

        $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
        $host = parse_url($website, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || ! str_contains($host, '.')) {
            return false;
        }

        return $website;
    }
}
