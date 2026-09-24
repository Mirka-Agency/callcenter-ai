<?php

namespace App\Services\Demo;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Support\Seeding\DemoAvatarAssigner;
use App\Support\Seeding\DemoCatalog;
use Illuminate\Support\Facades\Hash;

class DemoEmployeeProvisioner
{
    public function provision(
        Organization $organization,
        ?int $sequence = null,
        ?int $orgIndex = null,
        ?DemoAvatarAssigner $avatarAssigner = null,
    ): OrganizationUser {
        if (! $organization->is_demo) {
            throw new \InvalidArgumentException('Only demo organizations can receive demo employees.');
        }

        $sequence ??= $organization->memberships()->count() + 1;
        $orgIndex ??= DemoCatalog::organizationIndex($organization) ?? 0;

        $profiles = DemoCatalog::employeeProfiles();
        $profile = $profiles[($sequence - 1) % count($profiles)];

        $email = $this->resolveUniqueEmail($profile['email_first'], $profile['email_last']);

        $fullName = trim("{$profile['first_name']} {$profile['last_name']}");
        $mobile = DemoCatalog::formatMobile($orgIndex + 50, $sequence);
        $assigner = $avatarAssigner ?? DemoAvatarAssigner::forOrganization($organization);

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $fullName,
                'password' => Hash::make(DemoCatalog::DEMO_PASSWORD),
                'role' => UserRole::Employee,
                'email_verified_at' => now(),
                'avatar_path' => $assigner->assign($profile['gender']),
            ],
        );

        $membership = OrganizationUser::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
        );

        $membership->update([
            'first_name' => $profile['first_name'],
            'last_name' => $profile['last_name'],
            'gender' => $profile['gender'],
            'mobile' => $mobile,
            'position' => $profile['position'],
            'department' => $profile['department'],
            'is_active' => true,
        ]);

        return $membership->load('user');
    }

    private function resolveUniqueEmail(string $emailFirst, string $emailLast): string
    {
        $variant = 0;

        do {
            $variant++;
            $email = DemoCatalog::employeeEmail($emailFirst, $emailLast, $variant);
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
