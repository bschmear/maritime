<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_ticket_service_items', function (Blueprint $table) {
            $table->string('warranty_type', 32)->nullable()->after('warranty');
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->string('warranty_type', 32)->nullable()->after('warranty_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('service_ticket_service_items', function (Blueprint $table) {
            $table->dropColumn('warranty_type');
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn('warranty_type');
        });
    }
};
