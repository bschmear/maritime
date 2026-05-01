<?php

namespace App\Domain\InventoryImage\Actions;

use App\Actions\PublicStorage;
use App\Domain\InventoryImage\Models\InventoryImage as RecordModel;
use App\Domain\InventoryImage\Support\InventoryImageStorageDirectory;
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

            $hasExisting = RecordModel::query()
                ->where('imageable_type', $validated['imageable_type'])
                ->where('imageable_id', $validated['imageable_id'])
                ->exists();

            if (! $hasExisting) {
                $validated['is_primary'] = true;
            } elseif (! isset($validated['is_primary'])) {
                $validated['is_primary'] = false;
            }

            // Set created_by and updated_by
            $validated['created_by_id'] = auth()->id();
            $validated['updated_by_id'] = auth()->id();

            $record = RecordModel::create($validated);

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
