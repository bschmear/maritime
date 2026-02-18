<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->string('signed_name')->nullable()->after('customer_signature');
            $table->timestamp('declined_at')->nullable()->after('signature_hash');
            $table->text('decline_reason')->nullable()->after('declined_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropColumn(['signed_name', 'declined_at', 'decline_reason']);
        });
    }
};
