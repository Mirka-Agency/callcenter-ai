<?php

namespace App\Application\Llm\Services;

use App\Support\CompanyName;
use App\Support\NeedsAttention;
use App\Support\PaymentFollowUpSentiment;

class AnalysisResponseNormalizer
{
    /** @return array{score: int, level: string, reason: string, buying_intent_signals: list<string>} */
    public function normalizeLeadQuality(mixed $leadQuality): array
    {
        if (! is_array($leadQuality)) {
            $leadQuality = [];
        }

        $score = max(0, min(100, (int) ($leadQuality['score'] ?? 0)));
        $level = $this->normalizeLevel($leadQuality['level'] ?? null);
        $level = in_array($level, ['low', 'medium', 'high'], true) ? $level : $this->levelFromScore($score);

        $signals = array_values(array_filter(
            (array) ($leadQuality['buying_intent_signals'] ?? []),
            fn (mixed $signal) => is_string($signal) && trim($signal) !== '',
        ));

        return [
            'score' => $score,
            'level' => $level,
            'reason' => trim((string) ($leadQuality['reason'] ?? 'ارزیابی کیفیت لید در دسترس نیست.')),
            'buying_intent_signals' => $signals,
        ];
    }

    /** @return list<array{type: string, text: string, severity: string}> */
    public function normalizeConcerns(mixed $concerns): array
    {
        if (! is_array($concerns)) {
            return [];
        }

        $normalized = [];

        foreach ($concerns as $concern) {
            if (! is_array($concern)) {
                continue;
            }

            $text = trim((string) ($concern['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $type = $this->normalizeConcernType($concern['type'] ?? null);
            $severity = $this->normalizeLevel($concern['severity'] ?? null);
            $severity = in_array($severity, ['low', 'medium', 'high'], true) ? $severity : 'medium';

            $normalized[] = [
                'type' => $type,
                'text' => $text,
                'severity' => $severity,
            ];
        }

        return $normalized;
    }

    /** @return array{person_name: string, company_name: string, email: string, job_title: string, phone_number: string, confidence: float, evidence: string} */
    public function normalizeCustomerIdentity(mixed $customerIdentity, ?array $crmContext = null): array
    {
        if (! is_array($customerIdentity)) {
            $customerIdentity = [];
        }

        $personName = trim((string) ($customerIdentity['person_name'] ?? ''));
        $companyName = CompanyName::display((string) ($customerIdentity['company_name'] ?? ''));
        $email = trim((string) ($customerIdentity['email'] ?? ''));
        $jobTitle = trim((string) ($customerIdentity['job_title'] ?? ''));
        $phoneNumber = trim((string) ($customerIdentity['phone_number'] ?? ''));
        $evidence = trim((string) ($customerIdentity['evidence'] ?? ''));

        $confidence = (float) ($customerIdentity['confidence'] ?? 0);
        if ($confidence > 1) {
            $confidence = $confidence / 100;
        }
        $confidence = max(0.0, min(1.0, round($confidence, 2)));

        $currentUserName = trim((string) ($crmContext['current_user_name'] ?? ''));
        $currentCompanyName = trim((string) ($crmContext['current_company_name'] ?? ''));

        if ($this->matchesExcludedPerson($personName, $currentUserName)) {
            $personName = '';
            $confidence = min($confidence, 0.3);
        }

        if ($this->matchesExcludedCompany($companyName, $currentCompanyName)) {
            $companyName = '';
            $confidence = min($confidence, 0.3);
        }

        if ($personName === '' && $companyName === '' && $email === '' && $jobTitle === '' && $phoneNumber === '') {
            $confidence = 0.0;
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
            $confidence = min($confidence, 0.3);
        }

        return [
            'person_name' => $personName,
            'company_name' => $companyName,
            'email' => $email,
            'job_title' => $jobTitle,
            'phone_number' => $phoneNumber,
            'confidence' => $confidence,
            'evidence' => $evidence,
        ];
    }

    /** @param array<string, mixed> $response
     * @param  array<string, mixed>|null  $crmContext
     * @return array<string, mixed>
     */
    public function apply(array $response, ?array $crmContext = null): array
    {
        $response['lead_quality'] = $this->normalizeLeadQuality($response['lead_quality'] ?? null);
        $response['concerns'] = $this->normalizeConcerns($response['concerns'] ?? null);
        $response['customer_identity'] = $this->normalizeCustomerIdentity($response['customer_identity'] ?? null, $crmContext);
        $response = PaymentFollowUpSentiment::correct($response);
        $response['needs_attention'] = NeedsAttention::fromResponse($response);
        $response['evaluable'] = $this->resolveEvaluable($response);

        if (! $response['evaluable']) {
            $response['score'] = 0;
            $response['lead_quality']['score'] = 0;
            $response['lead_quality']['level'] = 'low';
        }

        return $response;
    }

    /** @param array<string, mixed> $response */
    private function resolveEvaluable(array $response): bool
    {
        if (array_key_exists('evaluable', $response)) {
            $raw = $response['evaluable'];

            if (is_bool($raw)) {
                return $raw;
            }

            if (is_numeric($raw)) {
                return (int) $raw === 1;
            }

            if (is_string($raw)) {
                return in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes', 'بله'], true);
            }

            return false;
        }

        if (! array_key_exists('score', $response)) {
            return true;
        }

        return (int) $response['score'] > 0;
    }

    private function matchesExcludedPerson(string $value, string $excluded): bool
    {
        if ($value === '' || $excluded === '') {
            return false;
        }

        return $this->normalizePersonName($value) === $this->normalizePersonName($excluded);
    }

    private function normalizePersonName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^(?:آقای|آقا|خانم|مهندس|دکتر|جناب)\s+/u', '', $value) ?? $value;

        return trim($value);
    }

    private function matchesExcludedCompany(string $value, string $excluded): bool
    {
        if ($value === '' || $excluded === '') {
            return false;
        }

        return CompanyName::matches($value, $excluded);
    }

    private function levelFromScore(int $score): string
    {
        return match (true) {
            $score >= 70 => 'high',
            $score >= 40 => 'medium',
            default => 'low',
        };
    }

    private function normalizeLevel(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'low', 'کم', 'پایین' => 'low',
            'medium', 'متوسط' => 'medium',
            'high', 'بالا', 'زیاد' => 'high',
            'critical', 'بحرانی' => 'critical',
            default => null,
        };
    }

    private function normalizeConcernType(mixed $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'price', 'قیمت' => 'price',
            'trust', 'اعتماد' => 'trust',
            'timing', 'زمان', 'زمان‌بندی', 'زمانبندی' => 'timing',
            'technical', 'فنی' => 'technical',
            default => 'other',
        };
    }
}
