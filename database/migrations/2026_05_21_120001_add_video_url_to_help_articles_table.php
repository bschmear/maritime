<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('help_articles', 'video_url')) {
            return;
        }

        Schema::table('help_articles', function (Blueprint $table) {
            $table->text('video_url')->nullable()->after('excerpt');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('help_articles', 'video_url')) {
            return;
        }

        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
