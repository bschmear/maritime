<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * boat_types.json was missing "raft" while App\Enums\Inventory\BoatType::Raft exists.
 * Achilles meta.json (rv-series, etc.) references boat_type_key "raft".
 */
return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::connection($this->connection)->hasTable('boat_type')) {
            return;
        }

        $now = now();
        DB::connection($this->connection)->table('boat_type')->upsert(
            [
                [
                    'slug' => 'raft',
                    'display_name' => 'Raft',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['slug'],
            ['display_name', 'active', 'updated_at']
        );
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::connection($this->connection)->hasTable('boat_type')) {
            return;
        }

        DB::connection($this->connection)->table('boat_type')->where('slug', 'raft')->delete();
    }
};
