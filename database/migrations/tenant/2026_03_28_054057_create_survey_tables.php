<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('public_description')->nullable();
            $table->enum('type', ['feedback', 'lead', 'followup', 'custom'])->default('custom');
            $table->enum('visibility', ['private', 'public'])->default('private');
            $table->boolean('status')->default(true);
            $table->unsignedTinyInteger('layout')->default(1)->comment('Used to determine which layout template to render');
            $table->string('delivery_method')->default('email');
            $table->string('automation_trigger')->default('manual');
            $table->json('automation_config')->nullable();
            $table->text('thank_you_message')->nullable();
            $table->string('redirect_url')->nullable();
            $table->json('privacy_settings')->nullable();
            $table->string('color_scheme', 20)->default('default');
            $table->string('custom_color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('label');
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0);
            $table->json('options')->nullable();
            $table->json('config')->nullable();
            $table->json('conditional_logic')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_followup_email_id')
                ->nullable()
                // ->constrained('emails')
                ->nullOnDelete();
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->nullableMorphs('sourceable');
            $table->nullableMorphs('owner');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('converted')->default(false);
            $table->boolean('tasks_applied')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_response_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')
                ->constrained('survey_responses')
                ->cascadeOnDelete();
            $table->foreignId('survey_question_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('email_subject');
            $table->text('email_body');
            $table->integer('wait_time')->default(24);
            $table->boolean('send_after_submission')->default(true);
            $table->timestamps();
        });

        Schema::create('survey_followup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')
                ->constrained('survey_responses')
                ->cascadeOnDelete();
            $table->foreignId('followup_id')
                ->constrained('survey_followups')
                ->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_followup_logs');
        Schema::dropIfExists('survey_followups');
        Schema::dropIfExists('survey_response_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
