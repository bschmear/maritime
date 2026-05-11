<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_request_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            /** opportunity | estimate | standalone — extend as new sources are added */
            $table->string('source', 32)->default('opportunity');

            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('asset_opportunity_id')->nullable()->constrained('asset_opportunity')->cascadeOnDelete();

            $table->foreignId('estimate_id')->nullable()->constrained('estimates')->cascadeOnDelete();
            $table->unsignedBigInteger('estimate_line_item_id')->nullable();

            $table->boolean('include_addons')->default(false);
            /** Catalog `addons.id` values offered on this invite */
            $table->json('addon_catalog_ids')->nullable();
            /** Future: standalone payload (asset snapshot, etc.) */
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['source', 'opportunity_id']);
            $table->index(['source', 'estimate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_request_invites');
    }
};
