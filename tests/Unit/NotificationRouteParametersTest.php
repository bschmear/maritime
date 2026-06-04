<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Notification\Models\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NotificationRouteParametersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/contracts/{contract}', fn () => 'ok')->name('contracts.show');
        Route::get('/servicetickets/{serviceticket}', fn () => 'ok')->name('servicetickets.show');
    }

    public function test_scalar_route_params_resolve_to_named_route_parameter(): void
    {
        $notification = new Notification;
        $notification->setRawAttributes([
            'route' => 'contracts.show',
            'route_params' => json_encode(42),
        ]);

        $this->assertSame(['contract' => 42], $notification->getRouteParameters());
    }

    public function test_associative_route_params_are_returned_unchanged(): void
    {
        $notification = new Notification([
            'route' => 'contracts.show',
            'route_params' => ['contract' => 7],
        ]);

        $this->assertSame(['contract' => 7], $notification->getRouteParameters());
    }

    public function test_legacy_serviceticket_scalar_still_resolves(): void
    {
        $notification = new Notification;
        $notification->setRawAttributes([
            'route' => 'servicetickets.show',
            'route_params' => json_encode(99),
        ]);

        $this->assertSame(['serviceticket' => 99], $notification->getRouteParameters());
    }
}
