<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\PullContactsFromQuickBooks;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PullContactsFromQuickBooksTest extends TestCase
{
    public function test_contact_import_type_maps_to_customer_profile(): void
    {
        $job = new PullContactsFromQuickBooks(1, 'contact');
        $method = new ReflectionMethod(PullContactsFromQuickBooks::class, 'normalizeRecordType');
        $method->setAccessible(true);

        $this->assertSame('customer', $method->invoke($job, 'contact'));
        $this->assertSame('lead', $method->invoke($job, 'lead'));
        $this->assertSame('customer', $method->invoke($job, 'customer'));
    }
}
