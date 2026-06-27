<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $table->boolean('use_default_logo')->default(true)->after('is_custom');
            $table->string('default_brand_image', 512)->nullable()->after('use_default_logo');
            $table->unsignedBigInteger('custom_logo_id')->nullable()->after('default_brand_image');
        });

        if (Schema::hasColumn('boat_make', 'logo')) {
            $rows = DB::table('boat_make')
                ->whereNotNull('logo')
                ->where('logo', '!=', '')
                ->get(['id', 'logo']);

            foreach ($rows as $row) {
                $documentId = is_numeric($row->logo) ? (int) $row->logo : null;
                if ($documentId === null) {
                    continue;
                }

                DB::table('boat_make')
                    ->where('id', $row->id)
                    ->update([
                        'custom_logo_id' => $documentId,
                        'use_default_logo' => false,
                    ]);
            }

            Schema::table('boat_make', function (Blueprint $table) {
                $table->dropColumn('logo');
            });
        }

        DB::table('boat_make')
            ->whereNull('default_brand_image')
            ->update(['use_default_logo' => false]);

        Schema::table('boat_make', function (Blueprint $table) {
            $table->foreign('custom_logo_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boat_make', function (Blueprint $table) {
            $table->dropForeign(['custom_logo_id']);
        });

        Schema::table('boat_make', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('is_custom');
        });

        DB::table('boat_make')
            ->whereNotNull('custom_logo_id')
            ->get(['id', 'custom_logo_id'])
            ->each(function ($row) {
                DB::table('boat_make')
                    ->where('id', $row->id)
                    ->update(['logo' => (string) $row->custom_logo_id]);
            });

        Schema::table('boat_make', function (Blueprint $table) {
            $table->dropColumn(['use_default_logo', 'default_brand_image', 'custom_logo_id']);
        });
    }
};
