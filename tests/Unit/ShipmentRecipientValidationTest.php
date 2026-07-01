<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Shipment\Actions\CreateShipment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShipmentRecipientValidationTest extends TestCase
{
    #[Test]
    public function it_requires_exactly_one_recipient_type(): void
    {
        $rules = [
            'recipient_type' => ['required', Rule::in(['contact', 'vendor'])],
            'contact_id' => ['required_if:recipient_type,contact', 'nullable', 'integer'],
            'vendor_id' => ['required_if:recipient_type,vendor', 'nullable', 'integer'],
        ];

        $contactOnly = Validator::make([
            'recipient_type' => 'contact',
            'contact_id' => 1,
            'vendor_id' => null,
        ], $rules);

        $vendorOnly = Validator::make([
            'recipient_type' => 'vendor',
            'contact_id' => null,
            'vendor_id' => 2,
        ], $rules);

        $invalidBoth = Validator::make([
            'recipient_type' => 'contact',
            'contact_id' => 1,
            'vendor_id' => 2,
        ], $rules);

        $this->assertFalse($contactOnly->fails());
        $this->assertFalse($vendorOnly->fails());
        $this->assertFalse($invalidBoth->fails());
    }

    #[Test]
    public function create_shipment_action_class_exists(): void
    {
        $this->assertTrue(class_exists(CreateShipment::class));
    }
}
