<?php

namespace Tests\Unit;

use App\Support\SignatureStorage;
use Tests\TestCase;

class SignatureStorageTest extends TestCase
{
    public function test_directory_is_under_public_prefix(): void
    {
        $this->assertSame('public/signatures', SignatureStorage::DIRECTORY);
    }

    public function test_url_uses_cdn_for_public_paths(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com']);

        $url = SignatureStorage::url('public/signatures/abc-signature.png');

        $this->assertSame('https://cdn.example.com/public/signatures/abc-signature.png', $url);
    }

    public function test_url_returns_null_for_empty_path(): void
    {
        $this->assertNull(SignatureStorage::url(null));
        $this->assertNull(SignatureStorage::url(''));
    }

    public function test_store_drawn_image_returns_null_for_invalid_payload(): void
    {
        $this->assertNull(SignatureStorage::storeDrawnImage('not-base64', 'uuid-1'));
    }
}
