<?php

namespace App\Application\Blog\Support;

use Illuminate\Validation\ValidationException;

final class FaqNormalizer
{
    public const MAX_ITEMS = 50;

    public const MAX_QUESTION = 500;

    public const MAX_ANSWER = 5000;

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function normalize(mixed $faq): array
    {
        if ($faq === null || $faq === '') {
            return [];
        }

        if (! is_array($faq)) {
            throw ValidationException::withMessages([
                'faq_json' => __('filament.blog.validation.faq_invalid'),
            ]);
        }

        $items = [];

        foreach ($faq as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            if ($question === '' || $answer === '') {
                throw ValidationException::withMessages([
                    'faq_json' => __('filament.blog.validation.faq_incomplete'),
                ]);
            }

            if (mb_strlen($question) > self::MAX_QUESTION || mb_strlen($answer) > self::MAX_ANSWER) {
                throw ValidationException::withMessages([
                    'faq_json' => __('filament.blog.validation.faq_too_long'),
                ]);
            }

            $items[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        if (count($items) > self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'faq_json' => __('filament.blog.validation.faq_too_many'),
            ]);
        }

        return $items;
    }
}
