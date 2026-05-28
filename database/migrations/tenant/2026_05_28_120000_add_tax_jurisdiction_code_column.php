<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'transactions', 'estimates'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tax_jurisdiction_code')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('tax_jurisdiction_code', 32)
                    ->nullable()
                    ->after('tax_jurisdiction');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'transactions', 'estimates'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tax_jurisdiction_code')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('tax_jurisdiction_code');
            });
        }
    }
};
