<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('workday_hours')->default(6)->after('auto_assign_work_orders');
            $table->time('start_time')->default('08:00:00')->after('workday_hours');
            $table->boolean('allow_overlap')->default(false)->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn(['workday_hours', 'start_time', 'allow_overlap']);
        });
    }
};
