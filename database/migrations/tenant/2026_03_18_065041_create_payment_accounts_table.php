<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->string('payment_provider')
                ->default('stripe')
                ->after('currency')
                ->index();
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_settings_id')->index();

            $table->string('provider');
            $table->boolean('is_active')->default(true);

            $table->json('data')->nullable();

            $table->string('external_account_id')->nullable();
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);

            $table->timestamp('connected_at')->nullable();

            $table->timestamps();

            $table->index(['account_settings_id', 'provider']);
            $table->unique(['account_settings_id', 'provider']);
            $table->index('external_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');

        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });
    }
};
