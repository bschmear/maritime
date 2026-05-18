<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();

            // 🔗 Relationship to CRM contact
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // 📄 Raw barcode / scan data (AAMVA string)
            // $table->text('raw_barcode')->nullable();

            // 🧠 Parsed structured fields
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();

            $table->date('date_of_birth')->nullable();

            $table->string('license_number')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->nullable()->default('US');

            // 📅 License metadata
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('issuing_state', 10)->nullable();

            // 📊 AI / processing metadata
            $table->string('scan_source')->default('barcode');
            // barcode | ocr | manual

            // $table->decimal('confidence_score', 5, 2)->nullable();
            // e.g. 0.95

            // $table->boolean('is_verified')->default(false);

            // 🔐 Optional dedupe / matching support
            $table->string('hash')->nullable()->index();
            // hash of normalized license_number + state

            $table->timestamps();

            // 🔍 Indexes for fast CRM lookups
            $table->index(['license_number', 'issuing_state']);
            $table->index('contact_id');
            $table->index('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
