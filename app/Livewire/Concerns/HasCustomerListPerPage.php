<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait HasCustomerListPerPage
{
    public const PER_PAGE_OPTIONS = [20, 50, 100];

    public const PER_PAGE = 20;

    #[Url(as: 'per_page', except: 20)]
    public int $perPage = 20;

    public function updatedPerPage(): void
    {
        $this->perPage = $this->currentPerPage();
        $this->resetPage();
    }

    public function currentPerPage(): int
    {
        return in_array($this->perPage, self::PER_PAGE_OPTIONS, true)
            ? $this->perPage
            : self::PER_PAGE;
    }
}
