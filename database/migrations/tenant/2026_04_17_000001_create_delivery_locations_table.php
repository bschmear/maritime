<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_locations')) {
            return;
        }

        Schema::create('delivery_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();

            $table->unsignedBigInteger('subsidiary_id')->nullable()->index();

            $table->string('name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['subsidiary_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_locations');
    }
};
