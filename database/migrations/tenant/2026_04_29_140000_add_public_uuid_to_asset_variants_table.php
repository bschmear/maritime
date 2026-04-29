<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_variants', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
        });

        DB::table('asset_variants')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('asset_variants')->where('id', $row->id)->update([
                    'public_uuid' => (string) Str::uuid(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_variants', function (Blueprint $table) {
            $table->dropColumn('public_uuid');
        });
    }
};
