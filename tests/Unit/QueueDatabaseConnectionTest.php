<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class QueueDatabaseConnectionTest extends TestCase
{
    public function test_database_queue_uses_central_connection_not_null(): void
    {
        $connection = config('queue.connections.database.connection');

        $this->assertNotNull($connection);
        $this->assertSame(config('tenancy.database.central_connection'), $connection);
    }
}
