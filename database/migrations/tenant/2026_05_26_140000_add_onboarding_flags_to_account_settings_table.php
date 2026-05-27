<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->boolean('onboarding_complete')->default(false)->after('sandbox_mode');
            $table->boolean('account_overviewed')->default(false)->after('onboarding_complete');
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn(['onboarding_complete', 'account_overviewed']);
        });
    }
};
