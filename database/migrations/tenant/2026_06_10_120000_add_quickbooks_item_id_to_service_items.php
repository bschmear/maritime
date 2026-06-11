<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->string('quickbooks_item_id', 64)->nullable()->unique()->after('code');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('service_item_id')
                ->nullable()
                ->after('transaction_line_item_id')
                ->constrained('service_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_item_id');
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->dropUnique(['quickbooks_item_id']);
            $table->dropColumn('quickbooks_item_id');
        });
    }
};
