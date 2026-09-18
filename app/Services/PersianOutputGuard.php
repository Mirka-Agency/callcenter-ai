<?php

namespace App\Services;

class PersianOutputGuard
{
    /** @var list<string> */
    private const TEXT_FIELDS = [
        'summary',
        'overall_evaluation',
        'evaluation',
        'transcript',
    ];

    /** @var list<string> */
    private const NESTED_TEXT_PATHS = [
        'lead_quality.reason',
        'customer_identity.person_name',
        'customer_identity.company_name',
        'customer_identity.evidence',
        'needs_attention.reason',
    ];

    /** @var list<string> */
    private const LIST_FIELDS = [
        'strengths',
        'weaknesses',
        'next_actions',
        'recommended_improvements',
    ];

    /** @var list<string> */
    private const NESTED_LIST_PATHS = [
        'operational_insights.missed_opportunities',
        'operational_insights.escalation_risks',
        'operational_insights.compliance_issues',
        'operational_insights.follow_up_suggestions',
        'operational_insights.important_keywords',
        'lead_quality.buying_intent_signals',
    ];

    public function containsEnglish(array $payload): bool
    {
        foreach (self::TEXT_FIELDS as $field) {
            if (! empty($payload[$field]) && is_string($payload[$field]) && $this->textHasEnglish($payload[$field])) {
                return true;
            }
        }

        foreach (self::NESTED_TEXT_PATHS as $path) {
            $value = $this->valueAtPath($payload, $path);

            if (is_string($value) && $this->textHasEnglish($value)) {
                return true;
            }
        }

        foreach (self::LIST_FIELDS as $field) {
            if ($this->listHasEnglish($payload[$field] ?? null)) {
                return true;
            }
        }

        foreach (self::NESTED_LIST_PATHS as $path) {
            if ($this->listHasEnglish($this->valueAtPath($payload, $path))) {
                return true;
            }
        }

        foreach ((array) ($payload['concerns'] ?? []) as $concern) {
            if (is_array($concern)) {
                $text = $concern['text'] ?? null;

                if (is_string($text) && $this->textHasEnglish($text)) {
                    return true;
                }
            }
        }

        $customerIntent = $payload['customer_insights']['intent'] ?? null;

        if (is_string($customerIntent) && $this->textHasEnglish($customerIntent)) {
            return true;
        }

        return false;
    }

    public function textHasEnglish(string $text): bool
    {
        $normalized = trim($text);

        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/[A-Za-z]{3,}/', $normalized);
    }

    private function listHasEnglish(mixed $items): bool
    {
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_string($item) && $this->textHasEnglish($item)) {
                return true;
            }
        }

        return false;
    }

    private function valueAtPath(array $payload, string $path): mixed
    {
        $current = $payload;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
