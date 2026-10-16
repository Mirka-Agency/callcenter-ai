<?php

namespace App\Domain\Crm\DTOs;

/**
 * Per-organization preferences for internal CRM (call summary, AI tools, follow-up, seller identity, tax).
 * Distinct from {@see CrmSettings}, which stores external CRM connection defaults.
 */
readonly class OrganizationCrmPreferences
{
    public const DEFAULT_TAX_PERCENT = 10.0;

    /**
     * @param  list<int>  $salesFollowUpDefaultDays
     * @param  array{
     *     legal_name: string,
     *     trade_name: string,
     *     national_id: string,
     *     economic_code: string,
     *     registration_number: string,
     *     postal_code: string,
     *     phone: string,
     *     email: string,
     *     address: string
     * }  $companyIdentity
     */
    public function __construct(
        public ?int $callSummaryPipelineId = null,
        public bool $aiCatalogSearchEnabled = true,
        public bool $requireNationalIdForNewNumbers = false,
        public bool $aiProformaEnabled = true,
        public bool $salesFollowUpEnabled = false,
        public array $salesFollowUpDefaultDays = [3, 10],
        public int $salesFollowUpMaxCount = 2,
        public int $salesFollowUpMinDayInterval = 3,
        public int $salesFollowUpEarliestDays = 2,
        public bool $salesFollowUpAiCanChangeDays = false,
        public int $salesFollowUpReplanIntervalDays = 7,
        public array $companyIdentity = [
            'legal_name' => '',
            'trade_name' => '',
            'national_id' => '',
            'economic_code' => '',
            'registration_number' => '',
            'postal_code' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
        ],
        public float $defaultTaxPercent = self::DEFAULT_TAX_PERCENT,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $followUp = is_array($data['sales_follow_up'] ?? null) ? $data['sales_follow_up'] : [];
        $identity = is_array($data['company_identity'] ?? null) ? $data['company_identity'] : [];

        $pipelineId = $data['call_summary_pipeline_id'] ?? null;
        $pipelineId = $pipelineId === null || $pipelineId === '' ? null : (int) $pipelineId;

        return new self(
            callSummaryPipelineId: $pipelineId && $pipelineId > 0 ? $pipelineId : null,
            aiCatalogSearchEnabled: array_key_exists('ai_catalog_search_enabled', $data)
                ? (bool) $data['ai_catalog_search_enabled']
                : true,
            requireNationalIdForNewNumbers: (bool) ($data['require_national_id_for_new_numbers'] ?? false),
            aiProformaEnabled: array_key_exists('ai_proforma_enabled', $data)
                ? (bool) $data['ai_proforma_enabled']
                : true,
            salesFollowUpEnabled: (bool) ($followUp['enabled'] ?? false),
            salesFollowUpDefaultDays: self::normalizeDays($followUp['default_days'] ?? [3, 10]),
            salesFollowUpMaxCount: max(1, min(20, (int) ($followUp['max_count'] ?? 2))),
            salesFollowUpMinDayInterval: max(1, min(90, (int) ($followUp['min_day_interval'] ?? 3))),
            salesFollowUpEarliestDays: max(0, min(90, (int) ($followUp['earliest_days'] ?? 2))),
            salesFollowUpAiCanChangeDays: (bool) ($followUp['ai_can_change_days'] ?? false),
            salesFollowUpReplanIntervalDays: max(1, min(365, (int) ($followUp['replan_interval_days'] ?? 7))),
            companyIdentity: self::normalizeIdentity($identity),
            defaultTaxPercent: self::normalizeTax($data['default_tax_percent'] ?? self::DEFAULT_TAX_PERCENT),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'call_summary_pipeline_id' => $this->callSummaryPipelineId,
            'ai_catalog_search_enabled' => $this->aiCatalogSearchEnabled,
            'require_national_id_for_new_numbers' => $this->requireNationalIdForNewNumbers,
            'ai_proforma_enabled' => $this->aiProformaEnabled,
            'sales_follow_up' => [
                'enabled' => $this->salesFollowUpEnabled,
                'default_days' => $this->salesFollowUpDefaultDays,
                'max_count' => $this->salesFollowUpMaxCount,
                'min_day_interval' => $this->salesFollowUpMinDayInterval,
                'earliest_days' => $this->salesFollowUpEarliestDays,
                'ai_can_change_days' => $this->salesFollowUpAiCanChangeDays,
                'replan_interval_days' => $this->salesFollowUpReplanIntervalDays,
            ],
            'company_identity' => $this->companyIdentity,
            'default_tax_percent' => $this->defaultTaxPercent,
        ];
    }

    public function salesFollowUpDefaultDaysCsv(): string
    {
        return implode(', ', $this->salesFollowUpDefaultDays);
    }

    /**
     * @param  list<int>|string|mixed  $value
     * @return list<int>
     */
    public static function normalizeDays(mixed $value): array
    {
        if (is_string($value)) {
            $parts = preg_split('/[,\s]+/u', $value) ?: [];
            $days = [];
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part === '' || ! is_numeric($part)) {
                    continue;
                }
                $day = (int) $part;
                if ($day >= 1 && $day <= 365) {
                    $days[] = $day;
                }
            }
        } elseif (is_array($value)) {
            $days = [];
            foreach ($value as $item) {
                if (! is_numeric($item)) {
                    continue;
                }
                $day = (int) $item;
                if ($day >= 1 && $day <= 365) {
                    $days[] = $day;
                }
            }
        } else {
            $days = [3, 10];
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days !== [] ? $days : [3, 10];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array{
     *     legal_name: string,
     *     trade_name: string,
     *     national_id: string,
     *     economic_code: string,
     *     registration_number: string,
     *     postal_code: string,
     *     phone: string,
     *     email: string,
     *     address: string
     * }
     */
    public static function normalizeIdentity(array $identity): array
    {
        $keys = [
            'legal_name',
            'trade_name',
            'national_id',
            'economic_code',
            'registration_number',
            'postal_code',
            'phone',
            'email',
            'address',
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = mb_substr(trim((string) ($identity[$key] ?? '')), 0, $key === 'address' ? 500 : 180);
        }

        return $out;
    }

    public static function normalizeTax(mixed $value): float
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_TAX_PERCENT;
        }

        return max(0.0, min(100.0, round((float) $value, 2)));
    }
}
