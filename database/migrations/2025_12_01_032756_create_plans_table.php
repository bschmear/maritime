<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Basic, Pro, Agency
            $table->decimal('monthly_price', 8, 2)->nullable();
            $table->decimal('yearly_price', 8, 2)->nullable();
            $table->string('stripe_monthly_id')->nullable();
            $table->string('stripe_yearly_id')->nullable();
            $table->integer('seat_limit')->default(1);
            $table->integer('seat_extra')->default(0)->nullable();
            $table->text('description')->nullable();
            $table->json('included')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('popular')->default(false);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->string('feature');
            $table->timestamps();
        });

        // Optional: Add-ons or extra items
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->string('name'); // e.g., "Extra seat"
            $table->string('stripe_price_id')->nullable(); // Stripe price ID for add-on
            $table->decimal('price', 8, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plan_items');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
