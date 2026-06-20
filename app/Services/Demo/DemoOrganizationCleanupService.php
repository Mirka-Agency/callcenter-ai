<?php

namespace App\Services\Demo;

use App\Exceptions\DemoCleanupException;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationActivity;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoOrganizationCleanupService
{
    public function summarizeAll(): DemoCleanupSummary
    {
        return $this->summarize($this->demoOrganizationsQuery()->get());
    }

    public function summarizeOrganization(Organization $organization): DemoCleanupSummary
    {
        $this->assertDeletable($organization);

        return $this->summarize(collect([$organization]));
    }

    public function deleteAll(): DemoCleanupSummary
    {
        $organizations = $this->demoOrganizationsQuery()->get();

        if ($organizations->isEmpty()) {
            return DemoCleanupSummary::empty();
        }

        $organizations->each(fn (Organization $organization) => $this->assertDeletable($organization));

        $summary = $this->summarize($organizations);
        $userIds = $this->collectDemoUserIds($organizations);

        DB::transaction(function () use ($organizations, $userIds): void {
            $organizations->each(fn (Organization $organization) => $organization->delete());
            $this->deleteOrphanDemoUsers($userIds);
        });

        return $summary;
    }

    public function deleteOrganization(Organization $organization): DemoCleanupSummary
    {
        $this->assertDeletable($organization);

        $summary = $this->summarize(collect([$organization]));
        $userIds = $this->collectDemoUserIds(collect([$organization]));

        DB::transaction(function () use ($organization, $userIds): void {
            $organization->delete();
            $this->deleteOrphanDemoUsers($userIds);
        });

        return $summary;
    }

    /** @return Builder<Organization> */
    public function demoOrganizationsQuery(): Builder
    {
        return Organization::query()->demo()->orderBy('id');
    }

    public function demoOrganizationCount(): int
    {
        return $this->demoOrganizationsQuery()->count();
    }

    private function assertDeletable(Organization $organization): void
    {
        if (! $organization->is_demo) {
            throw DemoCleanupException::notDemoOrganization();
        }
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     */
    private function summarize(Collection $organizations): DemoCleanupSummary
    {
        if ($organizations->isEmpty()) {
            return DemoCleanupSummary::empty();
        }

        $organizationIds = $organizations->pluck('id');

        return new DemoCleanupSummary(
            organizations: $organizations->count(),
            users: $this->collectDemoUserIds($organizations)->count(),
            memberships: OrganizationUser::query()->whereIn('organization_id', $organizationIds)->count(),
            customers: Customer::query()->whereIn('organization_id', $organizationIds)->count(),
            calls: Call::query()->whereIn('organization_id', $organizationIds)->count(),
            analyses: ConversationAnalysis::query()->whereIn('organization_id', $organizationIds)->count(),
            walletTransactions: WalletTransaction::query()->whereIn('organization_id', $organizationIds)->count(),
            activities: OrganizationActivity::query()->whereIn('organization_id', $organizationIds)->count(),
            integrations: OrganizationVoipConnection::query()->whereIn('organization_id', $organizationIds)->count()
                + OrganizationCrmConnection::query()->whereIn('organization_id', $organizationIds)->count(),
        );
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @return Collection<int, int>
     */
    private function collectDemoUserIds(Collection $organizations): Collection
    {
        $organizations = Organization::query()
            ->whereIn('id', $organizations->pluck('id'))
            ->with(['employer', 'employees'])
            ->get();

        return $organizations
            ->flatMap(function (Organization $organization): array {
                $ids = [];

                if ($organization->user_id) {
                    $ids[] = $organization->user_id;
                }

                foreach ($organization->employees as $employee) {
                    $ids[] = $employee->id;
                }

                return $ids;
            })
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteOrphanDemoUsers(Collection $userIds): void
    {
        User::query()
            ->whereIn('id', $userIds)
            ->each(function (User $user): void {
                if ($user->organizations()->exists() || $user->employeeOrganizations()->exists()) {
                    return;
                }

                $user->delete();
            });
    }
}
