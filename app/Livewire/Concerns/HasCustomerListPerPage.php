<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait HasCustomerListPerPage
{
    public const DEFAULT_PER_PAGE = 10;

    public const MIN_PER_PAGE = 1;

    public const MAX_PER_PAGE = 100;

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = self::DEFAULT_PER_PAGE;

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);
        $this->resetPage();
    }

    public function currentPerPage(): int
    {
        return $this->perPage = $this->normalizePerPage($this->perPage);
    }

    private function normalizePerPage(mixed $value): int
    {
        $value = (int) $value;

        if ($value < self::MIN_PER_PAGE) {
            return self::DEFAULT_PER_PAGE;
        }

        return min(self::MAX_PER_PAGE, $value);
    }
}
