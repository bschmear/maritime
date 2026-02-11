<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_settings', function (Blueprint $table) {
            $table->id();

            /**
             * --- Localization ---
             */
            $table->string('timezone')
                ->default('UTC');

            /**
             * --- Branding ---
             */
            $table->string('logo_file')->nullable();
            $table->string('logo_file_extension', 10)->nullable();
            $table->integer('logo_file_size')->default(0);
            $table->string('brand_color')->nullable();

            /**
             * --- Formatting Preferences ---
             */
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i');
            $table->string('currency')->default('USD');

            /**
             * --- Operational Defaults ---
             */
            $table->boolean('week_starts_on_monday')->default(false);
            $table->boolean('auto_assign_work_orders')->default(false);

            /**
             * --- Extensibility ---
             * Use sparingly, but extremely useful for feature flags & preferences
             */
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('timezone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_settings');
    }
};
