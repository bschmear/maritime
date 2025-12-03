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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Primary
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();

            // Secondary
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->string('title')->nullable();
            $table->string('secondary_email')->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Notes
            $table->text('notes')->nullable();

            // CRM management
            $table->tinyInteger('status_id')->default(1);
            $table->tinyInteger('source_id')->nullable();
            $table->tinyInteger('priority_id')->nullable();
            // Note: assigned_user_id references central users table, so we can't use foreign key constraint
            // across schemas in PostgreSQL. Referential integrity should be handled in application code.
            $table->unsignedBigInteger('assigned_user_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
