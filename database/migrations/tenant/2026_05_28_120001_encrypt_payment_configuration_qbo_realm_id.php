<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('payments_configurations')->select(['id', 'qbo_realm_id'])->cursor() as $row) {
            $stored = $row->qbo_realm_id;
            if ($stored === null || $stored === '') {
                continue;
            }

            $plain = (string) $stored;
            if ($this->isEncrypted($plain)) {
                continue;
            }

            DB::table('payments_configurations')->where('id', $row->id)->update([
                'qbo_realm_id' => Crypt::encryptString($plain),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('payments_configurations')->select(['id', 'qbo_realm_id'])->cursor() as $row) {
            $stored = $row->qbo_realm_id;
            if ($stored === null || $stored === '') {
                continue;
            }

            $plain = (string) $stored;
            if (! $this->isEncrypted($plain)) {
                continue;
            }

            try {
                DB::table('payments_configurations')->where('id', $row->id)->update([
                    'qbo_realm_id' => Crypt::decryptString($plain),
                ]);
            } catch (DecryptException) {
                continue;
            }
        }
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
