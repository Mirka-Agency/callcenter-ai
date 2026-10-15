<?php

namespace App\Application\Crm\Invoices;

use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Models\CrmInvoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQuery
{
    public function __construct(private InvoiceAuthorization $auth) {}

    /** @param  array{type?: string, search?: string}  $filters */
    public function paginate(int $organizationId, User $actor, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        return $this->base($organizationId, $filters)
            ->with(['customer'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @param  array{type?: string, search?: string}  $filters */
    public function counts(int $organizationId, User $actor, array $filters = []): array
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        $base = $this->base($organizationId, [
            'search' => $filters['search'] ?? '',
        ]);

        return [
            'all' => (clone $base)->count(),
            'proforma' => (clone $base)->where('document_type', InvoiceDocumentType::Proforma->value)->count(),
            'invoice' => (clone $base)->where('document_type', InvoiceDocumentType::Invoice->value)->count(),
        ];
    }

    public function findVisible(int $organizationId, User $actor, int $invoiceId): CrmInvoice
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        $invoice = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->with(['lines', 'customer', 'organization'])
            ->find($invoiceId);

        if (! $invoice instanceof CrmInvoice) {
            abort(404);
        }

        return $invoice;
    }

    /** @param  array{type?: string, search?: string}  $filters */
    private function base(int $organizationId, array $filters): Builder
    {
        $query = CrmInvoice::query()->where('organization_id', $organizationId);

        $type = (string) ($filters['type'] ?? 'all');
        if ($type !== 'all' && InvoiceDocumentType::tryFrom($type)) {
            $query->where('document_type', $type);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('number', 'like', $term)
                    ->orWhere('buyer_name', 'like', $term)
                    ->orWhere('buyer_company', 'like', $term)
                    ->orWhere('buyer_phone', 'like', $term);
            });
        }

        return $query;
    }
}
