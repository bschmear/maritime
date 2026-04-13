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
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');

            $table->string('processor_refund_id')->nullable();
            $table->json('processor_response')->nullable();

            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
