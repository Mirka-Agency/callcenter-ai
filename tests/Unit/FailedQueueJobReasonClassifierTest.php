<?php

namespace Tests\Unit;

use App\Services\QueueMonitoring\FailedQueueJobReasonClassifier;
use Tests\TestCase;

class FailedQueueJobReasonClassifierTest extends TestCase
{
    public function test_detects_avalai_insufficient_quota_as_depleted_credits(): void
    {
        $exception = 'RuntimeException: OpenAI API error (HTTP 429): {"error":{"message":"You exceeded your current quota, please check your plan and billing details.","type":"insufficient_quota","code":"insufficient_quota"}}'
            ."\n#0 /app/AnalyzeAudioJob.php(96)";

        $this->assertSame(
            __('filament.failed_job_reasons.avalai_credits_depleted'),
            $this->classifier()->classify($exception, 'AnalyzeAudioJob'),
        );
    }

    public function test_detects_http_402_as_depleted_credits(): void
    {
        $exception = 'RuntimeException: OpenAI API error (HTTP 402): {"error":{"code":402,"message":"Insufficient balance"}}';

        $this->assertSame(
            __('filament.failed_job_reasons.avalai_credits_depleted'),
            $this->classifier()->classify($exception),
        );
    }

    public function test_does_not_treat_rate_limit_as_depleted_credits(): void
    {
        $exception = 'LlmTransientException: OpenAI API error (HTTP 429): {"error":{"code":"rate_limit_exceeded","message":"Rate limit reached"}}';

        $this->assertSame(
            __('filament.failed_job_reasons.rate_limited'),
            $this->classifier()->classify($exception, 'AnalyzeAudioJob'),
        );
    }

    public function test_does_not_treat_platform_wallet_as_avalai_credits(): void
    {
        $exception = 'App\\Exceptions\\InsufficientWalletBalanceException: موجودی اعتبار تحلیل کافی نیست. برای ادامه، موجودی خود را شارژ کنید.';

        $this->assertSame(
            __('filament.failed_job_reasons.wallet_insufficient'),
            $this->classifier()->classify($exception),
        );
    }

    public function test_detects_missing_recording_and_parse_failures(): void
    {
        $classifier = $this->classifier();

        $this->assertSame(
            __('filament.failed_job_reasons.missing_recording_url'),
            $classifier->classify('RuntimeException: No recording URL available for call.'),
        );
        $this->assertSame(
            __('filament.failed_job_reasons.parse_failed'),
            $classifier->classify('RuntimeException: Failed to parse OpenAI audio analysis response.'),
        );
    }

    public function test_falls_back_to_provider_message_when_code_is_unknown(): void
    {
        $exception = 'RuntimeException: OpenAI API error (HTTP 400): {"error":{"message":"Audio format is not supported.","code":"invalid_request_error"}}';

        $this->assertSame(
            __('filament.failed_job_reasons.provider_error', [
                'message' => 'Audio format is not supported.',
            ]),
            $this->classifier()->classify($exception),
        );
    }

    private function classifier(): FailedQueueJobReasonClassifier
    {
        return new FailedQueueJobReasonClassifier;
    }
}
