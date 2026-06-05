<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_user', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'kiosk_role_id']);
            $table->dropPrimary(['id']);
            $table->dropColumn('id');
            $table->primary(['user_id', 'kiosk_role_id']);
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_user', function (Blueprint $table) {
            $table->dropPrimary(['user_id', 'kiosk_role_id']);
        });

        Schema::table('kiosk_user', function (Blueprint $table) {
            $table->id()->first();
            $table->unique(['user_id', 'kiosk_role_id']);
        });
    }
};
