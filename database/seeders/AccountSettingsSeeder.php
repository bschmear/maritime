<?php

namespace Database\Seeders;

use App\Models\AccountSettings;
use Illuminate\Database\Seeder;

class AccountSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only create if it doesn't exist
        if (! AccountSettings::first()) {
            AccountSettings::create([
                'timezone' => 'America/Chicago',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'currency' => 'USD',
                'week_starts_on_monday' => false,
                'auto_assign_work_orders' => false,
                'brand_color' => '#3B82F6', // Blue-500
                'workday_hours' => 6,
                'start_time' => '08:00:00',
                'allow_overlap' => false,
            ]);
        }
    }
}
