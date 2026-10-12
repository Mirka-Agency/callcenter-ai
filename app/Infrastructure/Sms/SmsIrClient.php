<?php

namespace App\Infrastructure\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsIrClient
{
    public function configured(): bool
    {
        return filled(config('services.smsir.api_key'))
            && filled(config('services.smsir.line_number'));
    }

    public function send(string $mobile, string $message): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $recipient = $this->mobile($mobile);

        if ($recipient === null) {
            Log::warning('sms.ir skipped a non-mobile recipient.');

            return false;
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'X-API-KEY' => (string) config('services.smsir.api_key'),
                ])
                ->post(rtrim((string) config('services.smsir.base_url'), '/').'/send/bulk', [
                    'lineNumber' => (int) preg_replace('/\D+/', '', (string) config('services.smsir.line_number')),
                    'messageText' => $message,
                    'mobiles' => [$recipient],
                ]);
        } catch (\Throwable $exception) {
            Log::error('sms.ir request failed.', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $status = $response->json('status');

        if (! $response->successful() || (int) $status !== 1) {
            Log::error('sms.ir rejected a message.', [
                'http_status' => $response->status(),
                'status' => $status,
                'message' => $response->json('message'),
            ]);

            return false;
        }

        return true;
    }

    public function mobile(string $phone): ?string
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
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }
}
