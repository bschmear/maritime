<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Entity\BudgetRange;
use Tests\TestCase;

class BudgetRangeTest extends TestCase
{
    public function test_from_amount_maps_listing_price_to_correct_bucket(): void
    {
        $this->assertSame(BudgetRange::HundredTo250k, BudgetRange::fromAmount(189_500));
        $this->assertSame(BudgetRange::Under10k, BudgetRange::fromAmount(9_999));
        $this->assertSame(BudgetRange::Over250k, BudgetRange::fromAmount(300_000));
    }
}
