<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Location\Models\Location;
use App\Domain\Shipment\Support\ShipmentFromAddressResolver;
use App\Domain\Subsidiary\Models\Subsidiary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShipmentFromAddressResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
            'database.default' => 'tenant',
        ]);
        DB::purge('tenant');
        DB::purge('sqlite');

        Schema::connection('tenant')->create('subsidiaries', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('location_subsidiary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id');
            $table->foreignId('subsidiary_id');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_builds_easypost_address_from_location_and_subsidiary(): void
    {
        $subsidiary = Subsidiary::query()->create(['display_name' => 'Main Store']);
        $location = Location::query()->create([
            'display_name' => 'Warehouse',
            'address_line_1' => '100 Harbor Dr',
            'city' => 'Miami',
            'state' => 'FL',
            'postal_code' => '33101',
            'country' => 'US',
            'phone' => '305-555-0100',
            'email' => 'ship@example.com',
        ]);
        $location->subsidiaries()->attach($subsidiary->id);

        $address = ShipmentFromAddressResolver::fromLocation($location->id, $subsidiary->id);

        $this->assertSame('Warehouse', $address['name']);
        $this->assertSame('Main Store', $address['company']);
        $this->assertSame('100 Harbor Dr', $address['street1']);
        $this->assertSame('Miami', $address['city']);
        $this->assertSame('33101', $address['zip']);
    }

    #[Test]
    public function it_rejects_location_without_street_address(): void
    {
        $location = Location::query()->create([
            'display_name' => 'Incomplete',
            'city' => 'Miami',
        ]);

        $this->expectException(ValidationException::class);
        ShipmentFromAddressResolver::fromLocation($location->id);
    }
}
