<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('external_id_hash', 64)->nullable()->after('external_id');
        });

        foreach (DB::table('integrations')->select(['id', 'external_id'])->cursor() as $row) {
            $plain = (string) $row->external_id;
            if ($plain === '') {
                continue;
            }

            if ($this->isEncrypted($plain)) {
                try {
                    $plain = Crypt::decryptString($plain);
                } catch (DecryptException) {
                    continue;
                }
            }

            DB::table('integrations')->where('id', $row->id)->update([
                'external_id' => Crypt::encryptString($plain),
                'external_id_hash' => hash('sha256', $plain),
            ]);
        }

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropUnique('unique_user_integration');
            $table->text('external_id')->change();
            $table->unique(['user_id', 'integration_type', 'external_id_hash'], 'unique_user_integration');
        });
    }

    public function down(): void
    {
        foreach (DB::table('integrations')->select(['id', 'external_id'])->cursor() as $row) {
            $stored = (string) $row->external_id;
            if ($stored === '' || ! $this->isEncrypted($stored)) {
                continue;
            }

            try {
                $plain = Crypt::decryptString($stored);
            } catch (DecryptException) {
                continue;
            }

            DB::table('integrations')->where('id', $row->id)->update([
                'external_id' => $plain,
                'external_id_hash' => null,
            ]);
        }

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropUnique('unique_user_integration');
            $table->string('external_id')->change();
            $table->unique(['user_id', 'integration_type', 'external_id'], 'unique_user_integration');
            $table->dropColumn('external_id_hash');
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
