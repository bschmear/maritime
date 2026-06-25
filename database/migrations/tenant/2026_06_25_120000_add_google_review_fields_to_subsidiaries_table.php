<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subsidiaries', function (Blueprint $table) {
            $table->string('google_review_url', 500)->nullable()->after('website');
            $table->boolean('prompt_google_review_on_transaction_close')->default(false)->after('google_review_url');
        });
    }

    public function down(): void
    {
        Schema::table('subsidiaries', function (Blueprint $table) {
            $table->dropColumn(['google_review_url', 'prompt_google_review_on_transaction_close']);
        });
    }
};
