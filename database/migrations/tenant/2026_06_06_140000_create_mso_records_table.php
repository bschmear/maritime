<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mso_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_unit_id')->constrained('asset_units')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('transaction_line_item_id')->nullable()->constrained('transaction_line_items')->nullOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('output_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->json('details')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['asset_unit_id', 'created_at']);
            $table->index('transaction_id');
            $table->unique('transaction_line_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mso_records');
    }
};
