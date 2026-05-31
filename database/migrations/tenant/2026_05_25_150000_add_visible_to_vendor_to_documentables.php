<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documentables')) {
            return;
        }

        if (! Schema::hasColumn('documentables', 'visible_to_vendor')) {
            Schema::table('documentables', function (Blueprint $table) {
                $table->boolean('visible_to_vendor')->default(false)->after('visible_to_customer');
            });
        }

        DB::table('documentables')->update(['visible_to_vendor' => false]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('documentables') || ! Schema::hasColumn('documentables', 'visible_to_vendor')) {
            return;
        }

        Schema::table('documentables', function (Blueprint $table) {
            $table->dropColumn('visible_to_vendor');
        });
    }
};
