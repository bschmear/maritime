<?php

namespace App\Domain\Document\Actions;

use App\Actions\PublicStorage;
use App\Domain\Contact\Models\Contact;
use App\Domain\Document\Models\Document as RecordModel;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateDocument
{
    public function __invoke(array $data, ?Model $attachTo = null): array
    {
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,pdf,doc,docx,csv,txt,xlsx,excel,x-excel,x-msexcel', 'max:51200'], // 50MB max
            'assigned_id' => ['nullable', 'exists:users,id'],
            'created_by_id' => ['nullable', 'exists:users,id'],
        ])->validate();

        $authUser = auth()->user();
        $staffUserId = $validated['created_by_id'] ?? null;
        if ($staffUserId === null && ! ($authUser instanceof Contact)) {
            $staffUserId = current_tenant_user_id();
        }

        $assignedId = $validated['assigned_id'] ?? $staffUserId;

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
            /** @var UploadedFile $file */
            $file = $data['file'];

            $uploadResult = app(PublicStorage::class)->store(
                $file,
                'documents',
                null,
                null,
                false,
                true,
                true,
            );

            $path = $uploadResult['key'];
            $filename = $uploadResult['file_name'];
            $fileSize = $uploadResult['file_size'];
            $extension = $uploadResult['file_extension'];

            return DB::transaction(function () use ($validated, $staffUserId, $assignedId, $path, $filename, $fileSize, $extension, $attachTo) {
                // Update team storage if applicable
                // $user->currentTeam?->incrementDocumentStorage($fileSize);

                $record = RecordModel::create([
                    'display_name' => $validated['display_name'] ?? $filename,
                    'description' => $validated['description'] ?? null,
                    'file' => $path,
                    'file_extension' => $extension,
                    'file_size' => $fileSize,
                    'created_by_id' => $staffUserId,
                    'assigned_id' => $assignedId,
                    'ai_status' => 'pending',
                ]);

                // Attach to parent model if provided
                if ($attachTo && method_exists($attachTo, 'attachDocument')) {
                    $attachTo->attachDocument($record);
                }

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
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => 'Database error: '.$e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateDocument', [
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

    /**
     * S3 key for generated documents (matches PublicStorage private layout).
     */
    public static function storagePathForFilename(string $filename): string
    {
        $tenantPrefix = tenant() ? tenant()->id.'/' : '';

        return "private/{$tenantPrefix}documents/{$filename}";
    }
}
