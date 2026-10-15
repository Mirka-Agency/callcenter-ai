<?php

namespace App\Application\Crm\Invoices\Actions;

use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Models\CrmInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteInvoice
{
    public function __construct(private InvoiceAuthorization $auth) {}

    public function handle(CrmInvoice $invoice, User $actor): void
    {
        $this->auth->assertInternalCrm((int) $invoice->organization_id);
        $this->auth->authorize($this->auth->canDelete($actor, $invoice));

        DB::transaction(function () use ($invoice): void {
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }
}
