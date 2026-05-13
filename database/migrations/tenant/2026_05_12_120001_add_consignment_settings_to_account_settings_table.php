<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->decimal('consignment_fee_percent', 5, 2)
                ->default(20.00)
                ->after('allow_overlap');
            $table->text('consignment_terms')->nullable()->after('consignment_fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn(['consignment_fee_percent', 'consignment_terms']);
        });
    }
};
