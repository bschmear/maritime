<?php
namespace App\Domain\Document\Actions;

use App\Domain\Document\Models\Document as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateDocument
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx,csv,txt,xlsx,excel,x-excel,x-msexcel', 'max:51200'],
            'assigned_id' => ['nullable', 'exists:users,id'],
        ])->validate();

        $user = auth()->user();

        try {
            $record = RecordModel::findOrFail($id);

            return DB::transaction(function () use ($record, $data, $validated, $user) {
                // Update basic fields
                $record->update([
                    'display_name' => $validated['display_name'],
                    'description' => $validated['description'] ?? $record->description,
                    'assigned_id' => $validated['assigned_id'] ?? $record->assigned_id,
                    'updated_by_id' => $user->id,
                ]);

                // Handle File Upload if present
                if (isset($data['file']) && $data['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $file = $data['file'];
                    $oldFilePath = $record->file;
                    $oldFileSize = $record->file_size;
                    $newFileSize = $file->getSize();

                    // Storage check logic placeholder
                    /*
                    $team = $user->currentTeam;
                    if ($team) {
                         // Check limit ...
                    }
                    */

                    $info = pathinfo($file->getClientOriginalName());
                    $extension = $info['extension'] ?? '';
                    $filename = \Illuminate\Support\Str::slug(basename($file->getClientOriginalName(), '.' . $extension)) . '-' . time() . '.' . $extension;
                    $path = 'documents/' . $user->id . '/' . $filename;

                    $uploaded = Storage::disk('s3')->put($path, file_get_contents($file), 'private');

                    if (!$uploaded) {
                        throw new \Exception('File upload failed.');
                    }

                    // Delete old file
                    if ($oldFilePath) {
                        Storage::disk('s3')->delete($oldFilePath);
                    }

                    // Update record with new file info
                    $record->update([
                        'file' => $path,
                        'file_extension' => $extension,
                        'file_size' => $newFileSize,
                    ]);

                    // Update storage stats logic placeholder
                    // $team?->decrementDocumentStorage($oldFileSize);
                    // $team?->incrementDocumentStorage($newFileSize);
                }

                return [
                    'success' => true,
                    'record' => $record,
                ];
            });

        } catch (QueryException $e) {
            Log::error('Database query error in UpdateDocument', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateDocument', [
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