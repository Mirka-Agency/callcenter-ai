<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealContacts;
use App\Application\Crm\Deals\DealMoney;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealSource;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\Events\DealCreated;
use App\Enums\UserRole;
use App\Models\CrmDeal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDeal
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
        private DealContacts $contacts,
        private DealAudit $audit,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(int $organizationId, ?User $actor, array $input, DealSource $source = DealSource::Manual, bool $asSystem = false): CrmDeal
    {
        $this->auth->assertInternalCrm($organizationId);

        if (! $asSystem) {
            if (! $actor instanceof User) {
                throw new AuthorizationException('مجوز این عملیات را ندارید.');
            }

            $this->auth->authorize($this->auth->canCreate($actor, $organizationId));
        }

        $title = trim((string) ($input['title'] ?? ''));

        if ($title === '' || mb_strlen($title) > 180) {
            throw ValidationException::withMessages([
                'title' => 'عنوان معامله الزامی است و حداکثر ۱۸۰ نویسه دارد.',
            ]);
        }

        $pipeline = $this->tenant->pipeline($organizationId, (int) ($input['pipeline_id'] ?? 0));

        if (! $pipeline->is_active) {
            throw ValidationException::withMessages([
                'pipeline' => 'پایپ‌لاین غیرفعال است.',
            ]);
        }

        $stage = $this->tenant->stage($pipeline, (int) ($input['stage_id'] ?? 0));

        if (! $stage->is_active) {
            throw ValidationException::withMessages([
                'stage' => 'مرحله غیرفعال است.',
            ]);
        }

        $amount = DealMoney::parseRials($input['amount_rials'] ?? null);
        $currency = strtoupper((string) ($input['currency'] ?? DealMoney::CURRENCY));

        if ($currency !== DealMoney::CURRENCY) {
            throw ValidationException::withMessages([
                'currency' => 'در این نسخه مبلغ فقط به ریال ذخیره می‌شود.',
            ]);
        }

        $customerIds = array_values(array_filter(array_map('intval', (array) ($input['customer_ids'] ?? []))));
        $primaryId = $this->nullableInt($input['primary_customer_id'] ?? null);
        $companyId = $this->nullableInt($input['company_id'] ?? null);
        $ownerId = $this->nullableInt($input['owner_organization_user_id'] ?? null);

        if (! $asSystem && $actor instanceof User && $actor->role === UserRole::Employee) {
            $ownerId = $this->auth->membershipId($actor, $organizationId);

            if ($ownerId === null) {
                throw new AuthorizationException('مجوز این عملیات را ندارید.');
            }
        }

        if ($ownerId !== null) {
            $this->tenant->owner($organizationId, $ownerId);
        }

        foreach ($customerIds as $customerId) {
            $customer = $this->tenant->customer($organizationId, $customerId);

            if ($companyId === null && $customer->customer_company_id && count($customerIds) === 1) {
                $companyId = (int) $customer->customer_company_id;
            }
        }

        if ($companyId !== null) {
            $this->tenant->company($organizationId, $companyId);
        }

        $description = $this->nullableText($input['description'] ?? null);

        return DB::transaction(function () use ($organizationId, $actor, $title, $pipeline, $stage, $amount, $currency, $customerIds, $primaryId, $companyId, $ownerId, $description, $source, $input): CrmDeal {
            $deal = CrmDeal::query()->create([
                'organization_id' => $organizationId,
                'crm_pipeline_id' => $pipeline->id,
                'crm_pipeline_stage_id' => $stage->id,
                'customer_company_id' => $companyId,
                'owner_organization_user_id' => $ownerId,
                'title' => $title,
                'description' => $description,
                'amount_rials' => $amount,
                'currency' => $currency,
                'status' => DealStatus::Open,
                'source' => $source,
                'expected_close_on' => $this->date($input['expected_close_on'] ?? null),
                'last_activity_at' => now(),
                'closed_at' => null,
                'lost_reason' => null,
                'won_reason' => null,
                'lost_reason_code' => null,
                'version' => 1,
                'stage_entered_at' => now(),
            ]);

            if ($customerIds !== []) {
                $this->contacts->sync($deal, $customerIds, $primaryId);
            }

            $fresh = $deal->fresh() ?? $deal;
            $this->audit->record($fresh, DealActivityType::Created, $actor?->id, $source->value, null, $fresh->auditSnapshot());
            $this->audit->stage($fresh, null, $stage, null, DealStatus::Open->value, $actor?->id, $source->value);
            $this->audit->afterCommit(new DealCreated($organizationId, (int) $fresh->id, $actor?->id, $source->value));

            return $fresh->load(['stage', 'pipeline', 'company', 'owner', 'primaryContact.customer']);
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

    private function nullableText(mixed $value): ?string
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

        $date = Carbon::createFromFormat('Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([
                'expected_close_on' => 'تاریخ احتمالی بسته‌شدن معتبر نیست.',
            ]);
        }

        return $value;
    }
}
