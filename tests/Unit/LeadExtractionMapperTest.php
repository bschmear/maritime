<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Entity\BudgetRange;
use App\Enums\Entity\Source;
use App\Enums\Leads\Status;
use App\Models\AiEmailIngestion;
use App\Models\EmailRoute;
use App\Support\InboundEmail\LeadExtractionMapper;
use Tests\TestCase;

class LeadExtractionMapperTest extends TestCase
{
    public function test_maps_mobile_and_strips_duplicate_phone(): void
    {
        $route = new EmailRoute(['address' => 'lead-123@inbound.helmful.com', 'tenant_id' => 't1']);
        $ingestion = new AiEmailIngestion(['id' => 9, 'tenant_id' => 't1']);

        $mapper = new LeadExtractionMapper;
        $payload = $mapper->toLeadPayload([
            'first_name' => 'John',
            'last_name' => 'Martinez',
            'email' => 'john@example.com',
            'mobile' => '(239) 555-0182',
            'phone' => '(239) 555-0182',
            'has_trade_in' => true,
            'source' => 'ad',
            'status' => 'open',
        ], $route, $ingestion, '');

        $this->assertSame('(239) 555-0182', $payload['mobile']);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertTrue($payload['has_trade_in']);
        $this->assertSame(Source::Ad->id(), $payload['source_id']);
        $this->assertSame(Status::Open->id(), $payload['status_id']);
    }

    public function test_rejects_uncertain_social_urls(): void
    {
        $route = new EmailRoute(['address' => 'lead-123@inbound.helmful.com', 'tenant_id' => 't1']);
        $ingestion = new AiEmailIngestion(['id' => 9, 'tenant_id' => 't1']);

        $mapper = new LeadExtractionMapper;
        $payload = $mapper->toLeadPayload([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'linkedin' => 'Jane Doe on LinkedIn',
            'facebook' => 'https://facebook.com/janedoe',
        ], $route, $ingestion, '');

        $this->assertArrayNotHasKey('linkedin', $payload);
        $this->assertSame('https://facebook.com/janedoe', $payload['facebook']);
    }

    public function test_maps_budget_and_address_fields(): void
    {
        $route = new EmailRoute(['address' => 'lead-123@inbound.helmful.com', 'tenant_id' => 't1']);
        $ingestion = new AiEmailIngestion(['id' => 9, 'tenant_id' => 't1']);

        $mapper = new LeadExtractionMapper;
        $payload = $mapper->toLeadPayload([
            'first_name' => 'Sam',
            'last_name' => 'Boater',
            'budget_min' => 150000,
            'budget_max' => 200000,
            'budget_range' => 'under_10k',
            'city' => 'Naples',
            'state' => 'FL',
            'postal_code' => '34112',
        ], $route, $ingestion, '');

        $scoreData = $mapper->buildScoreData([
            'first_name' => 'Sam',
            'last_name' => 'Boater',
            'budget_min' => 150000,
            'budget_max' => 200000,
            'lead_score' => 72,
        ], 150000, 200000);

        $this->assertSame(150000.0, $payload['budget_min']);
        $this->assertSame(200000.0, $payload['budget_max']);
        $this->assertSame(BudgetRange::HundredTo250k->id(), $payload['budget_range']);
        $this->assertSame('Naples', $payload['city']);
        $this->assertSame(72, $scoreData['score']);
    }

    public function test_listing_price_derives_range_and_avoids_duplicate_budget_display(): void
    {
        $route = new EmailRoute(['address' => 'lead-123@inbound.helmful.com', 'tenant_id' => 't1']);
        $ingestion = new AiEmailIngestion(['id' => 9, 'tenant_id' => 't1']);

        $mapper = new LeadExtractionMapper;
        $extracted = [
            'first_name' => 'John',
            'last_name' => 'Martinez',
            'email' => 'john@example.com',
            'mobile' => '(239) 555-0182',
            'budget_min' => 189500,
            'budget_max' => 189500,
            'budget_range' => 'under_10k',
            'has_trade_in' => true,
            'interested_model' => '2024 Sea Ray SLX 280',
        ];

        $payload = $mapper->toLeadPayload($extracted, $route, $ingestion, '');
        $scoreData = $mapper->buildScoreData($extracted, $payload['budget_min'] ?? null, $payload['budget_max'] ?? null);

        $this->assertArrayNotHasKey('budget_min', $payload);
        $this->assertArrayNotHasKey('lead_score', $payload);
        $this->assertSame(189500.0, $payload['budget_max']);
        $this->assertSame(BudgetRange::HundredTo250k->id(), $payload['budget_range']);
        $this->assertGreaterThanOrEqual(75, $scoreData['score']);
        $this->assertNotEmpty($scoreData['breakdown']);
    }

    public function test_build_score_data_prefers_ai_score_when_provided(): void
    {
        $mapper = new LeadExtractionMapper;
        $scoreData = $mapper->buildScoreData([
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'lead_score' => 88,
        ]);

        $this->assertSame(88, $scoreData['score']);
    }
}
