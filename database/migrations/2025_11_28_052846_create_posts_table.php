<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('slug')->unique();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('short_description')->nullable();
            $table->json('faqs')->nullable();
            $table->string('cover_image')->default('');
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('category_id');
            $table->index('published');
            $table->index(['published', 'published_at']); // For listing published posts by date
            $table->index('created_at');
            $table->index('featured');

            // PostgreSQL doesn't support fullText indexes
            // $table->fullText(['title', 'body', 'short_description'], 'posts_search');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
