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
        $this->assertFileExists(public_path(ltrim($path, '/')));
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
