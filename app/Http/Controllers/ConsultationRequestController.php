<?php

namespace App\Http\Controllers;

use App\Application\Consultation\ConsultationSmsNotifier;
use App\Http\Middleware\CaptureLeadAttribution;
use App\Models\ConsultationRequest;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConsultationRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json([
                'message' => 'درخواست مشاوره ثبت شد. به‌زودی با شما تماس می‌گیریم.',
            ]);
        }

        if (Recaptcha::enabled() && ! Recaptcha::verify($this->captchaToken($request), Recaptcha::CONSULTATION_ACTION)) {
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
            'company_name' => ['required', 'string', 'max:160'],
            'department' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'full_name.required' => 'نام و نام خانوادگی را وارد کنید.',
            'full_name.min' => 'نام و نام خانوادگی باید حداقل ۳ حرف باشد.',
            'full_name.max' => 'نام و نام خانوادگی طولانی است.',
            'phone.required' => 'شماره تماس را وارد کنید.',
            'company_name.required' => 'نام شرکت را وارد کنید.',
            'company_name.max' => 'نام شرکت طولانی است.',
            'department.required' => 'واحدی که نیاز به آوایار دارد را وارد کنید.',
            'department.max' => 'نام واحد طولانی است.',
            'notes.max' => 'توضیحات نباید بیشتر از ۲۰۰۰ حرف باشد.',
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

        $consultation = ConsultationRequest::query()->create([
            'full_name' => trim($validated['full_name']),
            'phone' => $phone,
            'company_name' => trim($validated['company_name']),
            'department' => trim($validated['department']),
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'ip_address' => $request->ip(),
            ...CaptureLeadAttribution::forLead($request),
        ]);

        app(ConsultationSmsNotifier::class)->notify($consultation);

        return response()->json([
            'message' => 'درخواست مشاوره ثبت شد. به‌زودی با شما تماس می‌گیریم.',
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
}
