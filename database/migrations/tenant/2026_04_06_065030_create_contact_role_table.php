<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        // Lead Profiles
        // ─────────────────────────────────────────────
        Schema::create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();

            // Scoring & qualification
            $table->integer('lead_score')->nullable();
            $table->string('lead_status')->nullable();         // new | contacted | qualified | disqualified
            $table->string('qualification_stage')->nullable(); // mql | sql | opportunity
            $table->boolean('is_qualified')->default(false);
            $table->date('qualified_at')->nullable();

            // Assignment & follow-up
            $table->foreignId('assigned_user_id')->nullable()->index();
            $table->foreignId('priority_id')->nullable();
            $table->date('last_contacted_at')->nullable();
            $table->date('next_followup_at')->nullable();
            $table->integer('contact_attempts')->default(0);

            // Acquisition / attribution
            $table->foreignId('source_id')->nullable();
            $table->string('source_details')->nullable();      // e.g. "Google Ad - Brand Campaign"
            $table->string('referrer')->nullable();            // referral contact name / URL
            $table->string('campaign')->nullable();
            $table->string('medium')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            // Purchase intent
            $table->string('purchase_timeline')->nullable();   // enum: immediate | 1_month | 3_months | 6_months | unknown
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('interested_model')->nullable();    // product / model they enquired about
            $table->boolean('has_trade_in')->default(false);
            $table->decimal('trade_in_value', 12, 2)->nullable();

            // Consent
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('opted_in_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'lead_status']);
            $table->index(['assigned_user_id', 'next_followup_at']);
        });

        // ─────────────────────────────────────────────
        // Customer Profiles
        // ─────────────────────────────────────────────
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();

            // Account standing
            $table->string('account_status')->nullable();      // active | on_hold | suspended | closed
            $table->string('customer_type')->nullable();       // retail | wholesale | fleet | corporate
            $table->string('tier')->nullable();                // standard | silver | gold | platinum
            $table->foreignId('assigned_user_id')->nullable()->index(); // account manager

            // Financials
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->integer('payment_terms')->nullable();      // net days
            $table->string('payment_method')->nullable();      // card | bank_transfer | account | cash
            $table->string('currency')->default('USD');

            // Tax & billing
            $table->string('tax_id')->nullable();              // ABN, EIN, VAT, etc.
            $table->boolean('tax_exempt')->default(false);
            $table->string('tax_exempt_reason')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('purchase_order_required')->nullable(); // boolean-ish: yes | no | over_threshold

            // Contract / relationship
            $table->date('first_purchase_at')->nullable();
            $table->date('last_purchase_at')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->boolean('auto_renew')->default(false);

            // Loyalty / value
            $table->decimal('lifetime_value', 12, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('average_order_value', 12, 2)->nullable();
            $table->integer('loyalty_points')->default(0);

            // Attribution (how they became a customer)
            $table->foreignId('converted_from_lead_id')->nullable(); // lead_profiles.id
            $table->foreignId('source_id')->nullable();
            $table->string('referrer')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'account_status']);
            // assigned_user_id already indexed via ->index() on foreignId() above
        });

        // ─────────────────────────────────────────────
        // Vendor Profiles
        // ─────────────────────────────────────────────
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();

            // Classification
            $table->string('vendor_type')->nullable();         // supplier | contractor | subcontractor | distributor | manufacturer
            $table->string('industry')->nullable();
            $table->string('business_number')->nullable();     // ABN, EIN, VAT, company reg, etc.
            $table->string('currency')->default('USD');

            // Verification & compliance
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable();
            $table->boolean('is_approved')->default(false);    // internal procurement approval
            $table->timestamp('approved_at')->nullable();
            $table->string('compliance_status')->nullable();   // compliant | review_needed | non_compliant
            $table->date('insurance_expiry')->nullable();
            $table->date('license_expiry')->nullable();

            // Financial
            $table->integer('payment_terms')->nullable();      // net days
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->string('payment_method')->nullable();      // bank_transfer | check | card
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_routing_number')->nullable();
            $table->string('bank_name')->nullable();

            // Service & performance
            $table->string('account_manager_name')->nullable(); // their rep for us
            $table->string('account_manager_email')->nullable();
            $table->string('account_manager_phone')->nullable();
            $table->integer('lead_time_days')->nullable();     // typical fulfillment lead time
            $table->decimal('rating', 3, 2)->nullable();       // 0.00 – 5.00 internal rating
            $table->date('last_order_at')->nullable();
            $table->decimal('total_spend', 12, 2)->default(0);

            // Contract
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('contract_reference')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'is_approved']);
            $table->index('compliance_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('lead_profiles');
    }
};
