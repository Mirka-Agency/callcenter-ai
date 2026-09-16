<?php

namespace Tests\Unit;

use App\Domain\Llm\Enums\AnalysisSentiment;
use PHPUnit\Framework\TestCase;

class AnalysisSentimentTest extends TestCase
{
    public function test_maps_persian_and_english_sentiment_values(): void
    {
        $this->assertSame(AnalysisSentiment::Positive, AnalysisSentiment::fromAnalysisValue('مثبت'));
        $this->assertSame(AnalysisSentiment::Positive, AnalysisSentiment::fromAnalysisValue('positive'));
        $this->assertSame(AnalysisSentiment::Negative, AnalysisSentiment::fromAnalysisValue('منفی'));
        $this->assertSame(AnalysisSentiment::Mixed, AnalysisSentiment::fromAnalysisValue('ترکیبی'));
        $this->assertSame(AnalysisSentiment::Neutral, AnalysisSentiment::fromAnalysisValue('خنثی'));
        $this->assertSame(AnalysisSentiment::Neutral, AnalysisSentiment::fromAnalysisValue('unknown'));
    }
}
