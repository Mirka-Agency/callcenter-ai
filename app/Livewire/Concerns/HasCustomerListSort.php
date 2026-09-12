<?php

namespace App\Livewire\Concerns;

use App\Support\CustomerListSort;
use Livewire\Attributes\Url;

trait HasCustomerListSort
{
    #[Url(as: 'sort', except: 'last_contact')]
    public string $sort = 'last_contact';

    public function updatedSort(): void
    {
        $this->sort = CustomerListSort::fromInput($this->sort)->value;
        $this->resetPage();
    }
}
