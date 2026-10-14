<?php

namespace App\Livewire\Crm\Deals;

use App\Application\Crm\Deals\Actions\AcceptDealSuggestion;
use App\Application\Crm\Deals\Actions\ArchiveDeal;
use App\Application\Crm\Deals\Actions\AssignDealOwner;
use App\Application\Crm\Deals\Actions\ChangeDealStage;
use App\Application\Crm\Deals\Actions\CloseDealAsLost;
use App\Application\Crm\Deals\Actions\CloseDealAsWon;
use App\Application\Crm\Deals\Actions\DismissDealSuggestion;
use App\Application\Crm\Deals\Actions\EnsureDealCloseReasons;
use App\Application\Crm\Deals\Actions\LinkCallToDeal;
use App\Application\Crm\Deals\Actions\ReopenDeal;
use App\Application\Crm\Deals\Actions\ResolveDealBarrier;
use App\Application\Crm\Deals\Actions\UpdateDeal;
use App\Application\Crm\Deals\DealAging;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealHealthScorer;
use App\Application\Crm\Deals\DealQuery;
use App\Domain\Crm\Deals\DealBarrierStatus;
use App\Domain\Crm\Deals\DealCloseReasonKind;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Enums\UserRole;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\Call;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealBarrier;
use App\Models\CrmDealCloseReason;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\OrganizationUser;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('جزئیات معامله')]
class Show extends Component
{
    use ResolvesCrmContext;

    public int $dealId;

    public string $title = '';

    public string $amountRials = '';

    public string $description = '';

    public string $expectedCloseOn = '';

    public string $companyId = '';

    public string $stageId = '';

    public string $ownerId = '';

    public string $expectedOwnerId = '';

    public string $lostReason = '';

    public string $lostReasonCode = '';

    public string $wonReason = '';

    public string $wonReasonCode = '';

    public string $nextActionText = '';

    public string $nextActionDueOn = '';

    public string $linkCallId = '';

    public string $addCustomerQuery = '';

    public string $expectedUpdatedAt = '';

    public string $expectedVersion = '';

    /** @var list<int> */
    public array $customerIds = [];

    public string $primaryCustomerId = '';

    public function mount(int $deal): void
    {
        $this->dealId = $deal;
        $this->syncForm($this->loadDeal());
    }

    public function save(UpdateDeal $action): void
    {
        $updated = $action->handle($this->loadDeal(), $this->crmUser(), [
            'title' => $this->title,
            'amount_rials' => $this->amountRials,
            'description' => $this->description,
            'expected_close_on' => $this->expectedCloseOn,
            'company_id' => $this->companyId,
            'customer_ids' => $this->customerIds,
            'primary_customer_id' => $this->primaryCustomerId,
            'expected_updated_at' => $this->expectedUpdatedAt,
            'expected_version' => $this->expectedVersion,
            'next_action_text' => $this->nextActionText,
            'next_action_due_on' => $this->nextActionDueOn,
        ]);
        $this->syncForm($updated);
        $this->toast('معامله ذخیره شد.');
    }

    public function moveStage(ChangeDealStage $action): void
    {
        $deal = $this->loadDeal();
        $updated = $action->handle(
            $deal,
            $this->crmUser(),
            (int) $this->stageId,
            (int) $deal->crm_pipeline_stage_id,
            false,
            $this->expectedVersion,
        );
        $this->syncForm($updated);
        $this->toast('مرحله معامله تغییر کرد.');
    }

    public function assignOwner(AssignDealOwner $action): void
    {
        $ownerId = $this->ownerId === '' ? null : (int) $this->ownerId;
        $expected = $this->expectedOwnerId === '' ? null : (int) $this->expectedOwnerId;
        $updated = $action->handle($this->loadDeal(), $this->crmUser(), $ownerId, $expected, true, $this->expectedVersion);
        $this->syncForm($updated);
        $this->toast('مسئول معامله ثبت شد.');
    }

    public function closeWon(CloseDealAsWon $action): void
    {
        $this->syncForm($action->handle(
            $this->loadDeal(),
            $this->crmUser(),
            $this->wonReasonCode !== '' ? $this->wonReasonCode : null,
            $this->wonReason !== '' ? $this->wonReason : null,
            $this->expectedVersion,
        ));
        $this->wonReason = '';
        $this->wonReasonCode = '';
        $this->toast('معامله موفق ثبت شد.');
    }

    public function closeLost(CloseDealAsLost $action): void
    {
        $this->syncForm($action->handle(
            $this->loadDeal(),
            $this->crmUser(),
            $this->lostReason,
            $this->lostReasonCode !== '' ? $this->lostReasonCode : null,
            $this->expectedVersion,
        ));
        $this->lostReason = '';
        $this->lostReasonCode = '';
        $this->toast('معامله ناموفق ثبت شد.');
    }

    public function reopen(ReopenDeal $action): void
    {
        $this->syncForm($action->handle($this->loadDeal(), $this->crmUser(), $this->expectedVersion));
        $this->toast('معامله بازگشایی شد.');
    }

    public function resolveBarrier(int $barrierId, string $status, ResolveDealBarrier $action): void
    {
        $barrier = CrmDealBarrier::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->where('crm_deal_id', $this->dealId)
            ->find($barrierId);

        if (! $barrier instanceof CrmDealBarrier) {
            abort(404);
        }

        $resolved = DealBarrierStatus::tryFrom($status);
        if ($resolved === null) {
            $this->addError('barrier', 'وضعیت مانع معتبر نیست.');

            return;
        }

        $action->handle($barrier, $this->crmUser(), $resolved);
        app(DealHealthScorer::class)->persist($this->loadDeal());
        $this->toast('وضعیت مانع به‌روز شد.');
    }

    public function archive(ArchiveDeal $action): void
    {
        $action->handle($this->loadDeal(), $this->crmUser());
        $this->toast('معامله بایگانی شد.');
        $this->redirect(route($this->crmPortal().'.crm.deals.index'), navigate: true);
    }

    public function linkCall(LinkCallToDeal $action): void
    {
        $deal = $this->loadDeal();
        $call = Call::query()
            ->where('organization_id', $deal->organization_id)
            ->find((int) $this->linkCallId);

        if (! $call instanceof Call) {
            $this->addError('call', 'تماس انتخاب‌شده پیدا نشد.');

            return;
        }

        $action->handle($deal, $call, $this->crmUser(), 'manual', $call->conversationAnalysis);
        $this->linkCallId = '';
        $this->syncForm($deal->fresh() ?? $deal);
        $this->toast('تماس به معامله متصل شد.');
    }

    public function addCustomer(int $customerId): void
    {
        $customer = Customer::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($customerId);

        if (! $customer instanceof Customer || in_array($customer->id, $this->customerIds, true)) {
            return;
        }

        $this->customerIds[] = (int) $customer->id;

        if ($this->primaryCustomerId === '') {
            $this->primaryCustomerId = (string) $customer->id;
        }

        $this->addCustomerQuery = '';
    }

    public function removeCustomer(int $customerId): void
    {
        $this->customerIds = array_values(array_filter(
            $this->customerIds,
            fn (int $id): bool => $id !== $customerId,
        ));

        if ((int) $this->primaryCustomerId === $customerId) {
            $this->primaryCustomerId = $this->customerIds === [] ? '' : (string) $this->customerIds[0];
        }
    }

    public function acceptSuggestion(int $suggestionId, AcceptDealSuggestion $action): void
    {
        $suggestion = $this->suggestion($suggestionId);
        $deal = $action->handle($suggestion, $this->crmUser(), $this->dealId);

        if ((int) $deal->id !== $this->dealId) {
            $this->toast('پیشنهاد روی معامله جدید اعمال شد.');
            $this->redirect(route($this->crmPortal().'.crm.deals.show', $deal), navigate: true);

            return;
        }

        $this->syncForm($deal);
        $this->toast('پیشنهاد تأیید شد.');
    }

    public function dismissSuggestion(int $suggestionId, DismissDealSuggestion $action): void
    {
        $action->handle($this->suggestion($suggestionId), $this->crmUser());
        $this->toast('پیشنهاد رد شد.');
    }

    public function render(DealAuthorization $auth)
    {
        $deal = $this->loadDeal()->load([
            'pipeline',
            'stage',
            'company',
            'owner',
            'contacts.customer',
            'dealCalls.call',
            'dealCalls.analysis',
            'stageHistory.actor',
            'activities.actor',
            'externalLinks',
            'barriers.sourceCall',
            'barriers.sourceAnalysis',
        ]);
        $actor = $this->crmUser();
        $contactIds = $deal->contacts->pluck('customer_id')->map(fn ($id) => (int) $id)->all();
        $suggestions = CrmDealAiSuggestion::query()
            ->where('organization_id', $deal->organization_id)
            ->where('status', DealSuggestionStatus::Pending)
            ->latest('id')
            ->limit(20)
            ->get()
            ->filter(function (CrmDealAiSuggestion $suggestion) use ($deal, $contactIds): bool {
                if ((int) $suggestion->customer_id && in_array((int) $suggestion->customer_id, $contactIds, true)) {
                    return true;
                }

                $candidates = array_map('intval', (array) ($suggestion->payload['candidate_deal_ids'] ?? []));

                return in_array((int) $deal->id, $candidates, true);
            });

        app(EnsureDealCloseReasons::class)->handle((int) $deal->organization_id);
        $health = $deal->health_calculated_at
            ? [
                'score' => $deal->health_score,
                'level' => $deal->health_level,
                'factors' => $deal->health_factors_json ?? [],
                'calculated_at' => $deal->health_calculated_at,
            ]
            : app(DealHealthScorer::class)->calculate($deal);

        return view('livewire.crm.deals.show', [
            'deal' => $deal,
            'portal' => $this->crmPortal(),
            'aging' => app(DealAging::class)->forDeal($deal),
            'health' => $health,
            'lostReasons' => CrmDealCloseReason::query()
                ->where('organization_id', $deal->organization_id)
                ->where('kind', DealCloseReasonKind::Lost)
                ->where('is_active', true)
                ->orderBy('position')
                ->get(),
            'wonReasons' => CrmDealCloseReason::query()
                ->where('organization_id', $deal->organization_id)
                ->where('kind', DealCloseReasonKind::Won)
                ->where('is_active', true)
                ->orderBy('position')
                ->get(),
            'stages' => CrmPipelineStage::query()->where('crm_pipeline_id', $deal->crm_pipeline_id)->orderBy('position')->get(),
            'owners' => OrganizationUser::query()->where('organization_id', $deal->organization_id)->where('is_active', true)->orderBy('last_name')->get(),
            'calls' => $contactIds === [] ? collect() : Call::query()
                ->where('organization_id', $deal->organization_id)
                ->whereIn('customer_id', $contactIds)
                ->whereDoesntHave('dealLink')
                ->latest('id')
                ->limit(15)
                ->get(),
            'suggestions' => $suggestions,
            'selectedCustomers' => Customer::query()
                ->where('organization_id', $deal->organization_id)
                ->whereIn('id', $this->customerIds === [] ? [0] : $this->customerIds)
                ->get(),
            'customerChoices' => mb_strlen(trim($this->addCustomerQuery)) >= 2
                ? Customer::query()->forOrganization((int) $deal->organization_id)
                    ->where(function ($inner): void {
                        $term = '%'.addcslashes(trim($this->addCustomerQuery), '%_\\').'%';
                        $inner->where('name', 'like', $term)->orWhere('phone_number', 'like', $term);
                    })
                    ->limit(8)
                    ->get()
                : collect(),
            'canUpdate' => $auth->canUpdate($actor, $deal),
            'canAssignOwner' => $auth->canAssignOwner($actor, $deal),
            'canReopen' => $auth->canReopen($actor, $deal),
            'isManager' => $actor->role === UserRole::Employer,
            'backUrl' => route($this->crmPortal().'.crm.deals.index'),
        ])->layout($this->crmLayout());
    }

    private function loadDeal(): CrmDeal
    {
        return app(DealQuery::class)->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->dealId);
    }

    private function syncForm(CrmDeal $deal): void
    {
        $deal->loadMissing('contacts');
        $this->title = $deal->title;
        $this->amountRials = $deal->amount_rials === null ? '' : (string) $deal->amount_rials;
        $this->description = (string) $deal->description;
        $this->expectedCloseOn = $deal->expected_close_on?->toDateString() ?? '';
        $this->companyId = $deal->customer_company_id ? (string) $deal->customer_company_id : '';
        $this->stageId = (string) $deal->crm_pipeline_stage_id;
        $this->ownerId = $deal->owner_organization_user_id ? (string) $deal->owner_organization_user_id : '';
        $this->expectedOwnerId = $this->ownerId;
        $this->customerIds = $deal->contacts->pluck('customer_id')->map(fn ($id) => (int) $id)->all();
        $primary = $deal->contacts->firstWhere('is_primary', true);
        $this->primaryCustomerId = $primary ? (string) $primary->customer_id : '';
        $this->expectedUpdatedAt = $deal->updated_at?->toDateTimeString() ?? '';
        $this->expectedVersion = (string) ($deal->version ?? 1);
        $this->nextActionText = (string) ($deal->next_action_text ?? '');
        $this->nextActionDueOn = $deal->next_action_due_on?->toDateString() ?? '';
    }

    private function suggestion(int $suggestionId): CrmDealAiSuggestion
    {
        $suggestion = CrmDealAiSuggestion::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($suggestionId);

        if (! $suggestion instanceof CrmDealAiSuggestion) {
            abort(404);
        }

        return $suggestion;
    }
}
