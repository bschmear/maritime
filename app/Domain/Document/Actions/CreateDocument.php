<?php
namespace App\Domain\Document\Actions;

use App\Domain\Document\Models\Document as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateDocument
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx,csv,txt,xlsx,excel,x-excel,x-msexcel', 'max:51200'], // 50MB max
            'assigned_id' => ['nullable', 'exists:users,id'],
        ])->validate();

        $user = auth()->user();

        // Note: 'currentTeam' and storage limit logic adapted from request.
        // Ensure User model has 'currentTeam' and Team model has 'document_storage_used'.
        // If tenancy is handled differently (e.g. global tenant() helper), update accordingly.

        /* 
        // Storage Limit Check (Uncomment and adjust if Team/Tenant model is available)
        $team = $user->currentTeam; 
        if ($team) {
            $currentStorage = $team->document_storage_used;
            // Assuming simplified check or config existence
            $availableStorage = 1073741824; // 1GB limit placeholder
             // $availableStorage = Config::get('global.subscriptionOptions')[$subscription->level]['storage'];

            $fileSize = $data['file']->getSize();
            if (($currentStorage + $fileSize) > $availableStorage) {
                 throw new \Exception('Storage limit reached.');
            }
        }
        */

        try {
            $file = $data['file'];
            $fileSize = $file->getSize();
            $info = pathinfo($file->getClientOriginalName());
            $extension = $info['extension'] ?? '';
            // Generate filename: name-timestamp.ext
            $filename = \Illuminate\Support\Str::slug(basename($file->getClientOriginalName(), '.' . $extension)) . '-' . time() . '.' . $extension;

            // Path: documents/{user_id}/{filename} (assuming tenant isolation handles the tenant path, or add tenant_id if needed)
            $path = 'documents/' . $user->id . '/' . $filename;

            // Upload to S3
            $uploaded = \Illuminate\Support\Facades\Storage::disk('s3')->put($path, file_get_contents($file), 'private');

            if (!$uploaded) {
                throw new \Exception('Failed to upload the document to S3.');
            }

            return \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $user, $path, $info, $filename, $fileSize, $extension) {
                // Update team storage if applicable
                // $user->currentTeam?->incrementDocumentStorage($fileSize);

                $record = RecordModel::create([
                    'display_name' => $validated['display_name'] ?? $filename,
                    'description' => $validated['description'] ?? null,
                    'file' => $path,
                    'file_extension' => $extension,
                    'file_size' => $fileSize,
                    'created_by_id' => $user->id,
                    'assigned_id' => $validated['assigned_id'] ?? $user->id,
                    'ai_status' => 'pending',
                ]);

                // Generate signed URL (optional, for return)
                // $url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(60));

                return [
                    'success' => true,
                    'record' => $record,
                ];
            });

        } catch (QueryException $e) {
            Log::error('Database query error in CreateDocument', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateDocument', [
                'error' => $e->getMessage(),
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