<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Notification\Models\Notification;
use ReflectionClass;
use Tests\TestCase;

class NotificationRelativeUrlTest extends TestCase
{
    public function test_work_order_show_route_is_mapped_for_notification_urls(): void
    {
        $ref = new ReflectionClass(Notification::class);
        $constant = $ref->getConstant('SCALAR_PARAM_BY_ROUTE');

        $this->assertIsArray($constant);
        $this->assertSame('workorder', $constant['workorders.show']);
    }
}
