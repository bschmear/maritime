<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_setup_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('feature_area', 32);
            $table->string('icon', 64)->nullable();
            $table->string('route_name');
            $table->json('route_params')->nullable();
            $table->string('permission', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('account_setup_step_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_setup_step_id')->constrained('account_setup_steps')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('account_setup_step_id');
        });

        Schema::table('account_settings', function (Blueprint $table) {
            $table->boolean('account_setup_complete')->default(false)->after('account_overviewed');
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn('account_setup_complete');
        });

        Schema::dropIfExists('account_setup_step_progress');
        Schema::dropIfExists('account_setup_steps');
    }
};
