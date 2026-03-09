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
        Schema::create('opportunities', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // $table->foreignId('lead_id')
            //     ->nullable()
            //     ->constrained()
            //     ->nullOnDelete();

            $table->foreignId('qualification_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete(); // salesperson

            $table->foreignId('createdby_id')
                ->nullable()
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // $table->string('display_name')->nullable();

            $table->unsignedTinyInteger('stage')->default(1);
            $table->unsignedTinyInteger('status')->default(1);

            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();

            $table->date('expected_close_date')->nullable();

            $table->boolean('needs_engine')->default(false);
            $table->boolean('needs_trailer')->default(false);

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
