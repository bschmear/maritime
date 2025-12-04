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

            /**
             * Primary Contact Data
             */
            $table->string('display_name')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();

            /**
             * Secondary / Business Info
             */
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->string('title')->nullable();
            $table->string('secondary_email')->nullable();

            /**
             * Address
             */
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /**
             * Notes
             */
            $table->text('notes')->nullable();

            /**
             * Contact Management
             */
            $table->tinyInteger('status_id')->default(1);
            $table->tinyInteger('source_id')->nullable();
            $table->tinyInteger('priority_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();

            /**
             * Contact Timeline
             */
            $table->dateTime('last_contacted_at')->nullable();
            $table->dateTime('next_followup_at')->nullable();

            /**
             * Lead Intelligence (copied from Lead -> Contact at conversion)
             */
            $table->unsignedSmallInteger('lead_score')->nullable()->comment('Numeric lead quality score');
            $table->string('campaign')->nullable(); // internal campaign name
            $table->string('medium')->nullable(); // channel: email, paid ads, organic
            $table->string('source_details')->nullable(); // additional breakdown
            $table->string('referrer')->nullable(); // friend, broker, partner
            $table->string('preferred_contact_method')->nullable(); // phone / email / sms
            $table->string('preferred_contact_time')->nullable(); // morning / afternoon / evening
            $table->string('purchase_timeline')->nullable(); // timeframe to buy

            /**
             * Budget + Product Interest
             */
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('interested_model')->nullable();

            /**
             * Trade-in Info
             */
            $table->boolean('has_trade_in')->default(false);
            $table->decimal('trade_in_value', 12, 2)->nullable();

            /**
             * Marketing / Communication Preferences
             */
            $table->boolean('marketing_opt_in')->default(false);

            /**
             * UTM Tracking (from ad campaigns)
             */
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            /**
             * Optional Web / Social
             */
            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();

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
