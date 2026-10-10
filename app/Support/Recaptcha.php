<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha
{
    public static function enabled(): bool
    {
        if (OnPrem::enabled()) {
            return false;
        }

        if (! filter_var(config('services.recaptcha.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public static function siteKey(): ?string
    {
        $siteKey = config('services.recaptcha.site_key');

        return is_string($siteKey) && $siteKey !== '' ? $siteKey : null;
    }

    public static function verify(?string $token): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('reCAPTCHA verification could not reach Google.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->ok() || $response->json('success') !== true) {
            Log::warning('reCAPTCHA verification was rejected.', [
                'status' => $response->status(),
                'errors' => $response->json('error-codes'),
            ]);

            return false;
        }

        return true;
    }
}
