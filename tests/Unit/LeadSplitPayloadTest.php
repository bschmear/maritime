<?php

namespace Tests\Unit;

use App\Domain\Lead\Models\Lead;
use PHPUnit\Framework\TestCase;

class LeadSplitPayloadTest extends TestCase
{
    public function test_split_payload_separates_contact_address_and_profile(): void
    {
        $payload = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'address_line_1' => '1 Main St',
            'city' => 'London',
            'status_id' => 1,
            'source_id' => 2,
            'campaign' => 'spring',
            'id' => 99,
            'created_at' => 'ignored',
        ];

        [$contact, $address, $profile] = Lead::splitPayload($payload);

        $this->assertSame('Ada', $contact['first_name']);
        $this->assertSame('Lovelace', $contact['last_name']);
        $this->assertSame('ada@example.com', $contact['email']);
        $this->assertArrayNotHasKey('status_id', $contact);

        $this->assertSame('1 Main St', $address['address_line_1']);
        $this->assertSame('London', $address['city']);
        $this->assertArrayNotHasKey('status_id', $address);

        $this->assertSame(1, $profile['status_id']);
        $this->assertSame(2, $profile['source_id']);
        $this->assertSame('spring', $profile['campaign']);
        $this->assertArrayNotHasKey('first_name', $profile);
        $this->assertArrayNotHasKey('id', $profile);
        $this->assertArrayNotHasKey('created_at', $profile);
    }
}
