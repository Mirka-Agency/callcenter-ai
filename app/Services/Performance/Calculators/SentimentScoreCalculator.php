<?php

namespace App\Services\Performance\Calculators;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;
use Illuminate\Support\Collection;

class SentimentScoreCalculator
{
    /** @var array<string, int> */
    private const WEIGHTS = [
        AnalysisSentiment::Positive->value => 100,
        AnalysisSentiment::Mixed->value => 60,
        AnalysisSentiment::Neutral->value => 50,
        AnalysisSentiment::Negative->value => 20,
    ];

    /** @return array<string, int> */
    public static function weights(): array
    {
        return self::WEIGHTS;
    }

    public static function weightExpression(string $column = 'conversation_analyses.sentiment'): string
    {
        $allowed = ['conversation_analyses.sentiment', 'sentiment'];
        $column = in_array($column, $allowed, true) ? $column : 'conversation_analyses.sentiment';

        return sprintf(
            "CASE %s WHEN '%s' THEN %d WHEN '%s' THEN %d WHEN '%s' THEN %d WHEN '%s' THEN %d ELSE NULL END",
            $column,
            AnalysisSentiment::Positive->value,
            self::WEIGHTS[AnalysisSentiment::Positive->value],
            AnalysisSentiment::Mixed->value,
            self::WEIGHTS[AnalysisSentiment::Mixed->value],
            AnalysisSentiment::Neutral->value,
            self::WEIGHTS[AnalysisSentiment::Neutral->value],
            AnalysisSentiment::Negative->value,
            self::WEIGHTS[AnalysisSentiment::Negative->value],
        );
    }

    /** @param  Collection<int, ConversationAnalysis>  $analyses */
    public function average(Collection $analyses): float
    {
        if ($analyses->isEmpty()) {
            return 0.0;
        }

        $total = $analyses->sum(
            fn (ConversationAnalysis $analysis) => self::WEIGHTS[$analysis->sentiment->value] ?? 50,
        );

        return round($total / $analyses->count(), 1);
    }
}
