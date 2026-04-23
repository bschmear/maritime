<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('subsidiary_id')
                ->nullable()
                ->after('work_order_id')
                ->constrained('subsidiaries')
                ->nullOnDelete();
            $table->foreignId('location_id')
                ->nullable()
                ->after('subsidiary_id')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('subsidiary_id');
        });
    }
};
