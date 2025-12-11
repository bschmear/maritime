<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->string('display_name')->nullable();
            $table->text('description')->nullable();

            $table->string('file')->nullable();
            $table->string('file_extension', 10)->nullable();
            $table->integer('file_size')->default(0);

            $table->longText('extracted_text')->nullable();
            $table->longText('ai_summary')->nullable();
            $table->json('key_points')->nullable();

            $table->enum('ai_status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');

            $table->timestamp('ai_processed_at')->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->unsignedBigInteger('assigned_id')->nullable();

            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_id')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
