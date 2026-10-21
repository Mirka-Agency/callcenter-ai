<?php

namespace Tests\Unit;

use App\Application\Llm\Services\TranscriptSanitizer;
use PHPUnit\Framework\TestCase;

class TranscriptSanitizerTest extends TestCase
{
    public function test_it_keeps_speech_and_drops_ringtone_hold_music_and_repeated_hallucinations(): void
    {
        $transcript = implode("\n", [
            'کارشناس: سلام، وقت بخیر.',
            'نامشخص: [بوق]',
            'نامشخص: موسیقی انتظار',
            'مشتری: من را به داخلی حسابداری وصل کنید.',
            'نامشخص: لا لا لا',
            'کارشناس: [نامفهوم]',
            'نامشخص: این یک شعر ساختگی طولانی از موسیقی انتظار است',
            'نامشخص: این یک شعر ساختگی طولانی از موسیقی انتظار است',
            'نامشخص: این یک شعر ساختگی طولانی از موسیقی انتظار است',
            'کارشناس: همکارم در خط است.',
            'مشتری: آهنگ تبلیغ‌تان را شنیدم.',
            'مشتری: الو',
            'مشتری: الو',
            'مشتری: الو',
        ]);

        $cleaned = (new TranscriptSanitizer)->clean($transcript);

        $this->assertSame(
            implode("\n", [
                'کارشناس: سلام، وقت بخیر.',
                'مشتری: من را به داخلی حسابداری وصل کنید.',
                'کارشناس: [نامفهوم]',
                'کارشناس: همکارم در خط است.',
                'مشتری: آهنگ تبلیغ‌تان را شنیدم.',
                'مشتری: الو',
                'مشتری: الو',
            ]),
            $cleaned,
        );
    }

    public function test_it_drops_a_long_low_confidence_span_and_keeps_an_unclear_word(): void
    {
        $sanitizer = new TranscriptSanitizer;

        $this->assertSame('سلام دارد', $sanitizer->dropLowConfidenceSpans(
            'سلام بوق ممتد اینجا ادامه دارد',
            [
                ['token' => 'سلام', 'logprob' => -0.1],
                ['token' => ' بوق', 'logprob' => -2.0],
                ['token' => ' ممتد', 'logprob' => -2.2],
                ['token' => ' اینجا', 'logprob' => -1.8],
                ['token' => ' ادامه', 'logprob' => -1.5],
                ['token' => ' دارد', 'logprob' => -0.2],
            ],
        ));

        $this->assertSame('کد ۴۲ است', $sanitizer->dropLowConfidenceSpans(
            'کد ۴۲ است',
            [
                ['token' => 'کد', 'logprob' => -0.1],
                ['token' => ' ۴۲', 'logprob' => -1.4],
                ['token' => ' است', 'logprob' => -0.2],
            ],
        ));
    }

    public function test_it_rejects_whisper_segments_that_are_not_speech(): void
    {
        $kept = (new TranscriptSanitizer)->reliableSegments([
            ['text' => 'سلام', 'no_speech_prob' => 0.1, 'avg_logprob' => -0.2, 'compression_ratio' => 1.2],
            ['text' => 'بوق', 'no_speech_prob' => 0.8, 'avg_logprob' => -0.2, 'compression_ratio' => 1.1],
            ['text' => 'تکرار', 'no_speech_prob' => 0.1, 'avg_logprob' => -1.4, 'compression_ratio' => 1.1],
            ['text' => 'آهنگ', 'no_speech_prob' => 0.1, 'avg_logprob' => -0.2, 'compression_ratio' => 3.0],
        ]);

        $this->assertSame([['text' => 'سلام', 'no_speech_prob' => 0.1, 'avg_logprob' => -0.2, 'compression_ratio' => 1.2]], $kept);
    }
}
