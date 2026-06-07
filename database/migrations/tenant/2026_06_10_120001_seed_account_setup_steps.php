<?php

declare(strict_types=1);

use Database\Seeders\AccountSetupStepSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AccountSetupStepSeeder::seed();
    }

    public function down(): void
    {
        // Reference data — leave progress rows intact on rollback.
    }
};
