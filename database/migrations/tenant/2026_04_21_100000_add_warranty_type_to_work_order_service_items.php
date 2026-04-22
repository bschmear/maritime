<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_service_items', function (Blueprint $table) {
            $table->string('warranty_type', 32)->nullable()->after('warranty');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_service_items', function (Blueprint $table) {
            $table->dropColumn('warranty_type');
        });
    }
};
