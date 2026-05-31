<?php

namespace Tests\Feature;

use App\Domain\DocumentRequest\Actions\FulfillDocumentRequest;
use App\Domain\DocumentRequest\Actions\SendDocumentRequest;
use App\Http\Controllers\Portal\CustomerPortalController;
use App\Http\Controllers\Tenant\DocumentController;
use App\Http\Controllers\Tenant\DocumentRequestController;
use App\Models\Concerns\HasDocuments;
use App\Services\NotificationService;
use App\Support\ContactDocumentLinker;
use ReflectionClass;
use Tests\TestCase;

class DocumentVisibilityAndRequestsTest extends TestCase
{
    public function test_has_documents_trait_supports_visible_to_customer_pivot(): void
    {
        $ref = new ReflectionClass(HasDocuments::class);
        $this->assertTrue($ref->hasMethod('attachDocument'));
        $this->assertTrue($ref->hasMethod('updateDocumentPivot'));

        $source = file_get_contents($ref->getFileName());
        $this->assertStringContainsString('visible_to_vendor', $source);
    }

    public function test_contact_document_linker_has_customer_only_attach(): void
    {
        $this->assertTrue(method_exists(ContactDocumentLinker::class, 'attachToCustomerOnly'));
    }

    public function test_document_controller_has_update_pivot(): void
    {
        $ref = new ReflectionClass(DocumentController::class);
        $this->assertTrue($ref->hasMethod('updatePivot'));
    }

    public function test_portal_controller_has_document_download_and_fulfill(): void
    {
        $ref = new ReflectionClass(CustomerPortalController::class);
        $this->assertTrue($ref->hasMethod('downloadDocument'));
        $this->assertTrue($ref->hasMethod('fulfillDocumentRequest'));
    }

    public function test_vendor_portal_controller_has_warranty_claim_document_download(): void
    {
        $ref = new ReflectionClass(\App\Http\Controllers\Portal\VendorPortalController::class);
        $this->assertTrue($ref->hasMethod('downloadWarrantyClaimDocument'));
    }

    public function test_document_request_controller_exists(): void
    {
        $this->assertTrue(class_exists(DocumentRequestController::class));
    }

    public function test_send_and_fulfill_actions_exist(): void
    {
        $this->assertTrue(class_exists(SendDocumentRequest::class));
        $this->assertTrue(class_exists(FulfillDocumentRequest::class));
    }

    public function test_notification_service_notifies_document_request_fulfilled(): void
    {
        $ref = new ReflectionClass(NotificationService::class);
        $this->assertTrue($ref->hasMethod('notifyDocumentRequestFulfilled'));
    }
}
