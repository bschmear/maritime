<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\BoatMake\Support\BoatMakeWordPressPayload;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BoatMakeWordPressPayloadTest extends TestCase
{
    #[Test]
    public function brand_payload_includes_description_and_website_url(): void
    {
        $make = new BoatMake;
        $make->forceFill([
            'display_name' => 'Zodiac',
            'slug' => 'zodiac',
            'brand_key' => 'zodiac',
            'description' => 'Inventor of the inflatable boat.',
            'website_url' => 'https://www.zodiac-nautic.com',
            'active' => true,
        ]);
        $make->id = 9;

        $payload = BoatMakeWordPressPayload::forBrand($make);

        $this->assertSame('brand:zodiac', $payload['uuid']);
        $this->assertSame('Inventor of the inflatable boat.', $payload['description']);
        $this->assertSame('https://www.zodiac-nautic.com', $payload['website_url']);
    }
}
