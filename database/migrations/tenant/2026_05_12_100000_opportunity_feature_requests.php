<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_opportunity', 'feature_request_completed_at')) {
                $table->timestamp('feature_request_completed_at')->nullable()->after('notes');
            }
        });

        Schema::create('opportunity_feature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('asset_opportunity_id')->constrained('asset_opportunity')->cascadeOnDelete();
            $table->boolean('include_addons')->default(false);

            $table->string('asset_display_name')->nullable();
            $table->string('variant_label')->nullable();

            $table->json('asset_option_selections');
            $table->json('addon_selections')->nullable();

            $table->string('signer_name');
            $table->string('signer_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');

            $table->timestamps();

            $table->index(['opportunity_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_feature_requests');

        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('asset_opportunity', 'feature_request_completed_at')) {
                $table->dropColumn('feature_request_completed_at');
            }
        });
    }
};
