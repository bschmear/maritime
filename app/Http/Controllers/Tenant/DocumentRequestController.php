<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\DocumentRequest\Actions\SendDocumentRequest;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Lead\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function index(Contact $contact): JsonResponse
    {
        $requests = DocumentRequest::query()
            ->where('contact_id', $contact->id)
            ->with(['requestedBy:id,display_name', 'fulfilledDocument:id,display_name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['document_requests' => $requests]);
    }

    public function store(Request $request, Contact $contact, SendDocumentRequest $sendDocumentRequest): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
        ]);

        $source = $this->resolveSource(
            $validated['source_type'] ?? null,
            isset($validated['source_id']) ? (int) $validated['source_id'] : null,
        );

        try {
            $result = $sendDocumentRequest(
                $contact,
                $validated['title'],
                $validated['description'] ?? null,
                $source,
            );

            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function cancel(DocumentRequest $documentRequest): JsonResponse
    {
        if ($documentRequest->status !== DocumentRequestStatus::Pending) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be cancelled.',
            ], 422);
        }

        $documentRequest->update(['status' => DocumentRequestStatus::Cancelled]);

        return response()->json(['success' => true]);
    }

    private function resolveSource(?string $type, ?int $id): Contact|Customer|Lead|null
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'Contact' => Contact::query()->find($id),
            'Customer' => Customer::query()->find($id),
            'Lead' => Lead::query()->find($id),
            default => null,
        };
    }
}
