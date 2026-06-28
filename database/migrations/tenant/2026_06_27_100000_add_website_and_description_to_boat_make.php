<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            if (! Schema::hasColumn('boat_make', 'website_url')) {
                $table->string('website_url', 512)->nullable()->after('default_brand_image');
            }
            if (! Schema::hasColumn('boat_make', 'description')) {
                $table->text('description')->nullable()->after('website_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('boat_make', 'description')) {
                $columns[] = 'description';
            }
            if (Schema::hasColumn('boat_make', 'website_url')) {
                $columns[] = 'website_url';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
