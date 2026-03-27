<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $table->json('asset_types')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $table->dropColumn('asset_types');
        });
    }
};
