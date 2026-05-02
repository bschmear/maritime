<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warrantyclaims', function (Blueprint $table) {
            $table->boolean('approved_by_vendor')->default(false)->after('notes');
            $table->timestamp('vendor_approved_at')->nullable()->after('approved_by_vendor');
            $table->foreignId('vendor_approved_by_contact_id')
                ->nullable()
                ->after('vendor_approved_at')
                ->constrained('contacts')
                ->nullOnDelete();
            $table->text('vendor_notes')->nullable()->after('vendor_approved_by_contact_id');
            $table->timestamp('vendor_rejected_at')->nullable()->after('vendor_notes');
            $table->foreignId('vendor_rejected_by_contact_id')
                ->nullable()
                ->after('vendor_rejected_at')
                ->constrained('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warrantyclaims', function (Blueprint $table) {
            $table->dropForeign(['vendor_approved_by_contact_id']);
            $table->dropForeign(['vendor_rejected_by_contact_id']);
            $table->dropColumn([
                'approved_by_vendor',
                'vendor_approved_at',
                'vendor_approved_by_contact_id',
                'vendor_notes',
                'vendor_rejected_at',
                'vendor_rejected_by_contact_id',
            ]);
        });
    }
};
