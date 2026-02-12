<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->uuid('uuid')
                  ->nullable()
                  ->after('id')
                  ->unique();
        });

        // Backfill existing rows
        DB::table('work_orders')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($workOrders) {
                foreach ($workOrders as $workOrder) {
                    DB::table('work_orders')
                        ->where('id', $workOrder->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        // Make it NOT NULL after backfill
        Schema::table('work_orders', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
