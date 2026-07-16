<?php

namespace App\Application\Crm\Services;

use App\Application\Crm\CrmManager;
use App\Domain\Crm\DTOs\CrmSettings;
use App\Models\OrganizationCrmConnection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CrmDealSettingsService
{
    /**
     * @return array{pipelines: list<array{id: string, title: string, stages: list<array{id: string, title: string, index: int}>}>, error: ?string}
     */
    public function loadPipelines(OrganizationCrmConnection $connection): array
    {
        $result = CrmManager::forOrganization($connection->organization_id)
            ->connection($connection->id)
            ->listPipelines();

        return [
            'pipelines' => $result->success ? ($result->data['pipelines'] ?? []) : [],
            'error' => $result->success ? null : ($result->error ?? 'بارگذاری کاریزها ناموفق بود.'),
        ];
    }

    /**
     * @return array{users: list<array{id: string, name: string, email?: string}>, error: ?string}
     */
    public function loadUsers(OrganizationCrmConnection $connection): array
    {
        $result = CrmManager::forOrganization($connection->organization_id)
            ->connection($connection->id)
            ->listUsers();

        return [
            'users' => $result->success ? ($result->data['users'] ?? []) : [],
            'error' => $result->success ? null : ($result->error ?? 'بارگذاری کاربران CRM ناموفق بود.'),
        ];
    }

    public function updateDealDefaults(
        OrganizationCrmConnection $connection,
        ?string $pipelineId,
        ?string $pipelineStageId,
        ?string $dealOwnerId = null,
    ): OrganizationCrmConnection {
        if (! $connection->is_active) {
            throw new InvalidArgumentException('این اتصال CRM توسط ادمین غیرفعال شده است.');
        }

        $pipelineId = $this->nullable($pipelineId);
        $pipelineStageId = $this->nullable($pipelineStageId);
        $dealOwnerId = $this->nullable($dealOwnerId);

        if ($pipelineStageId !== null && $pipelineId === null) {
            throw new InvalidArgumentException('برای انتخاب مرحله کاریز، ابتدا کاریز را مشخص کنید.');
        }

        if ($pipelineId !== null && $pipelineStageId === null) {
            throw new InvalidArgumentException('مرحله کاریز الزامی است.');
        }

        return DB::transaction(function () use ($connection, $pipelineId, $pipelineStageId, $dealOwnerId) {
            $settings = CrmSettings::fromArray($connection->settings ?? [])->toArray();
            $settings['pipeline_id'] = $pipelineId;
            $settings['pipeline_stage_id'] = $pipelineStageId;
            $settings['deal_owner_id'] = $dealOwnerId;

            // Remove empty keys so optional owner clears correctly.
            $settings = array_filter(
                $settings,
                fn ($value) => $value !== null && $value !== '',
            );

            // Employer may only update deal defaults — never is_active / credentials.
            $connection->update(['settings' => $settings]);

            return $connection->fresh(['provider']);
        });
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
