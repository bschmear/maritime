<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_checklist_items', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('completed'); // 'true', 'false', 'na'
        });

        // Migrate existing completed values: true -> 'true', false -> 'false'
        DB::table('delivery_checklist_items')
            ->where('completed', true)
            ->update(['status' => 'true']);

        DB::table('delivery_checklist_items')
            ->where('completed', false)
            ->update(['status' => 'false']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_checklist_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
