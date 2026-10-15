<?php

namespace App\Application\Crm\Analytics;

use App\Application\Crm\Deals\DealQuery;
use App\Application\Crm\Tasks\TaskQuery;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Domain\Crm\Products\ProductType;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\CrmDealCall;
use App\Models\CrmInvoice;
use App\Models\CrmInvoiceLine;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmProduct;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonInterface;

class CrmStatsOverview
{
    public function __construct(
        private DealQuery $deals,
        private TaskQuery $tasks,
    ) {}

    /** @return array<string, mixed> */
    public function summarize(int $organizationId, User $actor, ?int $pipelineId = null): array
    {
        $now = now();
        $start7 = $now->copy()->subDays(6)->startOfDay();
        $startToday = $now->copy()->startOfDay();
        $endToday = $now->copy()->endOfDay();
        $in24h = $now->copy()->addDay();

        $visibleDeals = $this->deals->visible($organizationId, $actor);
        $pipeline = $this->resolvePipeline($organizationId, $pipelineId);

        if ($pipeline instanceof CrmPipeline) {
            $visibleDeals->where('crm_pipeline_id', $pipeline->id);
        }

        $open = (clone $visibleDeals)->where('status', DealStatus::Open);
        $openCount = (clone $open)->count();
        $openAmount = (int) ((clone $open)->sum('amount_rials') ?? 0);

        $won7 = (clone $visibleDeals)
            ->where('status', DealStatus::Won)
            ->where('closed_at', '>=', $start7);
        $lost7 = (clone $visibleDeals)
            ->where('status', DealStatus::Lost)
            ->where('closed_at', '>=', $start7);
        $won7Count = (clone $won7)->count();
        $lost7Count = (clone $lost7)->count();
        $closed7 = $won7Count + $lost7Count;
        $won7Amount = (int) ((clone $won7)->sum('amount_rials') ?? 0);

        $newDeals7 = (clone $visibleDeals)->where('created_at', '>=', $start7)->count();

        $wonToday = (clone $visibleDeals)
            ->where('status', DealStatus::Won)
            ->whereBetween('closed_at', [$startToday, $endToday]);
        $wonTodayCount = (clone $wonToday)->count();
        $wonTodayAmount = (int) ((clone $wonToday)->sum('amount_rials') ?? 0);
        $newToday = (clone $visibleDeals)->whereBetween('created_at', [$startToday, $endToday])->count();

        $overdueClose = (clone $open)
            ->whereNotNull('expected_close_on')
            ->whereDate('expected_close_on', '<', $now->toDateString())
            ->count();

        $visibleTasks = $this->tasks->visible($organizationId, $actor);
        $openTasks = (clone $visibleTasks)->whereIn('status', [
            TaskStatus::Pending->value,
            TaskStatus::InProgress->value,
        ]);
        $overdueReminders = (clone $openTasks)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();
        $dueNext24h = (clone $openTasks)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', $now)
            ->where('due_at', '<=', $in24h)
            ->count();
        $completed7 = (clone $visibleTasks)
            ->where('status', TaskStatus::Completed)
            ->where('completed_at', '>=', $start7)
            ->count();

        $newContacts7 = Customer::query()
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $start7)
            ->count();
        $newContactsToday = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$startToday, $endToday])
            ->count();

        $issued7 = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Issued)
            ->where('issued_at', '>=', $start7);
        $issued7Amount = (int) ((clone $issued7)->sum('total_rials') ?? 0);
        $issuedInvoiceCount = (clone $issued7)->where('document_type', InvoiceDocumentType::Invoice)->count();
        $issuedProformaCount = (clone $issued7)->where('document_type', InvoiceDocumentType::Proforma)->count();

        $draftDocs = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Draft)
            ->count();
        $openIssuedProformas = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Issued)
            ->where('document_type', InvoiceDocumentType::Proforma)
            ->count();

        $catalogTotal = CrmProduct::query()->where('organization_id', $organizationId)->count();
        $catalogActive = CrmProduct::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->count();
        $catalogProducts = CrmProduct::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('type', ProductType::Product)
            ->count();
        $catalogServices = CrmProduct::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('type', ProductType::Service)
            ->count();

        $callInsights = $this->callInsights($organizationId, $actor, $start7);
        $stages = $this->stageValues($pipeline, $open);
        $trend = $this->sevenDayTrend($organizationId, $actor, $pipeline, $start7, $now);
        $topInvoiceItems = $this->topInvoiceItems($organizationId, $start7);

        return [
            'pipeline' => $pipeline,
            'open_count' => $openCount,
            'open_amount_rials' => $openAmount,
            'win_rate_7d' => $closed7 > 0 ? round(($won7Count / $closed7) * 100, 1) : null,
            'won_7d_count' => $won7Count,
            'lost_7d_count' => $lost7Count,
            'won_7d_amount_rials' => $won7Amount,
            'new_deals_7d' => $newDeals7,
            'issued_7d_amount_rials' => $issued7Amount,
            'issued_7d_invoice_count' => $issuedInvoiceCount,
            'issued_7d_proforma_count' => $issuedProformaCount,
            'overdue_reminders' => $overdueReminders,
            'reminders_due_24h' => $dueNext24h,
            'reminders_completed_7d' => $completed7,
            'overdue_close_count' => $overdueClose,
            'new_contacts_7d' => $newContacts7,
            'new_contacts_today' => $newContactsToday,
            'crm_call_summaries_7d' => $callInsights['summary_count'],
            'draft_documents' => $draftDocs,
            'open_issued_proformas' => $openIssuedProformas,
            'catalog_active' => $catalogActive,
            'catalog_total' => $catalogTotal,
            'catalog_products' => $catalogProducts,
            'catalog_services' => $catalogServices,
            'won_today_amount_rials' => $wonTodayAmount,
            'won_today_count' => $wonTodayCount,
            'new_deals_today' => $newToday,
            'stages' => $stages,
            'trend' => $trend,
            'top_invoice_items' => $topInvoiceItems,
            'call_reasons' => $callInsights['reasons'],
            'call_sentiments' => $callInsights['sentiments'],
        ];
    }

    private function resolvePipeline(int $organizationId, ?int $pipelineId): ?CrmPipeline
    {
        if ($pipelineId) {
            $pipeline = CrmPipeline::query()
                ->where('organization_id', $organizationId)
                ->whereKey($pipelineId)
                ->first();

            if ($pipeline instanceof CrmPipeline) {
                return $pipeline;
            }
        }

        return CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    /** @return list<array{id: int, name: string, color: string, count: int, amount_rials: int}> */
    private function stageValues(?CrmPipeline $pipeline, $open): array
    {
        if (! $pipeline instanceof CrmPipeline) {
            return [];
        }

        $stages = CrmPipelineStage::query()
            ->where('crm_pipeline_id', $pipeline->id)
            ->orderBy('position')
            ->get();

        $sums = (clone $open)
            ->select('crm_pipeline_stage_id')
            ->selectRaw('COUNT(*) as deal_count')
            ->selectRaw('COALESCE(SUM(amount_rials), 0) as amount_sum')
            ->groupBy('crm_pipeline_stage_id')
            ->get()
            ->keyBy('crm_pipeline_stage_id');

        return $stages->map(function (CrmPipelineStage $stage) use ($sums): array {
            $row = $sums->get($stage->id);

            return [
                'id' => (int) $stage->id,
                'name' => $stage->name,
                'color' => $stage->color ?: '#6366F1',
                'count' => (int) ($row->deal_count ?? 0),
                'amount_rials' => (int) ($row->amount_sum ?? 0),
            ];
        })->all();
    }

    /**
     * @return list<array{date: string, new_count: int, won_count: int, invoice_amount_rials: int}>
     */
    private function sevenDayTrend(
        int $organizationId,
        User $actor,
        ?CrmPipeline $pipeline,
        CarbonInterface $start7,
        CarbonInterface $now,
    ): array {
        $visible = $this->deals->visible($organizationId, $actor);
        if ($pipeline instanceof CrmPipeline) {
            $visible->where('crm_pipeline_id', $pipeline->id);
        }

        $newByDay = (clone $visible)
            ->where('created_at', '>=', $start7)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $wonByDay = (clone $visible)
            ->where('status', DealStatus::Won)
            ->where('closed_at', '>=', $start7)
            ->selectRaw('DATE(closed_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $invoiceByDay = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Issued)
            ->where('issued_at', '>=', $start7)
            ->selectRaw('DATE(issued_at) as day')
            ->selectRaw('COALESCE(SUM(total_rials), 0) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];
        for ($cursor = $start7->copy()->startOfDay(); $cursor->lte($now); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $days[] = [
                'date' => $key,
                'new_count' => (int) ($newByDay[$key] ?? 0),
                'won_count' => (int) ($wonByDay[$key] ?? 0),
                'invoice_amount_rials' => (int) ($invoiceByDay[$key] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return array{summary_count: int, reasons: list<array{label: string, count: int}>, sentiments: list<array{label: string, count: int}>}
     */
    private function callInsights(int $organizationId, User $actor, CarbonInterface $start7): array
    {
        $dealIds = $this->deals->visible($organizationId, $actor)->select('crm_deals.id');

        $links = CrmDealCall::query()
            ->where('crm_deal_calls.organization_id', $organizationId)
            ->whereIn('crm_deal_calls.crm_deal_id', $dealIds)
            ->whereNotNull('crm_deal_calls.conversation_analysis_id')
            ->where('crm_deal_calls.created_at', '>=', $start7)
            ->with(['analysis:id,sentiment,customer_insights_json,summary,analyzed_at'])
            ->limit(500)
            ->get();

        $reasonCounts = [];
        $sentimentCounts = [];

        foreach ($links as $link) {
            $analysis = $link->analysis;
            if (! $analysis) {
                continue;
            }

            $insights = is_array($analysis->customer_insights_json) ? $analysis->customer_insights_json : [];
            $intent = trim((string) ($insights['intent'] ?? ''));
            if ($intent === '') {
                $intent = 'سایر';
            }
            $reasonCounts[$intent] = ($reasonCounts[$intent] ?? 0) + 1;

            $sentiment = $analysis->sentiment instanceof AnalysisSentiment
                ? $analysis->sentiment
                : AnalysisSentiment::fromAnalysisValue($analysis->sentiment);
            $label = $sentiment->label();
            $sentimentCounts[$label] = ($sentimentCounts[$label] ?? 0) + 1;
        }

        arsort($reasonCounts);
        arsort($sentimentCounts);

        $reasons = collect($reasonCounts)
            ->take(8)
            ->map(fn (int $count, string $label): array => ['label' => $label, 'count' => $count])
            ->values()
            ->all();

        $sentiments = collect($sentimentCounts)
            ->map(fn (int $count, string $label): array => ['label' => $label, 'count' => $count])
            ->values()
            ->all();

        return [
            'summary_count' => $links->count(),
            'reasons' => $reasons,
            'sentiments' => $sentiments,
        ];
    }

    /** @return list<array{label: string, amount_rials: int, quantity: float}> */
    private function topInvoiceItems(int $organizationId, CarbonInterface $start7): array
    {
        $invoiceIds = CrmInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Issued)
            ->where('document_type', InvoiceDocumentType::Invoice)
            ->where('issued_at', '>=', $start7)
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return [];
        }

        $lines = CrmInvoiceLine::query()
            ->where('organization_id', $organizationId)
            ->whereIn('crm_invoice_id', $invoiceIds)
            ->get(['description', 'amount_rials', 'quantity']);

        /** @var array<string, array{label: string, amount_rials: int, quantity: float}> $grouped */
        $grouped = [];

        foreach ($lines as $line) {
            $label = trim((string) ($line->description ?? ''));
            if ($label === '') {
                $label = 'بدون عنوان';
            }

            if (! isset($grouped[$label])) {
                $grouped[$label] = [
                    'label' => $label,
                    'amount_rials' => 0,
                    'quantity' => 0.0,
                ];
            }

            $grouped[$label]['amount_rials'] += (int) $line->amount_rials;
            $grouped[$label]['quantity'] += (float) $line->quantity;
        }

        return collect($grouped)
            ->sortByDesc('amount_rials')
            ->take(8)
            ->values()
            ->all();
    }
}
