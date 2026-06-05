<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('featured');
        });

        $ids = DB::table('faqs')->orderBy('id')->pluck('id');
        foreach ($ids as $index => $id) {
            DB::table('faqs')->where('id', $id)->update(['sort_order' => $index]);
        }
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
