<?php

namespace App\Application\Customer\Actions;

use App\Models\Customer;
use App\Services\CustomerProfileUpdateService;

class CreateCustomer
{
    public function __construct(
        private CustomerProfileUpdateService $profiles,
    ) {}

    /**
     * @param  array{name?: ?string, company_name?: ?string, customer_company_id?: ?int, phone_number?: string, email?: ?string, job_title?: ?string}  $data
     */
    public function handle(int $organizationId, array $data): Customer
    {
        return $this->profiles->create($organizationId, $data);
    }
}
