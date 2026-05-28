<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('quickbooks_invoice_id')->nullable()->after('meta');
            $table->string('quickbooks_invoice_url', 512)->nullable()->after('quickbooks_invoice_id');

            $table->index('quickbooks_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['quickbooks_invoice_id']);
            $table->dropColumn(['quickbooks_invoice_id', 'quickbooks_invoice_url']);
        });
    }
};
