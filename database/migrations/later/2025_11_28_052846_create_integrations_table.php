<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('integration_type')->comment('Type of integration (e.g., google_calendar, outlook)');
            $table->string('external_id')->comment('The ID from the third party service');
            $table->string('name')->nullable()->comment('Display name for the integration');
            $table->boolean('active')->default(true);
            $table->string('sync_token')->nullable()->comment('Token for incremental sync');
            $table->text('access_token')->nullable()->comment('OAuth access token (should be encrypted)');
            $table->text('refresh_token')->nullable()->comment('OAuth refresh token (should be encrypted)');
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedTinyInteger('sync_status')->default(1);
            $table->text('sync_error_message')->nullable();
            $table->integer('sync_retry_count')->default(0);
            $table->json('settings')->nullable()->comment('Integration-specific settings and preferences');
            $table->json('metadata')->nullable()->comment('Additional data from the third party service');
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'integration_type', 'external_id'], 'unique_user_integration');
            $table->unique(['team_id', 'integration_type', 'external_id'], 'unique_team_integration');
            $table->index(['user_id', 'integration_type']);
            $table->index(['team_id', 'integration_type']);
            $table->index(['active', 'sync_status']);
            $table->index('last_synced_at');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
