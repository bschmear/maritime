<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assigned_to_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type');
            $table->string('title');
            $table->text('message')->nullable();

            $table->string('route');
            $table->json('route_params')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('assigned_to_user_id');
            $table->index('read_at');
        });
        Schema::table('account_settings', function (Blueprint $table) {
            $table->foreignId('service_ticket_signed_notify_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index('service_ticket_signed_notify_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_ticket_signed_notify_user_id');
        });
    }
};
