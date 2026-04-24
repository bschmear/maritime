<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_maintenance_log_maintenance_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_maintenance_log_id')
                ->constrained('fleet_maintenance_logs')
                ->cascadeOnDelete();
            $table->foreignId('maintenance_type_id')
                ->constrained('maintenance_types')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['fleet_maintenance_log_id', 'maintenance_type_id'],
                'fm_log_mt_unique'
            );
        });

        $rows = DB::table('fleet_maintenance_logs')
            ->whereNotNull('type_id')
            ->get(['id', 'type_id']);

        $now = now();
        foreach ($rows as $row) {
            DB::table('fleet_maintenance_log_maintenance_type')->insert([
                'fleet_maintenance_log_id' => $row->id,
                'maintenance_type_id' => $row->type_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('fleet_maintenance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_maintenance_logs', function (Blueprint $table) {
            $table->foreignId('type_id')
                ->nullable()
                ->after('performed_at')
                ->constrained('maintenance_types')
                ->nullOnDelete();
        });

        $pairs = DB::table('fleet_maintenance_log_maintenance_type')
            ->orderBy('fleet_maintenance_log_id')
            ->orderBy('id')
            ->get(['fleet_maintenance_log_id', 'maintenance_type_id']);

        foreach ($pairs as $p) {
            DB::table('fleet_maintenance_logs')
                ->where('id', $p->fleet_maintenance_log_id)
                ->whereNull('type_id')
                ->update(['type_id' => $p->maintenance_type_id]);
        }

        Schema::dropIfExists('fleet_maintenance_log_maintenance_type');
    }
};
