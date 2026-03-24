<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->morphs('communicable');

            $table->unsignedTinyInteger('communication_type_id')->index();
            $table->enum('direction', ['inbound', 'outbound'])->nullable();
            $table->string('subject')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('needs_follow_up')->default(false);
            $table->boolean('is_private')->default(false);
            $table->unsignedTinyInteger('status_id')->default(1)->index();
            $table->unsignedTinyInteger('channel_id')->nullable()->index();
            $table->unsignedTinyInteger('priority_id')->default(2)->index();
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('outcome_id')->nullable()->index();
            $table->timestamp('next_action_at')->nullable();
            $table->unsignedTinyInteger('next_action_type_id')->nullable()->index();
            $table->string('calendar_id')->nullable();
            $table->string('event_id')->nullable();

            $table->timestamp('date_contacted')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
