<?php

namespace App\Domain\InventoryImage\Actions;

use App\Actions\PublicStorage;
use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\InventoryImage\Models\InventoryImage as RecordModel;
use App\Domain\InventoryImage\Support\InventoryImageStorageDirectory;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateInventoryImage
{
    protected $publicStorage;

    public function __construct()
    {
        $this->publicStorage = new PublicStorage;
    }

    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'imageable_type' => 'required|string',
            'imageable_id' => 'required|integer',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|image|max:51200', // 50MB max
            'sort_order' => 'nullable|integer',
            'role' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
            'also_attach_to_service_ticket' => 'sometimes|boolean',
        ])->validate();

        try {
            // Handle file upload
            if (isset($validated['file']) && $validated['file'] instanceof UploadedFile) {
                $uploadedFile = $validated['file'];
                $directory = InventoryImageStorageDirectory::forType($validated['imageable_type']);
                $uploadResult = $this->publicStorage->store(
                    $uploadedFile,
                    $directory,
                    2000, // max width
                    null,
                    false, // don't crop
                    true,  // delete old
                    false  // not private
                );

                // Replace file field with uploaded file info
                $validated['file'] = $uploadResult['key'];
                $validated['file_extension'] = $uploadResult['file_extension'];
                $validated['file_size'] = $uploadResult['file_size'];
            }

            // Set sort_order default if not provided
            if (! isset($validated['sort_order'])) {
                $validated['sort_order'] = 0;
            }

            $imageableType = (string) $validated['imageable_type'];
            $imageableId = (int) $validated['imageable_id'];

            if (AttachmentLink::usesLinksForMorphClass($imageableType)) {
                $hasExisting = AttachmentLink::query()
                    ->where('attachable_type', $imageableType)
                    ->where('attachable_id', $imageableId)
                    ->exists()
                    || RecordModel::query()
                        ->where('imageable_type', $imageableType)
                        ->where('imageable_id', $imageableId)
                        ->exists();
            } else {
                $hasExisting = RecordModel::query()
                    ->where('imageable_type', $imageableType)
                    ->where('imageable_id', $imageableId)
                    ->exists();
            }

            if (! $hasExisting) {
                $validated['is_primary'] = true;
            } elseif (! isset($validated['is_primary'])) {
                $validated['is_primary'] = false;
            }

            // Set created_by and updated_by
            $validated['created_by_id'] = auth()->id();
            $validated['updated_by_id'] = auth()->id();

            $record = RecordModel::create($validated);

            if (AttachmentLink::usesLinksForMorphClass((string) $record->imageable_type)) {
                $attach = app(InventoryImageAttachmentService::class);
                $attach->ensureLinkForMorphOwner($record);
                $also = filter_var($data['also_attach_to_service_ticket'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $attach->maybeAlsoLinkWorkOrderUploadToServiceTicket($record, $also);
                $attach->normalizePrimaryLinksForAttachable((string) $record->imageable_type, (int) $record->imageable_id);
                if ($also && $record->imageable_type === WorkOrder::class) {
                    $wo = WorkOrder::query()->find((int) $record->imageable_id);
                    if ($wo && $wo->service_ticket_id) {
                        $attach->normalizePrimaryLinksForAttachable(
                            ServiceTicket::class,
                            (int) $wo->service_ticket_id
                        );
                    }
                }
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateInventoryImage', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateInventoryImage', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
