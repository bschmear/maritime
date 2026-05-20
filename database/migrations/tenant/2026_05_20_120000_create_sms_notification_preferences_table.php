<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_settings_id')
                ->constrained('account_settings')
                ->cascadeOnDelete();

            $table->boolean('notify_estimate')->default(false);
            $table->boolean('notify_invoice')->default(false);
            $table->boolean('notify_delivery')->default(false);

            $table->timestamps();

            $table->unique('account_settings_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notification_preferences');
    }
};
