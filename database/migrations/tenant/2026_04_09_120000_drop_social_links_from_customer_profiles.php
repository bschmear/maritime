<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $drop = collect(['website', 'linkedin', 'facebook'])
            ->filter(fn (string $c) => Schema::hasColumn('customer_profiles', $c))
            ->values()
            ->all();

        if ($drop === []) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('website')->nullable()->after('utm_content');
            $table->string('linkedin')->nullable()->after('website');
            $table->string('facebook')->nullable()->after('linkedin');
        });
    }
};
