<?php
namespace App\Domain\Vendor\Actions;

use App\Domain\Vendor\Models\Vendor as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class DeleteVendor
{
    /**
     * Handle the action.
     *
     * @param  int  $id
     * @return array
     */
    public function __invoke(int $id): array
    {
        try {
            $vendor = RecordModel::findOrFail($id);
            $vendor->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteVendor', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteVendor', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}