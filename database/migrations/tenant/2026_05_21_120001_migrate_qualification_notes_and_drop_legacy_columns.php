<?php

use App\Domain\Qualification\Models\Qualification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notes') || ! Schema::hasTable('qualifications')) {
            return;
        }

        DB::table('qualifications')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                $now = now();
                foreach ($rows as $row) {
                    $parts = [];
                    if (! empty($row->customer_notes)) {
                        $parts[] = '<p><strong>Customer notes</strong></p><p>'.e((string) $row->customer_notes).'</p>';
                    }
                    if (! empty($row->internal_notes)) {
                        $parts[] = '<p><strong>Internal notes</strong></p><p>'.e((string) $row->internal_notes).'</p>';
                    }
                    $body = trim(implode('', $parts));
                    if ($body === '') {
                        continue;
                    }

                    DB::table('notes')->insert([
                        'user_id' => $row->createdby_id,
                        'notifiable_type' => Qualification::class,
                        'notifiable_id' => $row->id,
                        'body' => $body,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

        Schema::table('qualifications', function (Blueprint $table) {
            if (Schema::hasColumn('qualifications', 'customer_notes')) {
                $table->dropColumn('customer_notes');
            }
            if (Schema::hasColumn('qualifications', 'internal_notes')) {
                $table->dropColumn('internal_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'customer_notes')) {
                $table->text('customer_notes')->nullable();
            }
            if (! Schema::hasColumn('qualifications', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
        });
    }
};
