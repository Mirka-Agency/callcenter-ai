<?php

namespace App\Application\Crm\Invoices\Actions;

use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Models\CrmInvoice;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueInvoice
{
    public function __construct(private InvoiceAuthorization $auth) {}

    public function handle(CrmInvoice $invoice, User $actor, bool $notifySms = false): CrmInvoice
    {
        $organizationId = (int) $invoice->organization_id;
        $this->auth->assertInternalCrm($organizationId);
        $this->auth->authorize($this->auth->canIssue($actor, $invoice));

        if ($invoice->status === InvoiceStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => 'این سند قبلاً صادر شده است.',
            ]);
        }

        if ($invoice->lines()->count() === 0) {
            throw ValidationException::withMessages([
                'lines' => 'برای صدور حداقل یک ردیف لازم است.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $organizationId, $notifySms): CrmInvoice {
            $locked = CrmInvoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof CrmInvoice) {
                abort(404);
            }

            if ($locked->status === InvoiceStatus::Issued) {
                throw ValidationException::withMessages([
                    'status' => 'این سند قبلاً صادر شده است.',
                ]);
            }

            $locked->fill([
                'status' => InvoiceStatus::Issued,
                'number' => $this->nextNumber($organizationId, $locked->document_type),
                'issued_at' => now(),
                'notify_sms_on_issue' => $notifySms,
            ]);
            $locked->save();

            return $locked->fresh(['lines', 'customer', 'organization']) ?? $locked;
        });
    }

    private function nextNumber(int $organizationId, InvoiceDocumentType $type): string
    {
        $year = JalaliDate::format(now(), 'Y', '1400');
        $prefix = $type->numberPrefix().'-'.$year.'-';

        $latest = CrmInvoice::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('document_type', $type->value)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
