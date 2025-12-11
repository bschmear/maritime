<?php
namespace App\Domain\Document\Actions;

use App\Domain\Document\Models\Document as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeleteDocument
{
    public function __invoke(int $id): array
    {
        $user = auth()->user();

        try {
            $record = RecordModel::findOrFail($id);

            return DB::transaction(function () use ($record, $user) {
                // Storage decrement logic placeholder
                /*
                $team = $user->currentTeam;
                $team?->decrementDocumentStorage($record->file_size);
                */

                // Delete file from S3
                if ($record->file) {
                    Storage::disk('s3')->delete($record->file);
                }

                // Delete record
                $record->delete();

                return [
                    'success' => true,
                ];
            });

        } catch (QueryException $e) {
            Log::error('Database query error in DeleteDocument', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteDocument', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}