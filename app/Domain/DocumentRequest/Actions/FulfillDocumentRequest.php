<?php

declare(strict_types=1);

namespace App\Domain\DocumentRequest\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Document\Actions\CreateDocument;
use App\Domain\Document\Models\Document;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Services\NotificationService;
use App\Support\ContactDocumentLinker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class FulfillDocumentRequest
{
    public function __construct(
        private readonly CreateDocument $createDocument,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @return array{success: bool, document?: Document, message?: string}
     */
    public function __invoke(DocumentRequest $documentRequest, Contact $contact, UploadedFile $file): array
    {
        if ((int) $documentRequest->contact_id !== (int) $contact->id) {
            return ['success' => false, 'message' => 'You are not authorized to fulfill this request.'];
        }

        if (! $documentRequest->isPending()) {
            return ['success' => false, 'message' => 'This document request is no longer open.'];
        }

        $customer = $documentRequest->customerProfile;
        if (! $customer) {
            return ['success' => false, 'message' => 'Customer profile not found.'];
        }

        return DB::transaction(function () use ($documentRequest, $customer, $file) {
            $result = ($this->createDocument)([
                'file' => $file,
                'display_name' => $documentRequest->title,
                'description' => $documentRequest->description,
                'created_by_id' => $documentRequest->requested_by_user_id,
                'assigned_id' => $documentRequest->requested_by_user_id,
            ]);

            if (! ($result['success'] ?? false) || ! isset($result['record'])) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to upload document.',
                ];
            }

            $document = $result['record'];
            ContactDocumentLinker::attachToCustomerOnly($document, $customer, true);

            $documentRequest->update([
                'status' => DocumentRequestStatus::Fulfilled,
                'fulfilled_document_id' => $document->id,
                'fulfilled_at' => now(),
            ]);

            $documentRequest->load('contact');
            $this->notificationService->notifyDocumentRequestFulfilled($documentRequest->fresh(['contact']), $document);

            return [
                'success' => true,
                'document' => $document,
            ];
        });
    }
}
