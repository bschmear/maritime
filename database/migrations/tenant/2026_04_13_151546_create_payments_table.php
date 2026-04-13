<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_settings_id')
                ->constrained('account_settings')
                ->cascadeOnDelete();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->enum('processor', ['stripe', 'quickbooks']);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            // Stripe Connect (Express): platform API key in env; only connected account id stored here
            $table->string('stripe_account_id')->nullable();
            $table->boolean('stripe_charges_enabled')->default(false);
            $table->boolean('stripe_payouts_enabled')->default(false);
            $table->string('stripe_publishable_key')->nullable();
            $table->text('stripe_secret_key_enc')->nullable(); // optional non-Connect use; encrypted at app layer

            // QuickBooks fields
            $table->string('qbo_realm_id')->nullable();
            $table->text('qbo_access_token_enc')->nullable();
            $table->text('qbo_refresh_token_enc')->nullable();
            $table->timestamp('qbo_token_expires_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['account_settings_id', 'processor']);
        });

        Schema::create('payment_methods_config', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('processor_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')
                ->constrained('payments_configurations')
                ->cascadeOnDelete();
            $table->string('payment_method_code', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['configuration_id', 'payment_method_code']);
        });

        DB::table('payment_methods_config')->insert([
            ['code' => 'credit_card', 'label' => 'Credit / Debit Card', 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ach', 'label' => 'ACH / Bank Transfer', 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'check', 'label' => 'Check', 'position' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'cash', 'label' => 'Cash', 'position' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'wire', 'label' => 'Wire Transfer', 'position' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'financing', 'label' => 'Financing', 'position' => 6, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedBigInteger('sequence')->unique();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('configuration_id')
                ->nullable()
                ->constrained('payments_configurations')
                ->nullOnDelete();

            $table->string('payment_method_code', 50);

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'refunded',
                'partially_refunded',
                'voided',
            ])->default('pending');

            $table->decimal('amount', 12, 2);
            $table->decimal('surcharge_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->char('currency', 3)->default('USD');

            $table->enum('processor', ['stripe', 'quickbooks', 'manual'])->nullable();
            $table->string('processor_transaction_id')->nullable()->index();
            $table->string('processor_status')->nullable();
            $table->json('processor_response')->nullable();

            $table->string('reference_number')->nullable();
            $table->text('memo')->nullable();

            $table->string('payment_last4', 4)->nullable();
            $table->string('payment_brand', 50)->nullable();

            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('processor_payment_methods');
        Schema::dropIfExists('payment_methods_config');
        Schema::dropIfExists('payments_configurations');
    }
};
