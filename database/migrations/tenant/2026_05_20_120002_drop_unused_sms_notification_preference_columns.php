<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limits per-type SMS columns to estimate, invoice, and delivery (see App\Enums\SMS).
 */
return new class extends Migration
{
    private const DROP_COLUMNS = [
        'notify_serviceticket',
        'notify_survey',
        'notify_contract',
        'notify_warrantyclaim',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('sms_notification_preferences')) {
            return;
        }

        $toDrop = array_values(array_filter(
            self::DROP_COLUMNS,
            fn (string $col) => Schema::hasColumn('sms_notification_preferences', $col),
        ));

        if ($toDrop !== []) {
            Schema::table('sms_notification_preferences', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_notification_preferences')) {
            return;
        }

        Schema::table('sms_notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_notification_preferences', 'notify_serviceticket')) {
                $table->boolean('notify_serviceticket')->default(false);
            }
            if (! Schema::hasColumn('sms_notification_preferences', 'notify_survey')) {
                $table->boolean('notify_survey')->default(false);
            }
            if (! Schema::hasColumn('sms_notification_preferences', 'notify_contract')) {
                $table->boolean('notify_contract')->default(false);
            }
            if (! Schema::hasColumn('sms_notification_preferences', 'notify_warrantyclaim')) {
                $table->boolean('notify_warrantyclaim')->default(false);
            }
        });
    }
};
