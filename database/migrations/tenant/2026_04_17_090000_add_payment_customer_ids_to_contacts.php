<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // External payment-processor customer IDs. Both are scoped to this tenant.
            // Kept unique per tenant so we can quickly resolve a contact from a webhook payload.
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('quickbooks_customer_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropUnique(['quickbooks_customer_id']);
            $table->dropColumn(['stripe_customer_id', 'quickbooks_customer_id']);
        });
    }
};
