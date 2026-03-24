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
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subsidiary_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider')->default('stripe'); // stripe, quickbooks, etc
            $table->string('account_id')->unique(); // acct_xxx (Stripe) or realm_id (QB)

            $table->string('status')->default('not_connected');

            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);

            $table->boolean('restricted')->default(false);

            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('business_type')->nullable();

            $table->json('requirements')->nullable();

            $table->json('settings')->nullable(); // config per provider
            $table->json('metadata')->nullable(); // raw API data if needed

            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->index(['provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
