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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Company Details
            $table->string('company_name');
            $table->string('vendor_type')->nullable(); // dealer, manufacturer, lender, service, parts, etc

            // Primary Contact Person
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // Secondary contact (optional)
            $table->string('secondary_email')->nullable();
            $table->string('secondary_phone')->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            // Relationship / CRM
            $table->tinyInteger('status_id')->default(1);
            // active, inactive, partner, preferred, blacklisted, etc (I can build enum)

            $table->unsignedBigInteger('assigned_user_id')->nullable(); // relationship owner
            $table->text('notes')->nullable();

            // Financial (optional depending on business)
            $table->string('payment_terms')->nullable(); // Net 30, COD, etc.
            $table->decimal('credit_limit', 12, 2)->nullable();

            // Links / Online
            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();

            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
