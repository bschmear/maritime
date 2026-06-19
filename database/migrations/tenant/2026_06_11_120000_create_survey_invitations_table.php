<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('record_type', 32);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_mobile')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->boolean('send_email')->default(true);
            $table->boolean('send_sms')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['record_type', 'record_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_invitations');
    }
};
