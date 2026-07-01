<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Customer\Models\Customer;
use Tests\TestCase;

class CustomerIndexJoinedAttributesTest extends TestCase
{
    public function test_index_accessors_read_joined_columns_without_contact_relation(): void
    {
        $customer = new Customer([
            'id' => 1,
            'status_id' => 2,
            'display_name' => 'Joined Name',
            'email' => 'joined@example.com',
            'phone' => '555-0100',
            'mobile' => '555-0101',
            'company' => 'Joined Co',
            'city' => 'Austin',
            'state' => 'TX',
        ]);
        $customer->setAppends(Customer::indexAppends());

        $this->assertSame('Joined Name', $customer->display_name);
        $this->assertSame('joined@example.com', $customer->email);
        $this->assertSame('Austin', $customer->city);
        $this->assertSame('TX', $customer->state);
        $this->assertFalse($customer->relationLoaded('contact'));
    }
}
