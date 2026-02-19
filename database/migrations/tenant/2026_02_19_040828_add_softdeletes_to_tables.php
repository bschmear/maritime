<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
