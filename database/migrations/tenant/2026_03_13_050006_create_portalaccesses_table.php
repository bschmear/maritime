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
        Schema::create('portal_accesses', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Secure access token used in URL
            $table->string('token', 128)->unique();

            // Customer the portal access belongs to
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            // What record this token provides access to
            $table->string('record_type'); // estimate, contract, invoice, delivery, document
            $table->unsignedBigInteger('record_id');

            // Optional permissions (future expansion)
            $table->json('permissions')->nullable();

            // Security / auditing
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // Internal tracking
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['record_type', 'record_id']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_accesses');
    }
};
