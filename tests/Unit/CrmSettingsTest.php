<?php

namespace Tests\Unit;

use App\Domain\Crm\DTOs\CrmSettings;
use App\Domain\Crm\DTOs\LeadData;
use PHPUnit\Framework\TestCase;

class CrmSettingsTest extends TestCase
{
    public function test_parses_deal_defaults_from_array(): void
    {
        $settings = CrmSettings::fromArray([
            'timeout' => 45,
            'pipeline_id' => 'pipe-1',
            'pipeline_stage_id' => 'stage-1',
            'deal_owner_id' => 'owner-1',
        ]);

        $this->assertTrue($settings->hasDealDefaults());
        $this->assertSame('pipe-1', $settings->pipelineId);
        $this->assertSame('stage-1', $settings->pipelineStageId);
        $this->assertSame('owner-1', $settings->dealOwnerId);
        $this->assertSame(45, $settings->timeout);
    }

    public function test_to_array_omits_empty_optional_owner(): void
    {
        $settings = CrmSettings::fromArray([
            'pipeline_id' => 'pipe-1',
            'pipeline_stage_id' => 'stage-1',
            'deal_owner_id' => '',
        ]);

        $this->assertArrayNotHasKey('deal_owner_id', $settings->toArray());
        $this->assertSame('stage-1', $settings->toArray()['pipeline_stage_id']);
    }

    public function test_lead_data_inherits_deal_defaults(): void
    {
        $lead = (new LeadData(title: 'تست'))->withDealDefaults('stage-9', 'owner-9');

        $this->assertSame('stage-9', $lead->pipelineStageId);
        $this->assertSame('owner-9', $lead->ownerId);
    }
}
