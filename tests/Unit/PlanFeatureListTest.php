<?php

namespace Tests\Unit;

use App\Support\PlanFeatureList;
use Tests\TestCase;

class PlanFeatureListTest extends TestCase
{
    public function test_normalize_legacy_strings(): void
    {
        $result = PlanFeatureList::normalize(['Leads', 'SMS']);

        $this->assertSame([
            ['title' => 'Leads', 'description' => ''],
            ['title' => 'SMS', 'description' => ''],
        ], $result);
    }

    public function test_normalize_structured_features(): void
    {
        $result = PlanFeatureList::normalize([
            ['title' => 'Customer portal', 'description' => 'Full portal access'],
        ]);

        $this->assertSame('Customer portal', $result[0]['title']);
        $this->assertSame('Full portal access', $result[0]['description']);
    }

    public function test_titles_extracts_names_only(): void
    {
        $this->assertSame(['Reports'], PlanFeatureList::titles([
            ['title' => 'Reports', 'description' => 'P&L and sales'],
        ]));
    }
}
