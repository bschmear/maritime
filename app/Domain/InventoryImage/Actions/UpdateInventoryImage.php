<?php
namespace App\Domain\InventoryImage\Actions;

use App\Domain\InventoryImage\Models\InventoryImage as RecordModel;
use App\Actions\PublicStorage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateInventoryImage
{
    protected $publicStorage;

    public function __construct()
    {
        $this->publicStorage = new PublicStorage();
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
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);

            // Handle primary image logic - ensure only one primary per parent
            if (isset($validated['is_primary']) && $validated['is_primary']) {
                // Unset primary status for all other images of the same parent
                RecordModel::where('imageable_type', $record->imageable_type)
                    ->where('imageable_id', $record->imageable_id)
                    ->where('id', '!=', $id)
                    ->update(['is_primary' => false]);
            }

            // Handle file upload if a new file is provided
            if (isset($validated['file']) && $validated['file'] instanceof UploadedFile) {
                $uploadedFile = $validated['file'];
                $uploadResult = $this->publicStorage->store(
                    $uploadedFile,
                    'inventory/images',
                    2000, // max width
                    $record->file, // existing file to delete
                    false, // don't crop
                    true,  // delete old
                    false  // not private
                );

                // Replace file field with uploaded file info
                $validated['file'] = $uploadResult['key'];
                $validated['file_extension'] = $uploadResult['file_extension'];
                $validated['file_size'] = $uploadResult['file_size'];
            }

            // Set updated_by
            $validated['updated_by_id'] = auth()->id();

            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateInventoryImage', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
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
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}