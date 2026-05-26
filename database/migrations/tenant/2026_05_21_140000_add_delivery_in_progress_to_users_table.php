<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'delivery_in_progress')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('delivery_in_progress')->default(false)->after('is_technician');
                $table->index('delivery_in_progress');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'delivery_in_progress')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['delivery_in_progress']);
                $table->dropColumn('delivery_in_progress');
            });
        }
    }
};
