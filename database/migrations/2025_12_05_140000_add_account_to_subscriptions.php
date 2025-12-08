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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add account_id foreign key (nullable for backward compatibility)
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained('accounts')->onDelete('cascade');
            
            // Add plan_id to track which plan the subscription is for
            $table->foreignId('plan_id')->nullable()->after('type')->constrained('plans')->onDelete('set null');
            
            // Add billing_cycle to track monthly vs yearly
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->after('plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['account_id', 'plan_id', 'billing_cycle']);
        });
    }
};
