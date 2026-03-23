<?php
namespace App\Domain\AddOn\Actions;

use App\Domain\AddOn\Models\AddOn as RecordModel;
use App\Domain\Estimate\Models\EstimateLineItemAddon;
use App\Domain\Transaction\Models\TransactionItemAddon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAddOn
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);

            $onTransactions = TransactionItemAddon::query()->where('addon_id', $id)->exists();
            $onEstimates = EstimateLineItemAddon::query()->where('addon_id', $id)->exists();

            if ($onTransactions || $onEstimates) {
                $parts = [];
                if ($onTransactions) {
                    $parts[] = 'transaction line items';
                }
                if ($onEstimates) {
                    $parts[] = 'estimate line items';
                }

                return [
                    'success' => false,
                    'message' => 'This add-on cannot be deleted because it is used on '.implode(' and ', $parts).'. Remove it from those deals or estimates first, or deactivate it instead.',
                ];
            }

            $record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteAddOn', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteAddOn', [
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