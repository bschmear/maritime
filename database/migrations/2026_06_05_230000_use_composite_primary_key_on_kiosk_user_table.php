<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kiosk_user') || ! Schema::hasColumn('kiosk_user', 'id')) {
            return;
        }

        $uniqueConstraint = DB::selectOne("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'kiosk_user'::regclass
              AND contype = 'u'
            LIMIT 1
        ");

        if ($uniqueConstraint !== null) {
            DB::statement(sprintf(
                'ALTER TABLE kiosk_user DROP CONSTRAINT %s',
                $this->quoteIdentifier($uniqueConstraint->conname)
            ));
        }

        Schema::table('kiosk_user', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropColumn('id');
            $table->primary(['user_id', 'kiosk_role_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kiosk_user') || Schema::hasColumn('kiosk_user', 'id')) {
            return;
        }

        Schema::table('kiosk_user', function (Blueprint $table) {
            $table->dropPrimary(['user_id', 'kiosk_role_id']);
        });

        Schema::table('kiosk_user', function (Blueprint $table) {
            $table->id()->first();
            $table->unique(['user_id', 'kiosk_role_id']);
        });
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
