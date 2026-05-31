<?php

namespace Tests\Unit;

use App\Domain\Document\Support\PortalDocuments;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use ReflectionClass;
use Tests\TestCase;

class PortalDocumentsVendorWarrantyClaimTest extends TestCase
{
    public function test_portal_documents_exposes_vendor_warranty_claim_helpers(): void
    {
        $ref = new ReflectionClass(PortalDocuments::class);

        $this->assertTrue($ref->hasMethod('vendorVisibleOnWarrantyClaim'));
        $this->assertTrue($ref->hasMethod('vendorCanDownloadFromWarrantyClaim'));
        $this->assertTrue($ref->hasMethod('mapForVendorWarrantyClaim'));
    }

    public function test_has_documents_trait_includes_visible_to_vendor_pivot(): void
    {
        $claim = new WarrantyClaim;
        $relation = $claim->documents();
        $pivotColumns = $relation->getPivotColumns();

        $this->assertContains('visible_to_vendor', $pivotColumns);
        $this->assertContains('visible_to_customer', $pivotColumns);
    }
}
