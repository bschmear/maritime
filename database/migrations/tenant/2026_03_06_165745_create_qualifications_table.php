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
        Schema::create('qualifications', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')
                  ->unique();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('createdby_id')->references('id')->on('users')->onDelete('set null');
            $table->string('status')->default('pending');
            $table->string('intended_use')->nullable();
            $table->string('ownership_type')->nullable();
            $table->foreignId('desired_brand')->references('id')->on('boat_make')->onDelete('set null');
            $table->string('desired_model')->nullable();
            $table->integer('preferred_length')->nullable();
            $table->integer('max_weight')->nullable();

            $table->boolean('needs_engine')->default(false);
            $table->boolean('needs_trailer')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Budget
            |--------------------------------------------------------------------------
            */
            $table->unsignedTinyInteger('budget_range')->default(1);
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timeline
            |--------------------------------------------------------------------------
            */

            $table->string('purchase_timeline')->nullable();

            $table->string('delivery_location')->nullable();
            $table->string('delivery_state')->nullable();
            $table->string('delivery_country')->nullable();

            $table->boolean('requires_delivery')->default(false);

            $table->string('lead_source')->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qualifications');
    }
};
