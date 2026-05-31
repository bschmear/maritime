<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_profiles', 'dl_front_id')) {
                $table->foreignId('dl_front_id')
                    ->nullable()
                    ->constrained('inventory_images')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_profiles', 'dl_back_id')) {
                $table->foreignId('dl_back_id')
                    ->nullable()
                    ->constrained('inventory_images')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profiles', 'dl_back_id')) {
                $table->dropConstrainedForeignId('dl_back_id');
            }
            if (Schema::hasColumn('customer_profiles', 'dl_front_id')) {
                $table->dropConstrainedForeignId('dl_front_id');
            }
        });
    }
};
