<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_notification_preferences')) {
            return;
        }

        if (! Schema::hasColumn('sms_notification_preferences', 'notify_contract')) {
            Schema::table('sms_notification_preferences', function (Blueprint $table) {
                $table->boolean('notify_contract')->default(false)->after('notify_delivery');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_notification_preferences')) {
            return;
        }

        if (Schema::hasColumn('sms_notification_preferences', 'notify_contract')) {
            Schema::table('sms_notification_preferences', function (Blueprint $table) {
                $table->dropColumn('notify_contract');
            });
        }
    }
};
