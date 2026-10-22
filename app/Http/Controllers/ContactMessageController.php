<?php

namespace App\Http\Controllers;

use App\Enums\ContactMessageSubject;
use App\Models\ContactMessage;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json([
                'message' => 'پیام شما ارسال شد. در سریع‌ترین زمان پاسخ می‌دهیم.',
            ]);
        }

        if (Recaptcha::enabled() && ! Recaptcha::verify($this->captchaToken($request), Recaptcha::CONTACT_ACTION)) {
            return response()->json([
                'message' => 'تأیید امنیتی انجام نشد. لطفاً دوباره تلاش کنید.',
                'errors' => [
                    'captcha' => ['تأیید امنیتی انجام نشد. لطفاً دوباره تلاش کنید.'],
                ],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:160'],
            'subject' => ['required', 'string', Rule::in(array_column(ContactMessageSubject::cases(), 'value'))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'full_name.required' => 'نام و نام خانوادگی را وارد کنید.',
            'full_name.min' => 'نام و نام خانوادگی باید حداقل ۳ حرف باشد.',
            'full_name.max' => 'نام و نام خانوادگی طولانی است.',
            'email.required' => 'ایمیل را وارد کنید.',
            'email.email' => 'ایمیل معتبر نیست.',
            'email.max' => 'ایمیل طولانی است.',
            'subject.required' => 'موضوع پیام را انتخاب کنید.',
            'subject.in' => 'موضوع پیام معتبر نیست.',
            'message.required' => 'متن پیام را بنویسید.',
            'message.min' => 'متن پیام باید حداقل ۱۰ حرف باشد.',
            'message.max' => 'متن پیام نباید بیشتر از ۲۰۰۰ حرف باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'اطلاعات واردشده معتبر نیست.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        ContactMessage::query()->create([
            'full_name' => trim($validated['full_name']),
            'email' => trim($validated['email']),
            'subject' => $validated['subject'],
            'message' => trim($validated['message']),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'پیام شما ارسال شد. در سریع‌ترین زمان پاسخ می‌دهیم.',
        ]);
    }

    private function captchaToken(Request $request): ?string
    {
        $token = $request->input('captcha');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
