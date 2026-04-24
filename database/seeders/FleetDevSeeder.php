<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Fleet\Models\Fleet;
use App\Domain\Location\Models\Location;
use App\Enums\Fleet\FleetStatus;
use App\Enums\Fleet\FleetType;
use App\Enums\Fleet\FuelType;
use App\Enums\Fleet\WeightUnit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary dev/demo fleet units. Not run on new tenants by default.
 *
 * Run for one tenant:
 *   php artisan tenants:seed --tenants="{tenant-id}" --class="Database\\Seeders\\FleetDevSeeder"
 *
 * Run for all tenants:
 *   php artisan tenants:seed --class="Database\\Seeders\\FleetDevSeeder"
 *
 * Re-running deletes rows with the seeded license plates, then inserts fresh rows.
 */
class FleetDevSeeder extends Seeder
{
    /** @var list<string> */
    private const LICENSE_PLATES = [
        'WI-TRL-101', 'WI-TRL-202', 'WI-TRL-303', 'WI-TRL-404',
        'WI-TRK-101', 'WI-TRK-202', 'WI-TRK-303', 'WI-TRK-404',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('fleets')) {
            $this->command?->warn('Table fleets does not exist (run tenant migrations first).');

            return;
        }

        Fleet::query()->whereIn('license_plate', self::LICENSE_PLATES)->delete();

        /** @var list<int|null> Map logical location slot 1..3 to tenant location ids (by ascending id). */
        $locationIds = Location::query()->orderBy('id')->pluck('id')->values()->all();
        $loc = fn (int $slot): ?int => match ($slot) {
            1 => $locationIds[0] ?? null,
            2 => $locationIds[1] ?? $locationIds[0] ?? null,
            3 => $locationIds[2] ?? $locationIds[0] ?? null,
            default => null,
        };

        $rows = [
            $this->trailer([
                'display_name' => 'RIB Trailer 1 - 18ft',
                'license_plate' => 'WI-TRL-101',
                'size' => '18ft',
                'location_id' => $loc(1),
                'last_maintenance_at' => Carbon::parse('2026-03-05'),
                'next_maintenance_due_at' => Carbon::parse('2026-09-05'),
                'maintenance_interval_days' => 180,
                'vin' => '5KTBS1810LF000001',
                'make' => 'Load Rite',
                'model' => 'LR-AB18T5200',
                'year' => 2022,
                'weight_capacity' => 5200,
                'gvwr' => 5200,
                'axle_count' => 1,
                'notes' => 'Used for 16-18ft RIBs, single axle setup',
            ]),
            $this->trailer([
                'display_name' => 'RIB Trailer 2 - 22ft Tandem',
                'license_plate' => 'WI-TRL-202',
                'size' => '22ft',
                'location_id' => $loc(2),
                'last_maintenance_at' => Carbon::parse('2026-02-20'),
                'next_maintenance_due_at' => Carbon::parse('2026-08-20'),
                'maintenance_interval_days' => 180,
                'vin' => '5KTBS2210LF000002',
                'make' => 'Karavan',
                'model' => 'KBE-2225-78-14',
                'year' => 2021,
                'weight_capacity' => 7000,
                'gvwr' => 7000,
                'axle_count' => 2,
                'notes' => 'Tandem axle trailer for mid-size RIB deliveries',
            ]),
            $this->trailer([
                'display_name' => 'Inflatable Tender Trailer - 14ft',
                'license_plate' => 'WI-TRL-303',
                'size' => '14ft',
                'location_id' => $loc(1),
                'last_maintenance_at' => Carbon::parse('2026-01-15'),
                'next_maintenance_due_at' => Carbon::parse('2026-07-15'),
                'maintenance_interval_days' => 180,
                'vin' => '4YMUL1410MF000003',
                'make' => 'EZ Loader',
                'model' => 'EZ14-3000',
                'year' => 2020,
                'weight_capacity' => 3000,
                'gvwr' => 3000,
                'axle_count' => 1,
                'notes' => 'Lightweight trailer for small tenders and inflatables',
            ]),
            $this->trailer([
                'display_name' => 'Heavy Duty RIB Trailer - 26ft',
                'license_plate' => 'WI-TRL-404',
                'size' => '26ft',
                'location_id' => $loc(3),
                'last_maintenance_at' => Carbon::parse('2026-03-10'),
                'next_maintenance_due_at' => Carbon::parse('2026-09-10'),
                'maintenance_interval_days' => 180,
                'status' => FleetStatus::Maintenance,
                'vin' => '5KTBS2610LF000004',
                'make' => 'Load Rite',
                'model' => 'LR-AB26T10000',
                'year' => 2023,
                'weight_capacity' => 10000,
                'gvwr' => 10000,
                'axle_count' => 2,
                'notes' => 'Heavy-duty tandem trailer for large offshore RIBs. Currently in maintenance for brake inspection',
            ]),
            $this->truck([
                'display_name' => 'Service Truck 1',
                'license_plate' => 'WI-TRK-101',
                'size' => '8ft Bed',
                'fuel_type' => FuelType::Gasoline,
                'location_id' => $loc(1),
                'last_maintenance_at' => Carbon::parse('2026-03-01'),
                'next_maintenance_due_at' => Carbon::parse('2026-06-01'),
                'maintenance_interval_days' => 90,
                'vin' => '1FTFW1E50JFA00001',
                'make' => 'Ford',
                'model' => 'F-150 XL',
                'year' => 2018,
                'weight_capacity' => 7050,
                'towing_capacity' => 11000,
                'payload_capacity' => 3270,
                'mileage' => 68500,
                'notes' => 'Primary mobile service truck',
            ]),
            $this->truck([
                'display_name' => 'Delivery Truck 2',
                'license_plate' => 'WI-TRK-202',
                'size' => '6.5ft Bed',
                'fuel_type' => FuelType::Diesel,
                'location_id' => $loc(2),
                'last_maintenance_at' => Carbon::parse('2026-02-15'),
                'next_maintenance_due_at' => Carbon::parse('2026-05-15'),
                'maintenance_interval_days' => 90,
                'vin' => '1GC4YNEY5LF000002',
                'make' => 'Chevrolet',
                'model' => 'Silverado 2500HD',
                'year' => 2020,
                'weight_capacity' => 10000,
                'towing_capacity' => 18000,
                'payload_capacity' => 3500,
                'mileage' => 45200,
                'notes' => 'Used for boat deliveries',
            ]),
            $this->truck([
                'display_name' => 'Hauler Truck 3',
                'license_plate' => 'WI-TRK-303',
                'size' => '8ft Bed',
                'fuel_type' => FuelType::Diesel,
                'location_id' => $loc(1),
                'last_maintenance_at' => Carbon::parse('2026-01-10'),
                'next_maintenance_due_at' => Carbon::parse('2026-04-10'),
                'maintenance_interval_days' => 90,
                'status' => FleetStatus::Maintenance,
                'vin' => '3C6UR5FL2KG000003',
                'make' => 'Ram',
                'model' => '2500 Tradesman',
                'year' => 2019,
                'weight_capacity' => 10000,
                'towing_capacity' => 17000,
                'payload_capacity' => 3200,
                'mileage' => 91200,
                'notes' => 'Currently in shop for brake service',
            ]),
            $this->truck([
                'display_name' => 'Manager Truck 4',
                'license_plate' => 'WI-TRK-404',
                'size' => '5.5ft Bed',
                'fuel_type' => FuelType::Gasoline,
                'location_id' => $loc(3),
                'last_maintenance_at' => Carbon::parse('2026-03-20'),
                'next_maintenance_due_at' => Carbon::parse('2026-06-20'),
                'maintenance_interval_days' => 90,
                'vin' => '1FTEW1CP5MFA00004',
                'make' => 'Ford',
                'model' => 'F-150 XLT',
                'year' => 2021,
                'weight_capacity' => 6500,
                'towing_capacity' => 9000,
                'payload_capacity' => 2000,
                'mileage' => 28000,
                'notes' => 'Used by sales manager',
            ]),
        ];

        foreach ($rows as $attrs) {
            Fleet::create($attrs);
        }

        $this->command?->info('Fleet dev seed: '.count($rows).' units (WI-TRL / WI-TRK plates).');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function truck(array $overrides): array
    {
        return array_merge([
            'type' => FleetType::Truck,
            'status' => FleetStatus::Active,
            'weight_unit' => WeightUnit::Pounds,
            'weight_capacity' => null,
            'axle_count' => null,
            'fuel_type' => FuelType::Diesel,
            'towing_capacity' => null,
            'payload_capacity' => null,
            'gvwr' => null,
            'mileage' => null,
            'hours' => null,
            'location_id' => null,
            'subsidiary_id' => null,
            'size' => null,
            'specs' => null,
            'last_maintenance_at' => null,
            'next_maintenance_due_at' => null,
            'maintenance_interval_days' => null,
            'notes' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function trailer(array $overrides): array
    {
        return array_merge([
            'type' => FleetType::Trailer,
            'status' => FleetStatus::Active,
            'weight_unit' => WeightUnit::Pounds,
            'fuel_type' => null,
            'towing_capacity' => null,
            'payload_capacity' => null,
            'gvwr' => null,
            'mileage' => null,
            'hours' => null,
            'subsidiary_id' => null,
            'specs' => null,
            'last_maintenance_at' => null,
            'next_maintenance_due_at' => null,
            'maintenance_interval_days' => null,
            'notes' => null,
        ], $overrides);
    }
}
