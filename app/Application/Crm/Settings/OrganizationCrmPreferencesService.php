<?php

namespace App\Application\Crm\Settings;

use App\Domain\Crm\DTOs\OrganizationCrmPreferences;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmPipeline;
use App\Models\CrmTask;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationCrmPreferencesService
{
    public function forOrganization(Organization|int $organization): OrganizationCrmPreferences
    {
        $model = $organization instanceof Organization
            ? $organization
            : Organization::query()->findOrFail($organization);

        return OrganizationCrmPreferences::fromArray(
            is_array($model->crm_settings) ? $model->crm_settings : null
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveGeneral(Organization $organization, array $input): OrganizationCrmPreferences
    {
        $current = $this->forOrganization($organization);
        $pipelineId = $input['call_summary_pipeline_id'] ?? null;
        $pipelineId = $pipelineId === null || $pipelineId === '' ? null : (int) $pipelineId;

        if ($pipelineId !== null) {
            $exists = CrmPipeline::query()
                ->where('organization_id', $organization->id)
                ->whereKey($pipelineId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'callSummaryPipelineId' => 'کاریز انتخاب‌شده معتبر نیست.',
                ]);
            }
        }

        $merged = OrganizationCrmPreferences::fromArray([
            ...$current->toArray(),
            'call_summary_pipeline_id' => $pipelineId,
            'ai_catalog_search_enabled' => (bool) ($input['ai_catalog_search_enabled'] ?? false),
            'require_national_id_for_new_numbers' => (bool) ($input['require_national_id_for_new_numbers'] ?? false),
            'ai_proforma_enabled' => (bool) ($input['ai_proforma_enabled'] ?? false),
            'company_identity' => is_array($input['company_identity'] ?? null) ? $input['company_identity'] : [],
            'default_tax_percent' => $input['default_tax_percent'] ?? OrganizationCrmPreferences::DEFAULT_TAX_PERCENT,
        ]);

        $this->persist($organization, $merged);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveSalesFollowUp(Organization $organization, array $input): OrganizationCrmPreferences
    {
        $current = $this->forOrganization($organization);
        $wasEnabled = $current->salesFollowUpEnabled;
        $enabled = (bool) ($input['enabled'] ?? false);

        $merged = OrganizationCrmPreferences::fromArray([
            ...$current->toArray(),
            'sales_follow_up' => [
                'enabled' => $enabled,
                'default_days' => $input['default_days'] ?? $current->salesFollowUpDefaultDays,
                'max_count' => $input['max_count'] ?? $current->salesFollowUpMaxCount,
                'min_day_interval' => $input['min_day_interval'] ?? $current->salesFollowUpMinDayInterval,
                'earliest_days' => $input['earliest_days'] ?? $current->salesFollowUpEarliestDays,
                'ai_can_change_days' => (bool) ($input['ai_can_change_days'] ?? false),
                'replan_interval_days' => $input['replan_interval_days'] ?? $current->salesFollowUpReplanIntervalDays,
            ],
        ]);

        return DB::transaction(function () use ($organization, $merged, $wasEnabled, $enabled): OrganizationCrmPreferences {
            $this->persist($organization, $merged);

            if ($wasEnabled && ! $enabled) {
                $this->cancelOpenSalesFollowUps((int) $organization->id);
            }

            return $merged;
        });
    }

    private function persist(Organization $organization, OrganizationCrmPreferences $preferences): void
    {
        $organization->forceFill([
            'crm_settings' => $preferences->toArray(),
        ])->save();
    }

    private function cancelOpenSalesFollowUps(int $organizationId): void
    {
        $now = now();

        CrmTask::query()
            ->where('organization_id', $organizationId)
            ->where('source', TaskSource::SalesFollowUp->value)
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->orderBy('id')
            ->each(function (CrmTask $task) use ($now): void {
                $task->forceFill([
                    'status' => TaskStatus::Cancelled,
                    'completion_outcome' => 'غیرفعال‌سازی پیگیری فروش',
                    'completed_at' => $now,
                    'version' => ((int) $task->version) + 1,
                ])->save();
            });
    }
}
