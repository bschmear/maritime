<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bills')) {
            return;
        }

        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'financing_id')) {
                $table->foreignId('financing_id')
                    ->nullable()
                    ->after('vendor_id')
                    ->constrained('financings')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('bills', 'financing_bill_type')) {
                $table->enum('financing_bill_type', ['interest', 'principal', 'fee'])
                    ->nullable()
                    ->after('financing_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bills')) {
            return;
        }

        Schema::table('bills', function (Blueprint $table) {
            if (Schema::hasColumn('bills', 'financing_bill_type')) {
                $table->dropColumn('financing_bill_type');
            }
            if (Schema::hasColumn('bills', 'financing_id')) {
                $table->dropConstrainedForeignId('financing_id');
            }
        });
    }
};
