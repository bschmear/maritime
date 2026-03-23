<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('estimates', 'transaction_id')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->foreignId('transaction_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('transactions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('estimates', 'transaction_id')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('transaction_id');
            });
        }
    }
};
