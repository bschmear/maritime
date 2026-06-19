<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_routes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('address')->unique();
            $table->string('action')->default('create_lead');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('address');
        });

        Schema::create('ai_email_ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('email_route_id')->nullable()->constrained('email_routes')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('from_email')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->jsonb('raw_payload');
            $table->jsonb('parsed_data')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_email_ingestions');
        Schema::dropIfExists('email_routes');
    }
};
