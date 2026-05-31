<?php

namespace Tests\Unit;

use App\Support\PostCoverImageStorage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PostCoverImageStorageTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (glob(public_path('posts/*.jpg')) ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_store_saves_under_public_posts_and_returns_path(): void
    {
        $file = UploadedFile::fake()->image('hero.jpg', 1200, 630);

        $path = PostCoverImageStorage::store($file);

        $this->assertStringStartsWith('/posts/', $path);
        $fullPath = public_path(ltrim($path, '/'));
        $this->assertFileExists($fullPath);

        [$width] = getimagesize($fullPath);
        $this->assertLessThanOrEqual(PostCoverImageStorage::MAX_WIDTH, $width);
    }

    public function test_store_does_not_upscale_images_narrower_than_max_width(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 400, 300);

        $path = PostCoverImageStorage::store($file);

        [$width] = getimagesize(public_path(ltrim($path, '/')));

        $this->assertSame(400, $width);
    }

    public function test_delete_removes_local_post_image(): void
    {
        $file = UploadedFile::fake()->image('hero.jpg');
        $path = PostCoverImageStorage::store($file);

        PostCoverImageStorage::deleteIfStoredLocally($path);

        $this->assertFileDoesNotExist(public_path(ltrim($path, '/')));
    }

    public function test_delete_ignores_external_urls(): void
    {
        PostCoverImageStorage::deleteIfStoredLocally('https://example.com/image.jpg');

        $this->assertTrue(true);
    }
}
