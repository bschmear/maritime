<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Fleet\MaintenanceTypeAppliesTo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaintenanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('maintenance_types')) {
            return;
        }

        if (DB::table('maintenance_types')->count() > 0) {
            return;
        }

        $rows = $this->definitions();
        foreach (array_chunk($rows, 40) as $chunk) {
            DB::table('maintenance_types')->insert($chunk);
        }
    }

    /**
     * @return list<array{display_name: string, category: string|null, applies_to: string, sort_order: int, created_at: \Illuminate\Support\Carbon, updated_at: \Illuminate\Support\Carbon}>
     */
    private function definitions(): array
    {
        $now = now();
        $n = 0;
        $mk = function (string $displayName, ?string $category, MaintenanceTypeAppliesTo $applies) use (&$n, $now) {
            return [
                'display_name' => $displayName,
                'category' => $category,
                'applies_to' => $applies->value,
                'sort_order' => ++$n,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        return array_merge(
            [
                $mk('Inspection (General)', 'General (Applies to All Fleet)', MaintenanceTypeAppliesTo::All),
                $mk('Preventive Maintenance Service', 'General (Applies to All Fleet)', MaintenanceTypeAppliesTo::All),
                $mk('Safety Inspection', 'General (Applies to All Fleet)', MaintenanceTypeAppliesTo::All),
                $mk('Cleaning / Wash', 'General (Applies to All Fleet)', MaintenanceTypeAppliesTo::All),
                $mk('Winterization', 'General (Applies to All Fleet)', MaintenanceTypeAppliesTo::All),
                $mk('De-winterization', 'General (Applies to All Fleet)', MaintenanceTypeAppliesTo::All),
            ],
            [
                $mk('Oil Change', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Oil Filter Replacement', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Fuel Filter Replacement', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Air Filter Replacement', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Cabin Air Filter Replacement', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Transmission Fluid Service', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Coolant Flush', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Brake Fluid Flush', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
                $mk('Power Steering Fluid Service', 'Truck — Engine / Fluids', MaintenanceTypeAppliesTo::Truck),
            ],
            [
                $mk('Tire Rotation', 'Truck — Tires & Wheels', MaintenanceTypeAppliesTo::Truck),
                $mk('Tire Replacement', 'Truck — Tires & Wheels', MaintenanceTypeAppliesTo::Truck),
                $mk('Tire Balancing', 'Truck — Tires & Wheels', MaintenanceTypeAppliesTo::Truck),
                $mk('Wheel Alignment', 'Truck — Tires & Wheels', MaintenanceTypeAppliesTo::Truck),
            ],
            [
                $mk('Brake Inspection', 'Truck — Brakes', MaintenanceTypeAppliesTo::Truck),
                $mk('Brake Pad Replacement', 'Truck — Brakes', MaintenanceTypeAppliesTo::Truck),
                $mk('Brake Rotor Replacement', 'Truck — Brakes', MaintenanceTypeAppliesTo::Truck),
                $mk('Brake System Service', 'Truck — Brakes', MaintenanceTypeAppliesTo::Truck),
            ],
            [
                $mk('Battery Test', 'Truck — Battery / Electrical', MaintenanceTypeAppliesTo::Truck),
                $mk('Battery Replacement', 'Truck — Battery / Electrical', MaintenanceTypeAppliesTo::Truck),
                $mk('Alternator Replacement', 'Truck — Battery / Electrical', MaintenanceTypeAppliesTo::Truck),
                $mk('Starter Replacement', 'Truck — Battery / Electrical', MaintenanceTypeAppliesTo::Truck),
            ],
            [
                $mk('Spark Plug Replacement', 'Truck — Drivetrain / Performance', MaintenanceTypeAppliesTo::Truck),
                $mk('Belt Replacement (Serpentine)', 'Truck — Drivetrain / Performance', MaintenanceTypeAppliesTo::Truck),
                $mk('Hose Replacement', 'Truck — Drivetrain / Performance', MaintenanceTypeAppliesTo::Truck),
                $mk('Suspension Inspection / Repair', 'Truck — Drivetrain / Performance', MaintenanceTypeAppliesTo::Truck),
            ],
            [
                $mk('Frame Inspection', 'Trailer — Trailer Structure', MaintenanceTypeAppliesTo::Trailer),
                $mk('Bunk Adjustment / Replacement', 'Trailer — Trailer Structure', MaintenanceTypeAppliesTo::Trailer),
                $mk('Roller Inspection / Replacement', 'Trailer — Trailer Structure', MaintenanceTypeAppliesTo::Trailer),
                $mk('Winch Inspection / Replacement', 'Trailer — Trailer Structure', MaintenanceTypeAppliesTo::Trailer),
                $mk('Strap / Cable Replacement', 'Trailer — Trailer Structure', MaintenanceTypeAppliesTo::Trailer),
            ],
            [
                $mk('Bearing Inspection', 'Trailer — Axles & Bearings', MaintenanceTypeAppliesTo::Trailer),
                $mk('Bearing Repack', 'Trailer — Axles & Bearings', MaintenanceTypeAppliesTo::Trailer),
                $mk('Bearing Replacement', 'Trailer — Axles & Bearings', MaintenanceTypeAppliesTo::Trailer),
                $mk('Axle Inspection / Repair', 'Trailer — Axles & Bearings', MaintenanceTypeAppliesTo::Trailer),
            ],
            [
                $mk('Brake Inspection', 'Trailer — Brakes (if equipped)', MaintenanceTypeAppliesTo::Trailer),
                $mk('Brake Pad Replacement', 'Trailer — Brakes (if equipped)', MaintenanceTypeAppliesTo::Trailer),
                $mk('Brake Line Inspection', 'Trailer — Brakes (if equipped)', MaintenanceTypeAppliesTo::Trailer),
                $mk('Brake Fluid Service (hydraulic trailers)', 'Trailer — Brakes (if equipped)', MaintenanceTypeAppliesTo::Trailer),
            ],
            [
                $mk('Light Inspection', 'Trailer — Lights / Electrical', MaintenanceTypeAppliesTo::Trailer),
                $mk('Wiring Repair', 'Trailer — Lights / Electrical', MaintenanceTypeAppliesTo::Trailer),
                $mk('Connector Replacement', 'Trailer — Lights / Electrical', MaintenanceTypeAppliesTo::Trailer),
            ],
            [
                $mk('Tire Inspection', 'Trailer — Tires', MaintenanceTypeAppliesTo::Trailer),
                $mk('Tire Replacement', 'Trailer — Tires', MaintenanceTypeAppliesTo::Trailer),
            ],
        );
    }
}
