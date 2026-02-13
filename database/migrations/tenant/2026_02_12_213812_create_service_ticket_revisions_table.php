<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_ticket_revisions', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->string('display_name');

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */
            $table->foreignId('service_ticket_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Revision Details
            |--------------------------------------------------------------------------
            */

            // Snapshot of previous estimate
            $table->decimal('previous_estimated_total', 12, 2);

            // New proposed estimate
            $table->decimal('revised_estimated_total', 12, 2);

            // Percent increase at time of revision
            $table->decimal('percent_increase', 6, 2);

            // Optional breakdown snapshot (recommended)
            $table->decimal('previous_subtotal', 12, 2)->nullable();
            $table->decimal('previous_tax', 12, 2)->nullable();
            $table->decimal('revised_subtotal', 12, 2)->nullable();
            $table->decimal('revised_tax', 12, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Scope Snapshot (Important)
            |--------------------------------------------------------------------------
            */

            // Store snapshot of service items at revision time
            $table->json('items_snapshot')->nullable();

            // Optional notes explaining why revision occurred
            $table->text('revision_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Signature & Audit Trail
            |--------------------------------------------------------------------------
            */

            $table->string('signature_file')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip')->nullable();
            $table->string('signed_user_agent')->nullable();

            // Hash of revision data at signing time
            $table->string('signature_hash')->nullable();
            $table->unsignedTinyInteger('signature_method')->nullable()
                ->comment('1 = Digital, 2 = Paper, 3 = Verbal, 4 = Email approval');
            $table->string('paper_signature_file')->nullable();
            $table->foreignId('paper_signature_document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('approved')->default(false);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('service_ticket_id');
            $table->index(['service_ticket_id', 'approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_revisions');
    }
};
