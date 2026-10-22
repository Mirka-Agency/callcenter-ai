<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Bootstrap a single-employer on-prem install (no demo multi-org data).
 *
 * Reads ONPREM_* from the live environment first so a stale config:cache
 * cannot seed the wrong password. Passwords are stored plain here and hashed
 * by the User model cast (do not Hash::make twice).
 *
 * @see docs/on-prem.md
 */
class OnPremSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformFoundationSeeder::class);

        $adminEmail = $this->setting('ONPREM_ADMIN_EMAIL', 'onprem.admin_email', 'admin@example.com');
        $adminPassword = $this->setting('ONPREM_ADMIN_PASSWORD', 'onprem.admin_password', 'password');
        $employerEmail = $this->setting('ONPREM_EMPLOYER_EMAIL', 'onprem.employer_email', 'employer@example.com');
        $employerPassword = $this->setting('ONPREM_EMPLOYER_PASSWORD', 'onprem.employer_password', 'password');

        if ($adminEmail === $employerEmail) {
            throw new RuntimeException('ONPREM_ADMIN_EMAIL and ONPREM_EMPLOYER_EMAIL must differ.');
        }

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $this->setting('ONPREM_ADMIN_NAME', 'onprem.admin_name', 'Super Admin'),
                'password' => $adminPassword,
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );

        $employer = User::query()->updateOrCreate(
            ['email' => $employerEmail],
            [
                'name' => $this->setting('ONPREM_EMPLOYER_NAME', 'onprem.employer_name', 'مدیر شرکت'),
                'password' => $employerPassword,
                'role' => UserRole::Employer,
                'email_verified_at' => now(),
            ],
        );

        $organization = Organization::query()->updateOrCreate(
            ['user_id' => $employer->id],
            [
                'title' => $this->setting('ONPREM_ORG_TITLE', 'onprem.org_title', 'شرکت محلی'),
                'disabled' => false,
                'is_demo' => false,
                'employer_can_manage_integrations' => filter_var(
                    $_ENV['ONPREM_EMPLOYER_CAN_MANAGE_INTEGRATIONS']
                        ?? getenv('ONPREM_EMPLOYER_CAN_MANAGE_INTEGRATIONS')
                        ?: config('onprem.employer_can_manage_integrations', true),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            ],
        );

        $this->command?->info('On-prem bootstrap complete.');
        $this->command?->info("Admin:    {$adminEmail} → /admin");
        $this->command?->info("Employer: {$employerEmail} → /app");
        $this->command?->warn('Use the exact ONPREM_* email/password from the container env (recreate app after editing .env).');
    }

    private function setting(string $envKey, string $configKey, string $default): string
    {
        $fromEnv = $_ENV[$envKey] ?? getenv($envKey);
        if (is_string($fromEnv) && trim($fromEnv) !== '' && $fromEnv !== false) {
            return trim($fromEnv);
        }

        $fromConfig = config($configKey, $default);

        if (! is_string($fromConfig) || trim($fromConfig) === '') {
            throw new RuntimeException("Missing required setting: {$envKey} / {$configKey}");
        }

        return trim($fromConfig);
    }
}
