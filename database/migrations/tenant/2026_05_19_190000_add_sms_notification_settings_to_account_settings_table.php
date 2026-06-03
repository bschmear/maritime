<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->boolean('sms_enabled')->default(false)->after('consignment_terms');
            $table->boolean('sandbox_mode')->default(true)->after('sms_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn(['sms_enabled', 'sandbox_mode']);
        });
    }
};
