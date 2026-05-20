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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('ticket_number')->unique()->nullable();
            $table->foreignId('user_id')->constrained();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->tinyInteger('category')->default(1);
            $table->text('message');
            $table->timestamp('date_submitted')->useCurrent();
            $table->boolean('escalated')->default(false);
            $table->tinyInteger('priority')->default(1);
            $table->boolean('completed')->default(false);
            $table->timestamp('time_completed')->nullable();
            $table->boolean('reopened')->default(false);
            $table->boolean('solved')->default(false);
            $table->string('agent')->nullable();
            $table->string('subject');
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('satisfaction')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained();
            $table->text('response');
            $table->boolean('internal')->default(false);
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_responses');
        Schema::dropIfExists('support_tickets');
    }
};
