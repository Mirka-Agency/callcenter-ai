<?php

namespace App\Application\Customer\Actions;

use App\Models\Customer;
use App\Services\CustomerProfileUpdateService;

class UpdateCustomer
{
    public function __construct(
        private CustomerProfileUpdateService $profiles,
    ) {}

    /**
     * @param  array{name?: ?string, company_name?: ?string, customer_company_id?: ?int, phone_number?: string, email?: ?string, job_title?: ?string}  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        return $this->profiles->update($customer, $data);
    }
}
