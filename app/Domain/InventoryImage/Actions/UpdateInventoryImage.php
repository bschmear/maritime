<?php

namespace App\Domain\InventoryImage\Actions;

use App\Actions\PublicStorage;
use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\InventoryImage\Models\InventoryImage as RecordModel;
use App\Domain\InventoryImage\Support\InventoryImageStorageDirectory;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateInventoryImage
{
    protected $publicStorage;

    public function __construct()
    {
        $this->publicStorage = new PublicStorage;
    }

    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'imageable_type' => 'sometimes|string',
            'imageable_id' => 'sometimes|integer',
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'file' => 'sometimes|file|image|max:51200', // 50MB max
            'sort_order' => 'sometimes|integer',
            'role' => 'nullable|string',
            'is_primary' => 'sometimes|boolean',
            'visible_to_customer' => 'sometimes|boolean',
            'attachable_type' => 'sometimes|string',
            'attachable_id' => 'sometimes|integer',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);

            $attachableType = $validated['attachable_type'] ?? null;
            $attachableId = isset($validated['attachable_id']) ? (int) $validated['attachable_id'] : null;
            unset($validated['attachable_type'], $validated['attachable_id']);

            $linkContext = $attachableType
                && $attachableId !== null
                && $attachableId > 0
                && AttachmentLink::usesLinksForMorphClass((string) $attachableType);

            if ($linkContext) {
                $attach = app(InventoryImageAttachmentService::class);
                if (array_key_exists('is_primary', $validated)) {
                    if ($validated['is_primary']) {
                        $attach->setPrimaryForAttachable((string) $attachableType, $attachableId, $id);
                    }
                    unset($validated['is_primary']);
                }
                if (array_key_exists('sort_order', $validated)) {
                    $attach->updateSortOrderForAttachable((string) $attachableType, $attachableId, $id, (int) $validated['sort_order']);
                    unset($validated['sort_order']);
                }
                if (array_key_exists('visible_to_customer', $validated)) {
                    $attach->updateVisibleToCustomerForAttachable(
                        (string) $attachableType,
                        $attachableId,
                        $id,
                        (bool) $validated['visible_to_customer'],
                    );
                    unset($validated['visible_to_customer']);
                }
            }

            if (! $linkContext && isset($validated['is_primary']) && $validated['is_primary']) {
                RecordModel::where('imageable_type', $record->imageable_type)
                    ->where('imageable_id', $record->imageable_id)
                    ->where('id', '!=', $id)
                    ->update(['is_primary' => false]);
            }

            if (isset($validated['file']) && $validated['file'] instanceof UploadedFile) {
                $uploadedFile = $validated['file'];
                $imageableType = $validated['imageable_type'] ?? $record->imageable_type;
                $directory = InventoryImageStorageDirectory::forType((string) $imageableType);
                $uploadResult = $this->publicStorage->store(
                    $uploadedFile,
                    $directory,
                    2000, // max width
                    $record->file, // existing file to delete
                    false, // don't crop
                    true,  // delete old
                    false  // not private
                );

                $validated['file'] = $uploadResult['key'];
                $validated['file_extension'] = $uploadResult['file_extension'];
                $validated['file_size'] = $uploadResult['file_size'];
            }

            $validated['updated_by_id'] = auth()->id();

            if ($validated !== []) {
                $record->update($validated);
            }

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateInventoryImage', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateInventoryImage', [
                'error' => $e->getMessage(),
                'id' => $id,
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
