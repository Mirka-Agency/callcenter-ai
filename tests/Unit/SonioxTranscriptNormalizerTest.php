<?php

namespace Tests\Unit;

use App\Application\Transcription\Services\SonioxTranscriptNormalizer;
use PHPUnit\Framework\TestCase;

class SonioxTranscriptNormalizerTest extends TestCase
{
    public function test_preserves_words_timing_confidence_and_additional_speakers(): void
    {
        $normalizer = new SonioxTranscriptNormalizer;
        $tokens = [
            ['text' => 'سلام', 'speaker' => '1', 'start_ms' => 0, 'end_ms' => 100, 'confidence' => 0.9],
            ['text' => ' Hello', 'speaker' => '1', 'start_ms' => 100, 'end_ms' => 200],
            ['text' => 'درود', 'speaker' => '2', 'start_ms' => 180, 'end_ms' => 300],
            ['text' => 'نفر سوم', 'speaker' => '3'],
            ['text' => 'نامشخص'],
            ['text' => '<end>'],
        ];

        $segments = $normalizer->segments($tokens);
        $this->assertCount(4, $segments);
        $this->assertSame('سلام Hello', $segments[0]['text']);
        $this->assertSame(200, $segments[0]['end_ms']);
        $this->assertSame(0.9, $segments[0]['tokens'][0]['confidence']);
        $this->assertSame("کارشناس: سلام Hello\nمشتری: درود\nگوینده 3: نفر سوم\nگوینده نامشخص: نامشخص", $normalizer->render($segments, ['1' => 'agent', '2' => 'customer']));
    }

    public function test_repeated_speaker_after_another_turn_is_a_new_segment(): void
    {
        $normalizer = new SonioxTranscriptNormalizer;
        $segments = $normalizer->segments([['text' => 'الف', 'speaker' => '1'], ['text' => 'ب', 'speaker' => '2'], ['text' => 'ج', 'speaker' => '1']]);
        $this->assertCount(3, $segments);
    }
}
