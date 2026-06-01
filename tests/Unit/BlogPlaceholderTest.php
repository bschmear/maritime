<?php

namespace Tests\Unit;

use App\Support\BlogPlaceholder;
use Tests\TestCase;

class BlogPlaceholderTest extends TestCase
{
    public function test_cover_image_returns_existing_when_present(): void
    {
        config(['app.blog_placeholder_image' => 'https://cdn.example.com/placeholder.jpg']);

        $this->assertSame(
            'https://cdn.example.com/existing.jpg',
            BlogPlaceholder::coverImage('https://cdn.example.com/existing.jpg')
        );
    }

    public function test_cover_image_falls_back_to_configured_placeholder(): void
    {
        config(['app.blog_placeholder_image' => 'https://cdn.example.com/placeholder.jpg']);

        $this->assertSame(
            'https://cdn.example.com/placeholder.jpg',
            BlogPlaceholder::coverImage(null)
        );
    }

    public function test_cover_image_returns_null_when_no_cover_and_no_placeholder(): void
    {
        config(['app.blog_placeholder_image' => null]);

        $this->assertNull(BlogPlaceholder::coverImage(''));
    }
}
