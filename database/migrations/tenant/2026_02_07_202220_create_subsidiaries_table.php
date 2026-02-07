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
        Schema::create('subsidiaries', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('code', 50)->nullable()->index();

            // Status
            $table->boolean('inactive')->default(false);

            // Contact info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('US')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Operational
            $table->string('timezone')->nullable();
            $table->decimal('default_labor_rate', 10, 2)->nullable();

            // Work order config
            $table->string('work_order_prefix', 10)->nullable();
            $table->unsignedInteger('next_work_order_number')->default(1000);

            // Branding
            $table->string('logo')->nullable();

            // Meta
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['inactive', 'display_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subsidiaries');
    }
};
