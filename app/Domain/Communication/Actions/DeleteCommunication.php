<?php

declare(strict_types=1);

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Communication as RecordModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteCommunication
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::query()->findOrFail($id);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Communication deleted successfully.',
            ];
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteCommunication', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteCommunication', [
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
