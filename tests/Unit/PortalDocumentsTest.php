<?php

namespace Tests\Unit;

use App\Domain\Document\Support\PortalDocuments;
use PHPUnit\Framework\TestCase;

class PortalDocumentsTest extends TestCase
{
    public function test_portal_documents_class_is_loadable(): void
    {
        $this->assertTrue(class_exists(PortalDocuments::class));
    }
}
