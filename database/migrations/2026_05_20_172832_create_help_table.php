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

        Schema::create('help_categories', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('help_categories')
                ->nullOnDelete();

            $table->integer('sort_order')->default(0);

            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('help_categories')
                ->nullOnDelete();

            $table->string('title');

            $table->string('slug')->unique();

            $table->longText('body');

            $table->text('excerpt')->nullable();
            $table->text('video_url')->nullable(); // YouTube or Vimeo URL

            $table->string('article_type')->default('guide');

            $table->integer('sort_order')->default(0);

            $table->boolean('featured')->default(false);

            $table->boolean('active')->default(true);

            $table->timestamp('published_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('help_categories');
    }
};
