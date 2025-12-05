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
            $table->string('display_name');
            $table->tinyInteger('vendor_type')->nullable(); // dealer, manufacturer, lender, service, parts, etc
            $table->string('industry')->nullable(); // HVAC, roofing, staging, lender, photography
        
            // Vendor Code for internal reference (short ID)
            $table->string('vendor_code')->unique()->nullable();
        
            // Tags for flexible grouping
            $table->json('tags')->nullable(); // ["preferred", "wholesale"]
        
            // Primary Contact Person
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
        
            // Additional contacts in JSON
            $table->json('contacts')->nullable(); 
            // [{ "name": "...", "email": "...", "phone": "...", "title": "..." }]
        
            // Secondary Contact
            $table->string('secondary_email')->nullable();
            $table->string('secondary_phone')->nullable();
        
            // Preferred contact method
            $table->tinyInteger('preferred_contact_method')->nullable(); // phone, email, portal
        
            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        
            // Relationship and CRM
            $table->tinyInteger('status_id')->default(1); 
            // active, inactive, partner, preferred, blacklisted, etc.
        
            $table->string('status_reason')->nullable(); 
            // reason for status changes, example: stopped responding
        
            $table->unsignedBigInteger('assigned_user_id')->nullable(); // relationship owner
            $table->text('notes')->nullable();
        
            // Rating
            $table->unsignedTinyInteger('rating')->nullable(); // 1 to 5
        
            // Financial
            $table->tinyInteger('payment_terms')->nullable(); // Net 30, COD
            $table->decimal('credit_limit', 12, 2)->nullable();
        
            // Links and Online
            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();
        
            // Contract Info
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->tinyInteger('contract_status')->nullable(); // active, pending, expired
        
            // Verification and compliance
            $table->boolean('is_verified')->default(false);
            $table->date('verified_at')->nullable();
        
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
