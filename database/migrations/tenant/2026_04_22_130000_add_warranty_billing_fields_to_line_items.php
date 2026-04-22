<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_ticket_service_items', function (Blueprint $table) {
            $table->string('billable_to', 32)->default('customer')->after('warranty_type');
        });

        Schema::table('work_order_service_items', function (Blueprint $table) {
            $table->string('billable_to', 32)->default('customer')->after('warranty_type');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->boolean('is_warranty')->default(false)->after('discount');
            $table->string('warranty_type', 32)->nullable()->after('is_warranty');
            $table->string('billable_to', 32)->default('customer')->after('warranty_type');
            $table->decimal('cost', 10, 2)->default(0)->after('unit_price');
        });

        DB::statement("
            UPDATE service_ticket_service_items
            SET billable_to = CASE
                WHEN COALESCE(warranty, false) = false THEN 'customer'
                WHEN warranty_type = 'manufacturer' THEN 'manufacturer'
                ELSE 'internal'
            END
        ");

        DB::statement("
            UPDATE work_order_service_items
            SET billable_to = CASE
                WHEN COALESCE(warranty, false) = false THEN 'customer'
                WHEN warranty_type = 'manufacturer' THEN 'manufacturer'
                ELSE 'internal'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_warranty', 'warranty_type', 'billable_to', 'cost']);
        });

        Schema::table('work_order_service_items', function (Blueprint $table) {
            $table->dropColumn('billable_to');
        });

        Schema::table('service_ticket_service_items', function (Blueprint $table) {
            $table->dropColumn('billable_to');
        });
    }
};
