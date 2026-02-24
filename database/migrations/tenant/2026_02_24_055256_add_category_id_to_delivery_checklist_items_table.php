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
            $table->foreignId('category_id')->nullable()->after('category');
        });

        // Map existing category values to new category IDs
        $preDeliveryId = DB::table('delivery_checklist_categories')->where('name', 'Pre Delivery')->first()->id;
        $uponDeliveryId = DB::table('delivery_checklist_categories')->where('name', 'Upon Delivery')->first()->id;

        DB::table('delivery_checklist_items')
            ->where('category', 'pre_delivery')
            ->update(['category_id' => $preDeliveryId]);

        DB::table('delivery_checklist_items')
            ->where('category', 'upon_delivery')
            ->update(['category_id' => $uponDeliveryId]);

        Schema::table('delivery_checklist_items', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreign('category_id')->references('id')->on('delivery_checklist_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_checklist_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
