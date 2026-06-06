<?php

namespace Tests\Unit;

use App\Domain\Document\Support\TenantDocumentAccess;
use PHPUnit\Framework\TestCase;

class TenantDocumentAccessTest extends TestCase
{
    public function test_private_public_storage_path_belongs_to_tenant(): void
    {
        $path = 'private/762332/documents/example.pdf';

        $this->assertTrue(TenantDocumentAccess::pathBelongsToTenant($path, '762332'));
        $this->assertFalse(TenantDocumentAccess::pathBelongsToTenant($path, '999999'));
    }

    public function test_legacy_documents_path_still_belongs_to_tenant(): void
    {
        $path = 'documents/762332/example.pdf';

        $this->assertTrue(TenantDocumentAccess::pathBelongsToTenant($path, '762332'));
    }

    public function test_rejects_path_traversal(): void
    {
        $this->assertFalse(TenantDocumentAccess::pathBelongsToTenant('../secrets/file.pdf', '762332'));
    }
}
