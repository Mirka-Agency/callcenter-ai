<?php

namespace App\Services\Demo;

final class DemoCleanupSummary
{
    public function __construct(
        public int $organizations = 0,
        public int $users = 0,
        public int $memberships = 0,
        public int $customers = 0,
        public int $calls = 0,
        public int $analyses = 0,
        public int $activities = 0,
        public int $integrations = 0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function totalRecords(): int
    {
        return $this->organizations
            + $this->users
            + $this->memberships
            + $this->customers
            + $this->calls
            + $this->analyses
            + $this->activities
            + $this->integrations;
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    public function breakdown(): array
    {
        return array_values(array_filter([
            ['label' => 'organizations', 'count' => $this->organizations],
            ['label' => 'users', 'count' => $this->users],
            ['label' => 'memberships', 'count' => $this->memberships],
            ['label' => 'customers', 'count' => $this->customers],
            ['label' => 'calls', 'count' => $this->calls],
            ['label' => 'analyses', 'count' => $this->analyses],
            ['label' => 'activities', 'count' => $this->activities],
            ['label' => 'integrations', 'count' => $this->integrations],
        ], fn (array $row): bool => $row['count'] > 0));
    }

    public function toFilamentDescription(): string
    {
        return $this->formatModalDescription(includeScopeNote: false);
    }

    public function toDeleteAllModalDescription(): string
    {
        return $this->formatModalDescription(includeScopeNote: true);
    }

    private function formatModalDescription(bool $includeScopeNote): string
    {
        if ($this->organizations === 0) {
            return __('filament.demo_cleanup.no_demo_data');
        }

        $sections = [
            __('filament.demo_cleanup.modal_irreversible_warning'),
        ];

        if ($includeScopeNote) {
            $sections[] = __('filament.demo_cleanup.modal_scope_note');
        }

        $sections[] = __('filament.demo_cleanup.modal_intro');

        $breakdown = collect($this->breakdown())
            ->map(fn (array $row): string => '• '.__('filament.demo_cleanup.counts.'.$row['label']).': '.number_format($row['count']))
            ->all();

        $sections[] = implode("\n", $breakdown);
        $sections[] = __('filament.demo_cleanup.modal_total', ['total' => number_format($this->totalRecords())]);
        $sections[] = __('filament.demo_cleanup.modal_confirm_prompt');

        return implode("\n\n", $sections);
    }
}
