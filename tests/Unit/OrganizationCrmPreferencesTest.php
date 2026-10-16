<?php

namespace Tests\Unit;

use App\Domain\Crm\DTOs\OrganizationCrmPreferences;
use PHPUnit\Framework\TestCase;

class OrganizationCrmPreferencesTest extends TestCase
{
    public function test_defaults_match_product_policy(): void
    {
        $prefs = OrganizationCrmPreferences::defaults();

        $this->assertNull($prefs->callSummaryPipelineId);
        $this->assertTrue($prefs->aiCatalogSearchEnabled);
        $this->assertFalse($prefs->requireNationalIdForNewNumbers);
        $this->assertTrue($prefs->aiProformaEnabled);
        $this->assertFalse($prefs->salesFollowUpEnabled);
        $this->assertSame([3, 10], $prefs->salesFollowUpDefaultDays);
        $this->assertSame(2, $prefs->salesFollowUpMaxCount);
        $this->assertSame(3, $prefs->salesFollowUpMinDayInterval);
        $this->assertSame(2, $prefs->salesFollowUpEarliestDays);
        $this->assertFalse($prefs->salesFollowUpAiCanChangeDays);
        $this->assertSame(7, $prefs->salesFollowUpReplanIntervalDays);
        $this->assertSame(10.0, $prefs->defaultTaxPercent);
    }

    public function test_parses_csv_days_and_identity(): void
    {
        $prefs = OrganizationCrmPreferences::fromArray([
            'call_summary_pipeline_id' => '12',
            'ai_catalog_search_enabled' => false,
            'sales_follow_up' => [
                'enabled' => true,
                'default_days' => '5, 1, 5, 20',
                'max_count' => 4,
                'ai_can_change_days' => true,
                'replan_interval_days' => 14,
            ],
            'company_identity' => [
                'legal_name' => ' شرکت نمونه ',
                'email' => 'sales@example.com',
            ],
            'default_tax_percent' => '9.5',
        ]);

        $this->assertSame(12, $prefs->callSummaryPipelineId);
        $this->assertFalse($prefs->aiCatalogSearchEnabled);
        $this->assertTrue($prefs->salesFollowUpEnabled);
        $this->assertSame([1, 5, 20], $prefs->salesFollowUpDefaultDays);
        $this->assertSame('1, 5, 20', $prefs->salesFollowUpDefaultDaysCsv());
        $this->assertSame(4, $prefs->salesFollowUpMaxCount);
        $this->assertTrue($prefs->salesFollowUpAiCanChangeDays);
        $this->assertSame(14, $prefs->salesFollowUpReplanIntervalDays);
        $this->assertSame('شرکت نمونه', $prefs->companyIdentity['legal_name']);
        $this->assertSame('sales@example.com', $prefs->companyIdentity['email']);
        $this->assertSame(9.5, $prefs->defaultTaxPercent);
    }
}
