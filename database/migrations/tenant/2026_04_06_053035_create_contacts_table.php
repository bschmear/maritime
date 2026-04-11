<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('type')->default('person'); // person | company

            $table->string('email')->nullable()->index();
            $table->string('secondary_email')->nullable();

            $table->string('phone')->nullable()->index();
            $table->string('mobile')->nullable();

            $table->string('company')->nullable();
            $table->string('title')->nullable();
            $table->string('position')->nullable();

            $table->foreignId('assigned_user_id')->nullable()->index();

            $table->string('preferred_contact_method')->nullable(); // email | phone | text
            $table->string('preferred_contact_time')->nullable();   // morning | afternoon | evening

            $table->string('source')->nullable(); // website, referral, etc.
            $table->string('status')->default(1);
            $table->tinyInteger('stage_id')->default(1);
            $table->boolean('inactive')->default(false);

            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index(['email', 'phone']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('contact_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contact_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label')->nullable();
            // Home, Billing, Work, Marina, Storage, etc.

            $table->boolean('is_primary')->default(false);

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable()->index();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->index(['contact_id', 'is_primary']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('contact_addresses');
        Schema::dropIfExists('contacts');
    }
};
