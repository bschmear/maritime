<?php

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Enums\Payments\Terms;
use App\Services\Payments\QuickBooksOAuthService;
use App\Services\Payments\QuickBooksTermsService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksTermsServiceTest extends TestCase
{
    #[Test]
    public function from_stored_maps_enum_values_and_numeric_ids(): void
    {
        $this->assertSame(Terms::Net30, Terms::fromStored('net_30'));
        $this->assertSame(Terms::Net30, Terms::fromStored(3));
        $this->assertSame(Terms::DueOnReceipt, Terms::fromStored(null));
    }

    #[Test]
    public function resolve_term_id_matches_qbo_term_name_case_insensitively(): void
    {
        $integration = new Integration([
            'id' => 7,
            'external_id' => 'realm-abc',
        ]);

        $oauth = Mockery::mock(QuickBooksOAuthService::class);
        $oauth->shouldReceive('queryAccountingForIntegration')
            ->once()
            ->with($integration, 'select Id, Name from Term where Active = true')
            ->andReturn([
                'QueryResponse' => [
                    'Term' => [
                        ['Id' => '1', 'Name' => 'Due on receipt'],
                        ['Id' => '2', 'Name' => 'Net 15'],
                        ['Id' => '3', 'Name' => 'Net 30'],
                    ],
                ],
            ]);

        $service = new QuickBooksTermsService($oauth);

        $this->assertSame('3', $service->resolveTermId($integration, Terms::Net30));
        $this->assertSame(
            ['value' => '1'],
            $service->salesTermRefFor($integration, Terms::DueOnReceipt),
        );
    }

    #[Test]
    public function custom_terms_do_not_set_sales_term_ref(): void
    {
        $integration = new Integration([
            'id' => 1,
            'external_id' => 'realm',
        ]);

        $oauth = Mockery::mock(QuickBooksOAuthService::class);
        $oauth->shouldNotReceive('queryAccountingForIntegration');

        $service = new QuickBooksTermsService($oauth);

        $this->assertNull($service->salesTermRefFor($integration, Terms::Custom));
    }
}
