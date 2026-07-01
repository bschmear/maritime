<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_menu_items', function (Blueprint $table) {
            $table->string('requires_integration')->nullable()->after('permission_key');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_menu_items', function (Blueprint $table) {
            $table->dropColumn('requires_integration');
        });
    }
};
