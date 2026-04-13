<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('allowed_methods')->nullable();

            $table->decimal('surcharge_percent', 5, 2)->nullable();
            $table->boolean('allow_partial_payment')->default(false);
            $table->decimal('minimum_partial_amount', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['allowed_methods', 'surcharge_percent', 'allow_partial_payment', 'minimum_partial_amount']);
        });
    }
};
