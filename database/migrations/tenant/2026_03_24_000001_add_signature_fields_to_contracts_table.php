<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->text('customer_signature')->nullable()->after('signature_hash');
            $table->unsignedTinyInteger('signature_method')->nullable()->after('customer_signature')
                ->comment('1=draw, 5=type');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['customer_signature', 'signature_method']);
        });
    }
};
