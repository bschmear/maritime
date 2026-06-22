<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_oauth_handoffs', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_user_profile_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_handoffs', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_user_profile_id')->nullable(false)->change();
        });
    }
};
