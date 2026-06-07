<?php

declare(strict_types=1);

use App\Domain\ConsignmentPolicy\Models\ConsignmentPolicy;
use App\Models\AccountSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ConsignmentPolicy::ensureDefaultsExist();
        AccountSettings::ensureConsignmentDefaults();
    }

    public function down(): void
    {
        // Reference data — leave tenant policies intact on rollback.
    }
};
