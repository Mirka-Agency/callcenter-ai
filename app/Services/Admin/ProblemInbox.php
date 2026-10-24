<?php

namespace App\Services\Admin;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Crm\Enums\CrmLogStatus;
use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Filament\Pages\Sales\LeadPipeline;
use App\Filament\Pages\Support\OperationsHealth;
use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use App\Filament\Resources\FailedQueueJobs\FailedQueueJobResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\BillingInvoice;
use App\Models\Call;
use App\Models\ConsultationRequest;
use App\Models\ContactMessage;
use App\Models\CrmSyncLog;
use App\Models\CustomerIdentityReview;
use App\Models\DemoRequest;
use App\Models\FailedQueueJob;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoipWebhookLog;
use App\Services\PlatformAnalytics\OperationsMetrics;
use App\Services\PlatformAnalytics\OrganizationHealth;
use App\Services\PlatformAnalytics\OrganizationHealthScorer;

/**
 * Everything an admin must act on, collected from operations, customers, sales and finance.
 */
class ProblemInbox
{
    public const DANGER = 'danger';

    public const WARNING = 'warning';

    /**
     * @return list<array{key: string, area: AdminArea, severity: string, title: string, detail: string|null, count: int, url: string|null}>
     */
    public function items(?User $viewer = null): array
    {
        $items = [
            ...$this->operationItems(),
            ...$this->organizationItems(),
            ...$this->salesItems(),
            ...$this->financeItems(),
        ];

        if ($viewer) {
            $items = array_values(array_filter($items, fn (array $item): bool => $viewer->hasAdminArea($item['area'])));
        }

        usort($items, fn (array $a, array $b): int => [$a['severity'] === self::DANGER ? 0 : 1, -$a['count']] <=> [$b['severity'] === self::DANGER ? 0 : 1, -$b['count']]);

        return $items;
    }

    public function count(?User $viewer = null): int
    {
        return count($this->items($viewer));
    }

    private function operationItems(): array
    {
        $metrics = app(OperationsMetrics::class);
        $items = [];

        foreach ($metrics->failingProviders(now()->subDay()) as $provider) {
            $items[] = $this->item(
                'provider:'.$provider['kind'].':'.$provider['channel'].':'.$provider['provider'],
                AdminArea::Support,
                self::DANGER,
                __('admin.inbox.failing_provider', ['provider' => $provider['provider']]),
                __('admin.inbox.failing_provider_detail', [
                    'rate' => round(($provider['failure_rate'] ?? 0) * 100),
                    'organizations' => $provider['failing_organizations'],
                ]),
                $provider['failed'],
                OperationsHealth::getUrl(),
            );
        }

        if (($stuck = $metrics->stuckCalls()) > 0) {
            $items[] = $this->item('stuck_calls', AdminArea::Support, self::WARNING, __('admin.inbox.stuck_calls'), __('admin.operations.stuck_calls_description'), $stuck, OperationsHealth::getUrl());
        }

        if (($failedJobs = FailedQueueJob::query()->count()) > 0) {
            $items[] = $this->item('failed_jobs', AdminArea::Support, self::WARNING, __('admin.inbox.failed_jobs'), null, $failedJobs, FailedQueueJobResource::getUrl('index'));
        }

        return $items;
    }

    private function organizationItems(): array
    {
        $items = [];
        $since = now()->subDay();

        $failedCalls = Call::query()
            ->where('processing_status', CallProcessingStatus::Failed)
            ->where('updated_at', '>=', $since)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        $voipErrors = VoipWebhookLog::query()
            ->join('organization_voip_connections', 'organization_voip_connections.id', '=', 'voip_webhook_logs.organization_voip_connection_id')
            ->where('voip_webhook_logs.status', VoipLogStatus::Failed)
            ->where('voip_webhook_logs.created_at', '>=', $since)
            ->groupBy('organization_voip_connections.organization_id')
            ->selectRaw('organization_voip_connections.organization_id as organization_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        $crmErrors = CrmSyncLog::query()
            ->join('organization_crm_connections', 'organization_crm_connections.id', '=', 'crm_sync_logs.organization_crm_connection_id')
            ->where('crm_sync_logs.status', CrmLogStatus::Failed)
            ->where('crm_sync_logs.created_at', '>=', $since)
            ->groupBy('organization_crm_connections.organization_id')
            ->selectRaw('organization_crm_connections.organization_id as organization_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        $identityReviews = CustomerIdentityReview::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(3))
            ->groupBy('organization_id')
            ->selectRaw('organization_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        $ids = collect([$failedCalls, $voipErrors, $crmErrors, $identityReviews])->flatMap(fn ($map) => $map->keys())->unique();
        $titles = Organization::query()->whereKey($ids)->where('is_demo', false)->pluck('title', 'id');

        foreach ($titles as $id => $title) {
            $integration = (int) ($voipErrors[$id] ?? 0) + (int) ($crmErrors[$id] ?? 0);
            $url = OrganizationResource::getUrl('overview', ['record' => $id]);

            if ($integration > 0) {
                $items[] = $this->item("integration:{$id}", AdminArea::Support, self::DANGER, __('admin.inbox.integration_errors', ['organization' => $title]), __('admin.inbox.last_24h'), $integration, $url);
            }

            if (($failed = (int) ($failedCalls[$id] ?? 0)) > 0) {
                $items[] = $this->item("failed_calls:{$id}", AdminArea::Support, self::WARNING, __('admin.inbox.failed_calls', ['organization' => $title]), __('admin.inbox.last_24h'), $failed, $url);
            }

            if (($reviews = (int) ($identityReviews[$id] ?? 0)) > 0) {
                $items[] = $this->item("identity_reviews:{$id}", AdminArea::Support, self::WARNING, __('admin.inbox.identity_reviews', ['organization' => $title]), __('admin.inbox.identity_reviews_detail'), $reviews, $url);
            }
        }

        $critical = app(OrganizationHealthScorer::class)->atRisk(50)
            ->filter(fn (OrganizationHealth $health): bool => $health->band() === OrganizationHealth::CRITICAL);
        $criticalTitles = Organization::query()->whereKey($critical->pluck('organizationId'))->pluck('title', 'id');

        foreach ($critical as $health) {
            $items[] = $this->item(
                "critical_health:{$health->organizationId}",
                AdminArea::Support,
                self::WARNING,
                __('admin.inbox.critical_health', ['organization' => $criticalTitles[$health->organizationId] ?? '#'.$health->organizationId]),
                implode('، ', $health->reasonLabels()),
                $health->score,
                OrganizationResource::getUrl('overview', ['record' => $health->organizationId]),
            );
        }

        return $items;
    }

    private function salesItems(): array
    {
        $breaching = 0;
        $followUps = 0;

        foreach ([DemoRequest::class, ConsultationRequest::class, ContactMessage::class] as $model) {
            $breaching += $model::query()->breachingSla()->count();
            $followUps += $model::query()->followUpDue()->whereNull('archived_at')->count();
        }

        $items = [];

        if ($breaching > 0) {
            $items[] = $this->item('leads_sla', AdminArea::Sales, self::DANGER, __('admin.inbox.leads_breaching_sla', ['hours' => DemoRequest::SLA_HOURS]), null, $breaching, LeadPipeline::getUrl());
        }

        if ($followUps > 0) {
            $items[] = $this->item('leads_follow_up', AdminArea::Sales, self::WARNING, __('admin.inbox.leads_follow_up_due'), null, $followUps, LeadPipeline::getUrl());
        }

        return $items;
    }

    private function financeItems(): array
    {
        $overdue = BillingInvoice::query()->overdue()->count();

        return $overdue === 0 ? [] : [
            $this->item('overdue_invoices', AdminArea::Finance, self::DANGER, __('admin.inbox.overdue_invoices'), null, $overdue, BillingInvoiceResource::getUrl('index')),
        ];
    }

    private function item(string $key, AdminArea $area, string $severity, string $title, ?string $detail, int $count, ?string $url): array
    {
        return compact('key', 'area', 'severity', 'title', 'detail', 'count', 'url');
    }
}
