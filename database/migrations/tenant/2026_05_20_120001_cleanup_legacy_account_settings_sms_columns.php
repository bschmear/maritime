<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes per-type SMS booleans that previously lived on account_settings
 * (before sms_notification_preferences). Adds sandbox_mode if an older
 * migration ran without it.
 */
return new class extends Migration
{
    private const LEGACY_SMS_NOTIFY_COLUMNS = [
        'sms_notify_delivery_en_route',
        'sms_notify_invoice_sent',
        'sms_notify_invoice_paid',
        'sms_notify_quote_ready',
        'sms_notify_appointment_reminder',
    ];

    public function up(): void
    {
        $toDrop = array_values(array_filter(
            self::LEGACY_SMS_NOTIFY_COLUMNS,
            fn (string $col) => Schema::hasColumn('account_settings', $col),
        ));

        if ($toDrop !== []) {
            Schema::table('account_settings', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        if (! Schema::hasColumn('account_settings', 'sandbox_mode')) {
            Schema::table('account_settings', function (Blueprint $table) {
                if (Schema::hasColumn('account_settings', 'sms_enabled')) {
                    $table->boolean('sandbox_mode')->default(false)->after('sms_enabled');
                } else {
                    $table->boolean('sandbox_mode')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('account_settings', 'sms_notify_delivery_en_route')) {
            Schema::table('account_settings', function (Blueprint $table) {
                $table->boolean('sms_notify_delivery_en_route')->default(false);
                $table->boolean('sms_notify_invoice_sent')->default(false);
                $table->boolean('sms_notify_invoice_paid')->default(false);
                $table->boolean('sms_notify_quote_ready')->default(false);
                $table->boolean('sms_notify_appointment_reminder')->default(false);
            });
        }
    }
};
