<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\InventoryCatalogImageStorage;
use Tests\TestCase;

class InventoryCatalogImageStorageTest extends TestCase
{
    public function test_s3_key_from_cdn_url(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com']);

        $key = InventoryCatalogImageStorage::s3KeyFromStoredPath(
            'https://cdn.example.com/public/inventory/boat_makes/logo.webp'
        );

        $this->assertSame('public/inventory/boat_makes/logo.webp', $key);
    }

    public function test_s3_key_from_storage_key(): void
    {
        $key = InventoryCatalogImageStorage::s3KeyFromStoredPath('public/inventory/boat_makes/logo.webp');

        $this->assertSame('public/inventory/boat_makes/logo.webp', $key);
    }

    public function test_cdn_url_builds_from_key(): void
    {
        config(['filesystems.disks.s3.cdn_url' => 'https://cdn.example.com/']);

        $url = InventoryCatalogImageStorage::cdnUrl('public/inventory/boat_makes/logo.webp');

        $this->assertSame('https://cdn.example.com/public/inventory/boat_makes/logo.webp', $url);
    }
}
