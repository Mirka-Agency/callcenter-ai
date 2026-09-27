<?php

namespace App\Services\Coaching;

use App\Domain\Coaching\CoachingCatalog;
use Illuminate\Support\Facades\Log;

/**
 * Validates untrusted model output. Invalid coaching is dropped.
 * The rest of the call analysis is left untouched.
 */
class CoachingAnalysisNormalizer
{
    private const TEXT_LIMIT = 1000;

    private const EVIDENCE_LIMIT = 3;

    /**
     * @return array{
     *     skills: list<array<string, mixed>>,
     *     strengths: list<array<string, mixed>>,
     *     weaknesses: list<array<string, mixed>>,
     *     coaching_recommendations: list<array<string, mixed>>
     * }|null
     */
    public function normalize(mixed $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (! is_array($payload)) {
            $this->log('coaching_schema_invalid', ['reason' => 'coaching_analysis_not_object']);

            return null;
        }

        $issues = [];
        $skills = $this->skills($payload['skills'] ?? null, $issues);

        if ($skills === []) {
            if ($issues !== [] || array_key_exists('skills', $payload)) {
                $this->log('coaching_schema_invalid', [
                    'reason' => 'no_valid_skills',
                    'issues' => array_slice($issues, 0, 20),
                ]);
            }

            return null;
        }

        if ($issues !== []) {
            $this->log('coaching_skill_rejected', ['issues' => array_slice($issues, 0, 20)]);
        }

        return [
            'skills' => $skills,
            'strengths' => $this->narratives($payload['strengths'] ?? null, withSeverity: false),
            'weaknesses' => $this->narratives($payload['weaknesses'] ?? null, withSeverity: true),
            'coaching_recommendations' => $this->recommendations($payload['coaching_recommendations'] ?? null),
        ];
    }

    /**
     * @param  list<string>  $issues
     * @return list<array<string, mixed>>
     */
    private function skills(mixed $skills, array &$issues): array
    {
        if ($skills === null) {
            $issues[] = 'skills_missing';

            return [];
        }

        if (! is_array($skills)) {
            $issues[] = 'skills_not_list';

            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($skills as $skill) {
            if (! is_array($skill)) {
                $issues[] = 'skill_not_object';

                continue;
            }

            $key = trim((string) ($skill['skill_key'] ?? ''));

            if (! CoachingCatalog::knows($key)) {
                $issues[] = 'unknown_skill';

                continue;
            }

            if (isset($seen[$key])) {
                $issues[] = 'duplicate_skill:'.$key;

                continue;
            }

            if (! is_numeric($skill['score'] ?? null)) {
                $issues[] = 'invalid_score:'.$key;

                continue;
            }

            $score = round((float) $skill['score'], 1);

            if ($score < 0 || $score > 100) {
                $issues[] = 'score_out_of_range:'.$key;

                continue;
            }

            $confidence = $this->confidence($skill['confidence'] ?? null);
            if ($confidence === false) {
                $issues[] = 'invalid_confidence:'.$key;

                continue;
            }

            $seen[$key] = true;
            $normalized[] = [
                'skill_key' => $key,
                'score' => $score,
                'status' => CoachingCatalog::statusFor($score),
                'confidence' => $confidence,
                'evidence' => $this->evidence($skill['evidence'] ?? null),
                'feedback' => $this->text($skill['feedback'] ?? null),
                'recommendation' => $this->text($skill['recommendation'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function narratives(mixed $items, bool $withSeverity): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['skill_key'] ?? ''));
            $title = $this->text($item['title'] ?? null);
            $explanation = $this->text($item['explanation'] ?? null);

            if (! CoachingCatalog::knows($key) || $title === '' || $explanation === '') {
                continue;
            }

            $row = [
                'skill_key' => $key,
                'title' => $title,
                'explanation' => $explanation,
                'evidence' => $this->evidence($item['evidence'] ?? null),
            ];

            if ($withSeverity) {
                $severity = strtolower(trim((string) ($item['severity'] ?? '')));
                $row['severity'] = in_array($severity, CoachingCatalog::SEVERITIES, true) ? $severity : 'medium';
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recommendations(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['skill_key'] ?? ''));
            $recommendation = $this->text($item['recommendation'] ?? null);
            $action = $this->text($item['suggested_action'] ?? null);

            if (! CoachingCatalog::knows($key) || ($recommendation === '' && $action === '')) {
                continue;
            }

            $priority = strtolower(trim((string) ($item['priority'] ?? '')));

            $normalized[] = [
                'skill_key' => $key,
                'priority' => in_array($priority, CoachingCatalog::SEVERITIES, true) ? $priority : 'medium',
                'recommendation' => $recommendation,
                'suggested_action' => $action,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{description: string, quote_or_summary: string, start_time: ?int, end_time: ?int}>
     */
    private function evidence(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = $this->text($item['description'] ?? null);
            $quote = $this->text($item['quote_or_summary'] ?? null);

            if ($description === '' && $quote === '') {
                continue;
            }

            $normalized[] = [
                'description' => $description,
                'quote_or_summary' => $quote,
                'start_time' => $this->offset($item['start_time'] ?? null),
                'end_time' => $this->offset($item['end_time'] ?? null),
            ];

            if (count($normalized) >= self::EVIDENCE_LIMIT) {
                break;
            }
        }

        return $normalized;
    }

    private function confidence(mixed $value): float|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $confidence = round((float) $value, 2);

        if ($confidence < 0 || $confidence > 1) {
            return false;
        }

        return $confidence;
    }

    private function offset(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)))) {
            $seconds = (int) $value;

            return $seconds >= 0 && $seconds <= 8 * 3600 ? $seconds : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches) && (int) $matches[2] < 60) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $value, $matches) && (int) $matches[2] < 60 && (int) $matches[3] < 60) {
            return ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (int) $matches[3];
        }

        return null;
    }

    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $text = trim($value);

        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, self::TEXT_LIMIT);
    }

    /** @param  array<string, mixed>  $context */
    private function log(string $message, array $context): void
    {
        try {
            Log::warning($message, $context);
        } catch (\Throwable) {
            // Validation can run in unit tests that have not booted the logger.
        }
    }
}
