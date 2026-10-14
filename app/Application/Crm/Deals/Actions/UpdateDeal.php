<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealConcurrency;
use App\Application\Crm\Deals\DealContacts;
use App\Application\Crm\Deals\DealHealthScorer;
use App\Application\Crm\Deals\DealMoney;
use App\Application\Crm\Deals\DealState;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\Events\DealUpdated;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDeal
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
        private DealContacts $contacts,
        private DealAudit $audit,
        private DealConcurrency $concurrency,
        private DealHealthScorer $health,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(CrmDeal $deal, User $actor, array $input): CrmDeal
    {
        $this->auth->authorize($this->auth->canUpdate($actor, $deal));

        $title = trim((string) ($input['title'] ?? ''));

        if ($title === '' || mb_strlen($title) > 180) {
            throw ValidationException::withMessages([
                'title' => 'عنوان معامله الزامی است و حداکثر ۱۸۰ نویسه دارد.',
            ]);
        }

        $amount = DealMoney::parseRials($input['amount_rials'] ?? null);
        $description = $this->text($input['description'] ?? null);
        $expectedClose = $this->date($input['expected_close_on'] ?? null);
        $companyId = $this->nullableInt($input['company_id'] ?? null);
        $customerIds = array_values(array_filter(array_map('intval', (array) ($input['customer_ids'] ?? []))));
        $primaryId = $this->nullableInt($input['primary_customer_id'] ?? null);
        $organizationId = (int) $deal->organization_id;

        foreach ($customerIds as $customerId) {
            $this->tenant->customer($organizationId, $customerId);
        }

        if ($companyId !== null) {
            $this->tenant->company($organizationId, $companyId);
        }

        return DB::transaction(function () use ($deal, $actor, $title, $amount, $description, $expectedClose, $companyId, $customerIds, $primaryId, $input): CrmDeal {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before update.');
            }

            $this->concurrency->assertExpectedUpdatedAt($locked, $input['expected_updated_at'] ?? null);
            $this->concurrency->assertExpectedVersion($locked, $input['expected_version'] ?? null);

            $previous = $locked->auditSnapshot();
            $previousAmount = $locked->amount_rials;
            $nextActionText = $this->text($input['next_action_text'] ?? $locked->next_action_text);
            $nextActionDue = $this->date($input['next_action_due_on'] ?? $locked->next_action_due_on?->toDateString());
            $commercial = [
                'title' => $title,
                'amount_rials' => $amount,
                'customer_company_id' => $companyId,
                'expected_close_on' => $expectedClose,
                'next_action_text' => $nextActionText,
                'next_action_due_on' => $nextActionDue,
            ];

            if (! $locked->status->isOpen()) {
                $existingContacts = $locked->contacts()->pluck('customer_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $incomingContacts = collect($customerIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
                $changed = $title !== $locked->title
                    || $amount !== $locked->amount_rials
                    || $companyId !== ($locked->customer_company_id ? (int) $locked->customer_company_id : null)
                    || $expectedClose !== $locked->expected_close_on?->toDateString()
                    || $incomingContacts !== $existingContacts;

                if ($changed) {
                    DealState::assertOpen($locked);
                }

                $this->concurrency->bump($locked, [
                    'description' => $description,
                    'next_action_text' => $nextActionText,
                    'next_action_due_on' => $nextActionDue,
                    'last_activity_at' => now(),
                ]);
            } else {
                $this->concurrency->bump($locked, [
                    'title' => $commercial['title'],
                    'description' => $description,
                    'amount_rials' => $commercial['amount_rials'],
                    'customer_company_id' => $commercial['customer_company_id'],
                    'expected_close_on' => $commercial['expected_close_on'],
                    'next_action_text' => $commercial['next_action_text'],
                    'next_action_due_on' => $commercial['next_action_due_on'],
                    'last_activity_at' => now(),
                ]);
                $this->contacts->sync($locked, $customerIds, $primaryId);
            }

            $fresh = $locked->fresh() ?? $locked;
            $next = $fresh->auditSnapshot();

            if ($previous !== $next) {
                $this->audit->record($fresh, DealActivityType::Updated, $actor->id, 'manual', $previous, $next);

                if ($previousAmount !== $fresh->amount_rials) {
                    $this->audit->record($fresh, DealActivityType::ValueChanged, $actor->id, 'manual', [
                        'amount_rials' => $previousAmount,
                    ], [
                        'amount_rials' => $fresh->amount_rials,
                    ]);
                }

                $this->audit->afterCommit(new DealUpdated(
                    (int) $fresh->organization_id,
                    (int) $fresh->id,
                    $actor->id,
                    'manual',
                    $previous,
                    $next,
                ));
            }

            $this->health->persist($fresh);

            return $fresh->load(['stage', 'pipeline', 'company', 'owner', 'contacts.customer', 'primaryContact.customer']);
        });
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            throw ValidationException::withMessages([
                'deal' => 'شناسه انتخاب‌شده معتبر نیست.',
            ]);
        }

        return (int) $value;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > 5000) {
            throw ValidationException::withMessages([
                'description' => 'توضیحات حداکثر ۵۰۰۰ نویسه دارد.',
            ]);
        }

        return $text;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([
                'expected_close_on' => 'تاریخ احتمالی بسته‌شدن معتبر نیست.',
            ]);
        }

        return $value;
    }
}
