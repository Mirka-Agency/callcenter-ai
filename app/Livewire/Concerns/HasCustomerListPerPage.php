<?php

namespace App\Livewire\Concerns;

trait HasCustomerListPerPage
{
    public const PER_PAGE = 50;

    public function currentPerPage(): int
    {
        return self::PER_PAGE;
    }
}
