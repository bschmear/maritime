<?php

use App\Enums\Entity\Source;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        if (! Schema::hasColumn('contacts', 'source_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->unsignedTinyInteger('source_id')->nullable()->after('preferred_contact_time');
            });
        }

        if (Schema::hasColumn('contacts', 'source')) {
            $contacts = DB::table('contacts')
                ->select(['id', 'source'])
                ->whereNotNull('source')
                ->where('source', '!=', '')
                ->get();

            foreach ($contacts as $row) {
                $enum = Source::tryFromStored($row->source);
                if ($enum !== null) {
                    DB::table('contacts')
                        ->where('id', $row->id)
                        ->update(['source_id' => $enum->id()]);
                }
            }
        }

        if (Schema::hasTable('lead_profiles') && Schema::hasColumn('contacts', 'source_id')) {
            $leadSources = DB::table('lead_profiles')
                ->select(['contact_id', 'source_id'])
                ->whereNotNull('contact_id')
                ->whereNotNull('source_id')
                ->orderBy('id')
                ->get()
                ->groupBy('contact_id');

            foreach ($leadSources as $contactId => $rows) {
                $sourceId = $rows->first()->source_id ?? null;
                if ($sourceId === null) {
                    continue;
                }

                DB::table('contacts')
                    ->where('id', $contactId)
                    ->whereNull('source_id')
                    ->update(['source_id' => $sourceId]);
            }
        }

        if (Schema::hasColumn('contacts', 'source')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        if (! Schema::hasColumn('contacts', 'source')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('source')->nullable()->after('preferred_contact_time');
            });
        }

        if (Schema::hasColumn('contacts', 'source_id')) {
            $contacts = DB::table('contacts')
                ->select(['id', 'source_id'])
                ->whereNotNull('source_id')
                ->get();

            foreach ($contacts as $row) {
                $enum = Source::tryFromStored($row->source_id);
                if ($enum !== null) {
                    DB::table('contacts')
                        ->where('id', $row->id)
                        ->update(['source' => $enum->value]);
                }
            }

            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('source_id');
            });
        }
    }
};
