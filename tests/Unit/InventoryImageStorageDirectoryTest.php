<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\InventoryImage\Support\InventoryImageStorageDirectory;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use PHPUnit\Framework\TestCase;

class InventoryImageStorageDirectoryTest extends TestCase
{
    public function test_service_ticket_uses_servicetickets_directory(): void
    {
        $this->assertSame('servicetickets', InventoryImageStorageDirectory::forType(ServiceTicket::class));
    }

    public function test_warranty_claim_uses_warrantyclaim_directory(): void
    {
        $this->assertSame('warrantyclaim', InventoryImageStorageDirectory::forType(WarrantyClaim::class));
    }

    public function test_other_models_use_inventory_images_path(): void
    {
        $this->assertSame(
            InventoryImageStorageDirectory::DEFAULT,
            InventoryImageStorageDirectory::forType(Asset::class)
        );
    }
}
