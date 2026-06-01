<?php

namespace Tests\Unit;

use App\Support\PostCoverImageStorage;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PostCoverImageStorageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_cdn_url_builds_from_s3_key(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com']);

        $url = PostCoverImageStorage::cdnUrl('public/posts/abc.jpg');

        $this->assertSame('https://cdn.example.com/public/posts/abc.jpg', $url);
    }

    public function test_s3_key_from_stored_cloudfront_url(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com']);

        $key = PostCoverImageStorage::s3KeyFromStoredPath(
            'https://cdn.example.com/public/posts/abc.jpg'
        );

        $this->assertSame('public/posts/abc.jpg', $key);
    }

    public function test_is_stored_public_path_accepts_cloudfront_url(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com']);

        $this->assertTrue(PostCoverImageStorage::isStoredPublicPath(
            'https://cdn.example.com/public/posts/legacy-uuid.jpg'
        ));
    }

    public function test_is_stored_public_path_accepts_legacy_local_paths(): void
    {
        $this->assertTrue(PostCoverImageStorage::isStoredPublicPath('/posts/legacy-uuid.jpg'));
        $this->assertTrue(PostCoverImageStorage::isStoredPublicPath('/post-covers/legacy-uuid.jpg'));
    }

    public function test_store_uploads_to_s3_and_returns_cloudfront_url(): void
    {
        config([
            'filesystems.disks.s3.cdn_url' => 'https://cdn.example.com',
            'filesystems.disks.s3.bucket' => 'test-bucket',
        ]);

        $s3Client = Mockery::mock(S3Client::class);
        $s3Client->shouldReceive('putObject')
            ->once()
            ->withArgs(function (array $args): bool {
                return $args['Bucket'] === 'test-bucket'
                    && str_starts_with($args['Key'], 'public/posts/')
                    && isset($args['SourceFile'])
                    && $args['CacheControl'] === 'public, max-age=604800';
            });

        $disk = Mockery::mock();
        $disk->shouldReceive('getClient')->andReturn($s3Client);
        $disk->shouldReceive('getConfig')->andReturn(['bucket' => 'test-bucket']);
        $disk->shouldReceive('exists')->andReturn(false);

        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $file = UploadedFile::fake()->image('hero.jpg', 1200, 630);

        $url = PostCoverImageStorage::store($file);

        $this->assertStringStartsWith('https://cdn.example.com/public/posts/', $url);
        $this->assertStringEndsWith('.jpg', $url);
    }

    public function test_delete_removes_object_from_s3(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com']);

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')
            ->once()
            ->with('public/posts/abc.jpg')
            ->andReturn(true);
        $disk->shouldReceive('delete')
            ->once()
            ->with('public/posts/abc.jpg');

        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        PostCoverImageStorage::deleteIfStored('https://cdn.example.com/public/posts/abc.jpg');

        $this->addToAssertionCount(1);
    }

    public function test_delete_ignores_unrelated_urls(): void
    {
        Storage::shouldReceive('disk')->never();

        PostCoverImageStorage::deleteIfStored('https://example.com/image.jpg');

        $this->assertTrue(true);
    }
}
