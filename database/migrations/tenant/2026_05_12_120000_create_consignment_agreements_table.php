<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('sequence')->unique();

            $table->foreignId('asset_unit_id')
                ->constrained('asset_units')
                ->cascadeOnDelete();

            $table->date('agreement_date')->nullable();

            $table->text('boat_description')->nullable();
            $table->text('motor_description')->nullable();
            $table->text('other_description')->nullable();

            $table->boolean('boat_title_signed_delivered')->default(false);

            $table->foreignId('owner_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignId('owner_contact_address_id')
                ->nullable()
                ->constrained('contact_addresses')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->decimal('asking_boat', 12, 2)->nullable();
            $table->decimal('asking_motor', 12, 2)->nullable();
            $table->decimal('asking_other', 12, 2)->nullable();
            $table->decimal('asking_sold', 12, 2)->nullable();

            $table->decimal('minimum_boat', 12, 2)->nullable();
            $table->decimal('minimum_motor', 12, 2)->nullable();
            $table->decimal('minimum_other', 12, 2)->nullable();
            $table->decimal('minimum_sold', 12, 2)->nullable();

            $table->timestamp('signed_at')->nullable();
            $table->string('signed_name')->nullable();
            $table->string('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->string('signature_file')->nullable();
            $table->string('signature_hash')->nullable();
            $table->text('customer_signature')->nullable();
            $table->unsignedTinyInteger('signature_method')->nullable()
                ->comment('1=draw, 5=type');

            $table->timestamps();

            $table->index(['asset_unit_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_agreements');
    }
};
