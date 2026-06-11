<?php

namespace Tests\Unit;

use App\Domain\User\Models\User;
use App\Support\Dashboard\DashboardFilters;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardFiltersTest extends TestCase
{
    public function test_from_request_prefers_query_params_over_user_preferences(): void
    {
        $user = new User([
            'preferred_subsidiary_id' => 9,
        ]);

        $filters = DashboardFilters::fromRequest(
            Request::create('/', 'GET', ['subsidiary_id' => 2]),
            $user
        );

        $this->assertSame(2, $filters->subsidiaryId);
        $this->assertNull($filters->locationId);
    }

    public function test_from_request_falls_back_to_user_preferences(): void
    {
        $user = new User([
            'preferred_subsidiary_id' => 4,
        ]);

        $filters = DashboardFilters::fromRequest(Request::create('/'), $user);

        $this->assertSame(4, $filters->subsidiaryId);
        $this->assertNull($filters->locationId);
    }

    public function test_is_active_when_any_scope_set(): void
    {
        $this->assertFalse(DashboardFilters::validated(null, null)->isActive());
        $this->assertTrue(DashboardFilters::validated(1, null)->isActive());
        $this->assertTrue(DashboardFilters::validated(null, 2)->isActive());
    }
}
