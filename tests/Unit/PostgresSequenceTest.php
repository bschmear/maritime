<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PostgresSequence;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostgresSequenceTest extends TestCase
{
    #[Test]
    public function it_does_not_throw_on_sqlite(): void
    {
        PostgresSequence::sync('consignment_policies');

        $this->assertTrue(true);
    }
}
