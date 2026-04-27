<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $table->string('brand_key')->nullable()->after('slug');
        });

        Schema::table('boat_make', function (Blueprint $table) {
            $table->unique('brand_key');
        });
    }

    public function down(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $table->dropUnique(['brand_key']);
            $table->dropColumn('brand_key');
        });
    }
};
