<?php

namespace Tests\Unit;

use App\Domain\Llm\Exceptions\LlmTransientException;
use Tests\TestCase;

class LlmTransientExceptionTest extends TestCase
{
    public function test_detects_avalai_service_unavailable_errors(): void
    {
        $message = 'OpenAI API error: {"error":{"code":"service_unavailable","message":"try again later"}}';

        $this->assertTrue(LlmTransientException::isTransientMessage($message));
    }

    public function test_ignores_permanent_configuration_errors(): void
    {
        $message = 'OpenAI API error: {"error":{"code":"invalid_api_key"}}';

        $this->assertFalse(LlmTransientException::isTransientMessage($message));
    }
}
