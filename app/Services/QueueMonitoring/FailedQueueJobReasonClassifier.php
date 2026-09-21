<?php

namespace App\Services\QueueMonitoring;

class FailedQueueJobReasonClassifier
{
    public function classify(?string $exception, ?string $jobClass = null): ?string
    {
        if (! is_string($exception) || trim($exception) === '') {
            return null;
        }

        $payload = $this->extractErrorPayload($exception);
        $haystack = mb_strtolower(trim(implode("\n", array_filter([
            $exception,
            $jobClass,
            $payload['code'],
            $payload['type'],
            $payload['message'],
        ]))));

        if ($this->isProviderCreditDepleted($haystack, $payload)) {
            return __('filament.failed_job_reasons.avalai_credits_depleted');
        }

        if ($this->containsAny($haystack, [
            'باید در پنل ادمین تنظیم شود',
            'no api key',
        ])) {
            return __('filament.failed_job_reasons.missing_api_key');
        }

        if ($this->containsAny($haystack, [
            'invalid_api_key',
            'incorrect api key',
            'incorrect_api_key',
            'authentication_error',
            'invalid authentication',
            'api key not valid',
        ]) || $payload['httpStatus'] === 401) {
            return __('filament.failed_job_reasons.invalid_api_key');
        }

        if ($this->containsAny($haystack, [
            'rate_limit_exceeded',
            'rate_limit',
            'too many requests',
            'too_many_requests',
        ])) {
            return __('filament.failed_job_reasons.rate_limited');
        }

        if (
            $this->containsAny($haystack, ['model_not_found', 'invalid model'])
            || (str_contains($haystack, 'model') && $this->containsAny($haystack, [
                'does not exist',
                'is not available',
            ]))
        ) {
            return __('filament.failed_job_reasons.model_not_found');
        }

        if ($this->containsAny($haystack, [
            'context_length_exceeded',
            'maximum context length',
            'string too long',
            'payload too large',
            'request too large',
            'audio is too long',
        ])) {
            return __('filament.failed_job_reasons.context_length');
        }

        if ($this->containsAny($haystack, [
            'service_unavailable',
            'temporarily unavailable',
            'try again later',
            'bad gateway',
            'gateway timeout',
            'server_error',
        ]) || in_array($payload['httpStatus'], [502, 503, 504], true)) {
            return __('filament.failed_job_reasons.service_unavailable');
        }

        if ($this->containsAny($haystack, [
            'timeout',
            'timed out',
            'maximum execution time',
        ])) {
            return __('filament.failed_job_reasons.timeout');
        }

        if ($this->containsAny($haystack, [
            'no recording url available',
        ])) {
            return __('filament.failed_job_reasons.missing_recording_url');
        }

        if ($this->containsAny($haystack, [
            'recording file not found',
            'recordingnotfoundexception',
        ])) {
            return __('filament.failed_job_reasons.recording_not_found');
        }

        if ($this->containsAny($haystack, [
            'recording download failed',
            'unable to fetch recording',
        ])) {
            return __('filament.failed_job_reasons.recording_download_failed');
        }

        if ($this->containsAny($haystack, [
            'فایل صوتی برای تحلیل یافت نشد',
        ])) {
            return __('filament.failed_job_reasons.audio_file_missing');
        }

        if ($this->containsAny($haystack, [
            'آدرس قابل‌دسترس فایل صوتی',
            'آدرس قابل‌دسترس فایل صوتی برای تحلیل یافت نشد',
        ])) {
            return __('filament.failed_job_reasons.audio_url_missing');
        }

        if ($this->containsAny($haystack, [
            'failed to parse',
            'llm analysis failed',
        ])) {
            return __('filament.failed_job_reasons.parse_failed');
        }

        if ($this->containsAny($haystack, [
            'موجودی اعتبار تحلیل کافی نیست',
            'insufficientwalletbalanceexception',
        ])) {
            return __('filament.failed_job_reasons.wallet_insufficient');
        }

        if ($this->containsAny($haystack, [
            'تحلیل هوش مصنوعی در این محیط غیرفعال است',
            'llmremotedisabledexception',
        ])) {
            return __('filament.failed_job_reasons.remote_disabled');
        }

        if ($this->containsAny($haystack, [
            'no query results for model',
            'modelnotfoundexception',
        ])) {
            return __('filament.failed_job_reasons.call_not_found');
        }

        if ($payload['httpStatus'] === 429) {
            return __('filament.failed_job_reasons.rate_limited_or_quota');
        }

        if ($payload['message'] !== '') {
            return __('filament.failed_job_reasons.provider_error', [
                'message' => $payload['message'],
            ]);
        }

        return $this->firstLine($exception);
    }

    /**
     * @param  array{httpStatus: ?int, code: string, type: string, message: string}  $payload
     */
    private function isProviderCreditDepleted(string $haystack, array $payload): bool
    {
        if ($payload['httpStatus'] === 402 || $payload['code'] === '402') {
            return true;
        }

        $codes = [
            'insufficient_quota',
            'quota_exceeded',
            'billing_not_active',
            'billing_hard_limit_reached',
            'insufficient_funds',
            'insufficient_credits',
            'insufficient_balance',
            'payment_required',
        ];

        if (in_array($payload['code'], $codes, true) || in_array($payload['type'], $codes, true)) {
            return true;
        }

        return $this->containsAny($haystack, [
            'insufficient_quota',
            'quota_exceeded',
            'billing_hard_limit',
            'billing_not_active',
            'exceeded your current quota',
            'check your plan and billing',
            'insufficient funds',
            'insufficient credits',
            'insufficient balance',
            'no credits',
            'out of credit',
            'credit exhausted',
            'payment required',
            'موجودی کافی نیست',
            'موجودی حساب',
            'اعتبار کافی نیست',
            'اعتبار شما تمام',
            'شارژ شما تمام',
            'شارژ حساب',
        ]);
    }

    /** @return array{httpStatus: ?int, code: string, type: string, message: string} */
    private function extractErrorPayload(string $exception): array
    {
        $httpStatus = null;

        if (preg_match('/HTTP\s+(\d{3})/i', $exception, $matches)) {
            $httpStatus = (int) $matches[1];
        }

        $json = $this->extractJson($exception);
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];

        $code = $error['code'] ?? $json['code'] ?? '';
        $type = $error['type'] ?? $json['type'] ?? '';
        $message = $error['message'] ?? $json['message'] ?? '';

        return [
            'httpStatus' => $httpStatus,
            'code' => is_scalar($code) ? strtolower(trim((string) $code)) : '',
            'type' => is_scalar($type) ? strtolower(trim((string) $type)) : '',
            'message' => is_scalar($message) ? trim((string) $message) : '',
        ];
    }

    /** @return array<string, mixed> */
    private function extractJson(string $exception): array
    {
        $firstLine = trim(strtok($exception, "\n") ?: $exception);
        $start = strpos($firstLine, '{');
        $end = strrpos($firstLine, '}');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($firstLine, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  list<string>  $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function firstLine(string $exception): string
    {
        return trim(strtok($exception, "\n") ?: $exception);
    }
}
