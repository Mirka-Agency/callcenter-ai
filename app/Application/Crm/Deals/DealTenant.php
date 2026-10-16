<?php

namespace App\Application\Crm\Deals;

use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\OrganizationUser;
use Illuminate\Validation\ValidationException;

class DealTenant
{
    public function pipeline(int $organizationId, int $pipelineId): CrmPipeline
    {
        $pipeline = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->whereKey($pipelineId)
            ->first();

        if (! $pipeline instanceof CrmPipeline) {
            throw ValidationException::withMessages([
                'pipeline' => 'پایپ‌لاین به این سازمان تعلق ندارد.',
            ]);
        }

        return $pipeline;
    }

    public function stage(CrmPipeline $pipeline, int $stageId): CrmPipelineStage
    {
        $stage = CrmPipelineStage::query()
            ->where('crm_pipeline_id', $pipeline->id)
            ->where('organization_id', $pipeline->organization_id)
            ->whereKey($stageId)
            ->first();

        if (! $stage instanceof CrmPipelineStage) {
            throw ValidationException::withMessages([
                'stage' => 'مرحله به این پایپ‌لاین تعلق ندارد.',
            ]);
        }

        return $stage;
    }

    public function customer(int $organizationId, int $customerId): Customer
    {
        $customer = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereKey($customerId)
            ->first();

        if (! $customer instanceof Customer) {
            throw ValidationException::withMessages([
                'customer' => 'شخص انتخاب‌شده به این سازمان تعلق ندارد.',
            ]);
        }

        return $customer;
    }

    public function company(int $organizationId, int $companyId): CustomerCompany
    {
        $company = CustomerCompany::query()
            ->where('organization_id', $organizationId)
            ->whereKey($companyId)
            ->first();

        if (! $company instanceof CustomerCompany) {
            throw ValidationException::withMessages([
                'company' => 'شرکت انتخاب‌شده به این سازمان تعلق ندارد.',
            ]);
        }

        return $company;
    }

    public function owner(int $organizationId, int $ownerId): OrganizationUser
    {
        $owner = OrganizationUser::query()->whereKey($ownerId)->first();

        if (! $owner instanceof OrganizationUser
            || (int) $owner->organization_id !== $organizationId
            || ! $owner->is_active
        ) {
            throw ValidationException::withMessages([
                'owner' => 'مسئول معامله باید عضو فعال همین سازمان باشد.',
            ]);
        }

        return $owner;
    }

    public function call(int $organizationId, int $callId): Call
    {
        $call = Call::query()
            ->where('organization_id', $organizationId)
            ->whereKey($callId)
            ->first();

        if (! $call instanceof Call) {
            throw ValidationException::withMessages([
                'call' => 'تماس به این سازمان تعلق ندارد.',
            ]);
        }

        return $call;
    }

    public function analysis(int $organizationId, int $analysisId): ConversationAnalysis
    {
        $analysis = ConversationAnalysis::query()
            ->where('organization_id', $organizationId)
            ->whereKey($analysisId)
            ->first();

        if (! $analysis instanceof ConversationAnalysis) {
            throw ValidationException::withMessages([
                'analysis' => 'تحلیل به این سازمان تعلق ندارد.',
            ]);
        }

        return $analysis;
    }
}
