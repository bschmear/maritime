<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_agreements', function (Blueprint $table) {
            $table->json('policies_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_agreements', function (Blueprint $table) {
            $table->dropColumn('policies_snapshot');
        });
    }
};
