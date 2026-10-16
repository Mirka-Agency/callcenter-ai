<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha
{
    public const ACTION = 'login';

    public const CONSULTATION_ACTION = 'consultation';

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

    public static function verify(?string $token, string $action = self::ACTION): bool
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

        $score = $response->json('score');
        $minScore = self::minScore();
        $responseAction = $response->json('action');

        if (! $response->ok() || $response->json('success') !== true || ! is_numeric($score) || (float) $score < $minScore || $responseAction !== $action) {
            Log::warning('reCAPTCHA verification was rejected.', [
                'status' => $response->status(),
                'errors' => $response->json('error-codes'),
                'score' => is_numeric($score) ? (float) $score : null,
                'min_score' => $minScore,
                'action' => is_string($responseAction) ? $responseAction : null,
            ]);

            return false;
        }

        return true;
    }

    private static function minScore(): float
    {
        $score = config('services.recaptcha.min_score', 0.5);

        if (! is_numeric($score)) {
            return 0.5;
        }

        return max(0.0, min(1.0, (float) $score));
    }
}
