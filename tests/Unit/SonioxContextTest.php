<?php

namespace Tests\Unit;

use App\Application\Llm\Prompts\SonioxContext;
use App\Domain\Transcription\DTOs\TranscriptionContextData;
use PHPUnit\Framework\TestCase;

class SonioxContextTest extends TestCase
{
    public function test_english_is_preserved_without_a_fixed_vocabulary_or_translation_rules(): void
    {
        $context = SonioxContext::build();
        $general = array_column($context['general'], 'value', 'key');

        $this->assertStringContainsString('original English spelling when spoken', $general['instructions']);
        $this->assertStringContainsString('Do not translate or transliterate them into Persian', $general['instructions']);
        $this->assertStringContainsString('do not insert names, terms, or facts that are not audible', $general['instructions']);
        $this->assertArrayNotHasKey('terms', $context);
        $this->assertArrayNotHasKey('translation_terms', $context);
        $this->assertArrayNotHasKey('speakers', $general);
    }

    public function test_known_organization_context_is_trimmed_and_kept_separate_from_instructions(): void
    {
        $context = SonioxContext::build(new TranscriptionContextData(
            organizationName: ' شرکت نمونه ',
            organizationBusinessContext: ' پشتیبانی نرم‌افزار ',
        ));
        $general = array_column($context['general'], 'value', 'key');

        $this->assertSame('شرکت نمونه', $general['organization']);
        $this->assertSame('پشتیبانی نرم‌افزار', $general['domain']);
        $this->assertStringNotContainsString('شرکت نمونه', $general['instructions']);
    }

    public function test_empty_metadata_is_omitted(): void
    {
        $context = SonioxContext::build(new TranscriptionContextData(
            organizationName: " \t ",
            organizationBusinessContext: '',
        ));
        $general = array_column($context['general'], 'value', 'key');

        $this->assertArrayNotHasKey('organization', $general);
        $this->assertArrayNotHasKey('domain', $general);
    }

    public function test_large_metadata_is_bounded_without_corrupting_persian_text(): void
    {
        $context = SonioxContext::build(new TranscriptionContextData(
            organizationName: str_repeat('آ', 300),
            organizationBusinessContext: str_repeat('ب', 5000),
        ));
        $general = array_column($context['general'], 'value', 'key');

        $this->assertSame(str_repeat('آ', 200), $general['organization']);
        $this->assertSame(str_repeat('ب', 1000), $general['domain']);
        $this->assertNotEmpty(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
