<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customer_profiles', 'subsidiary_id')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->foreignId('subsidiary_id')
                ->after('contact_id')
                ->constrained('subsidiaries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customer_profiles', 'subsidiary_id')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropForeign(['subsidiary_id']);
        });

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn('subsidiary_id');
        });
    }
};
