<?php

namespace App\Services\Sales;

use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Platform\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadConverter
{
    /**
     * Create an organization and its employer account from a lead, and mark the lead converted.
     *
     * @param  array{title: string, employer_name: string, employer_email: string, employer_phone?: string|null}  $data
     * @return array{organization: Organization, password: string}
     */
    public function convert(Model $lead, array $data): array
    {
        $password = Str::password(12, symbols: false);

        $organization = DB::transaction(function () use ($lead, $data, $password): Organization {
            $employer = User::query()->create([
                'name' => $data['employer_name'],
                'email' => $data['employer_email'],
                'phone' => $data['employer_phone'] ?? null,
                'password' => $password,
                'role' => UserRole::Employer,
            ]);

            $organization = Organization::query()->create([
                'title' => $data['title'],
                'user_id' => $employer->id,
                'crm_mode' => OrganizationCrmMode::Internal,
            ]);

            $lead->forceFill([
                'lead_status' => LeadStatus::Converted,
                'converted_organization_id' => $organization->id,
                'first_contacted_at' => $lead->first_contacted_at ?? now(),
            ])->save();

            return $organization;
        });

        app(AdminAuditLogger::class)->log('lead_converted', $lead, ['after' => ['organization_id' => $organization->id]]);

        return ['organization' => $organization, 'password' => $password];
    }
}
