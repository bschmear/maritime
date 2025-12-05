<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Core
            $table->string('display_name');
            $table->text('notes')->nullable();

            // Dates
            $table->dateTime('start_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Status & Priority (via enums or lookup tables)
            $table->unsignedTinyInteger('status_id')
                ->default(1);
            $table->unsignedTinyInteger('priority_id')
                ->default(2);

            // Assignment & ownership
            $table->unsignedBigInteger('assigned_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Boolean cached state
            $table->boolean('completed')->default(false);

            // Polymorphic relationship (automatically creates index)
            $table->nullableMorphs('relatable'); // relatable_type, relatable_id

            // Optional linking to calendar events
            $table->unsignedBigInteger('event_id')->nullable();

            // Smart scheduling features
            $table->dateTime('reminder_at')->nullable();   // notify user
            $table->dateTime('snoozed_until')->nullable(); // task snoozed

            // Optional categorization or task types
            $table->unsignedBigInteger('task_type_id')->nullable();

            // Recurrence rule (ex: "weekly", "RRULE:FREQ=WEEKLY;INTERVAL=1")
            $table->string('recurring_rule')->nullable();

            // Indexes (note: nullableMorphs already creates an index on relatable_type, relatable_id)
            $table->index(['status_id', 'priority_id']);
            $table->index(['assigned_id']);
            

            $table->timestamps();
            $table->softDeletes();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};