<?php

namespace App\Application\Crm\Invoices;

use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Models\CrmInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQuery
{
    public function __construct(private InvoiceAuthorization $auth) {}

    /**
     * @param  array{
     *     tab?: string,
     *     type?: string,
     *     status?: string,
     *     search?: string,
     *     customer_id?: int|string|null,
     *     from_date?: string|null
     * }  $filters
     */
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

    /**
     * @param  array{
     *     tab?: string,
     *     type?: string,
     *     status?: string,
     *     search?: string,
     *     customer_id?: int|string|null,
     *     from_date?: string|null
     * }  $filters
     * @return array{all: int, issued: int, drafts: int}
     */
    public function counts(int $organizationId, User $actor, array $filters = []): array
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        $base = $this->base($organizationId, [
            'search' => $filters['search'] ?? '',
            'type' => $filters['type'] ?? 'all',
            'customer_id' => $filters['customer_id'] ?? null,
            'from_date' => $filters['from_date'] ?? null,
        ]);

        return [
            'all' => (clone $base)->count(),
            'issued' => (clone $base)
                ->where('status', InvoiceStatus::Issued->value)
                ->where('document_type', InvoiceDocumentType::Invoice->value)
                ->count(),
            'drafts' => (clone $base)->where('status', InvoiceStatus::Draft->value)->count(),
        ];
    }

    public function findVisible(int $organizationId, User $actor, int $invoiceId): CrmInvoice
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        $invoice = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->with(['lines', 'customer.company', 'organization', 'createdBy'])
            ->find($invoiceId);

        if (! $invoice instanceof CrmInvoice) {
            abort(404);
        }

        return $invoice;
    }

    /**
     * @param  array{
     *     tab?: string,
     *     type?: string,
     *     status?: string,
     *     search?: string,
     *     customer_id?: int|string|null,
     *     from_date?: string|null
     * }  $filters
     */
    private function base(int $organizationId, array $filters): Builder
    {
        $query = CrmInvoice::query()->where('organization_id', $organizationId);

        $tab = (string) ($filters['tab'] ?? 'all');
        if ($tab === 'issued') {
            $query->where('status', InvoiceStatus::Issued->value)
                ->where('document_type', InvoiceDocumentType::Invoice->value);
        } elseif ($tab === 'drafts') {
            $query->where('status', InvoiceStatus::Draft->value);
        } else {
            $status = (string) ($filters['status'] ?? 'all');
            if ($status !== 'all' && InvoiceStatus::tryFrom($status)) {
                $query->where('status', $status);
            }
        }

        $type = (string) ($filters['type'] ?? 'all');
        if ($tab !== 'issued' && $type !== 'all' && InvoiceDocumentType::tryFrom($type)) {
            $query->where('document_type', $type);
        }

        $customerId = $filters['customer_id'] ?? null;
        if ($customerId !== null && $customerId !== '' && (int) $customerId > 0) {
            $query->where('customer_id', (int) $customerId);
        }

        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== '') {
            try {
                $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
            } catch (\Throwable) {
                // Ignore invalid date filters from the query string.
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('number', 'like', $term)
                    ->orWhere('buyer_name', 'like', $term)
                    ->orWhere('buyer_company', 'like', $term)
                    ->orWhere('buyer_phone', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        return $query;
    }
}
