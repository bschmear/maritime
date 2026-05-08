<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_checklist_template_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category');
        });

        $ensureCategoryId = static function (string $name, string $color): int {
            $row = DB::table('delivery_checklist_categories')->where('name', $name)->first();
            if ($row !== null) {
                return (int) $row->id;
            }

            return (int) DB::table('delivery_checklist_categories')->insertGetId([
                'name' => $name,
                'color' => $color,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $preDeliveryId = $ensureCategoryId('Pre Delivery', 'blue');
        $uponDeliveryId = $ensureCategoryId('Upon Delivery', 'green');

        DB::table('delivery_checklist_template_items')
            ->where('category', 'pre_delivery')
            ->update(['category_id' => $preDeliveryId]);

        DB::table('delivery_checklist_template_items')
            ->where('category', 'upon_delivery')
            ->update(['category_id' => $uponDeliveryId]);

        Schema::table('delivery_checklist_template_items', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreign('category_id')->references('id')->on('delivery_checklist_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_checklist_template_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
