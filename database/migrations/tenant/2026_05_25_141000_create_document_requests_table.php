<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('fulfilled_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'status']);
            $table->index(['customer_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
