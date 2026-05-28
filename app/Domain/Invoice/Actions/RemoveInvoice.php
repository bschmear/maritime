<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Enums\Invoice\Status;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class RemoveInvoice
{
    /**
     * @param  'delete'|'void'  $disposition
     * @param  'auto'|'void'|'delete'  $quickbooksOperation
     * @return array{success: bool, message?: string}
     */
    public function __invoke(
        int $id,
        string $disposition = 'delete',
        bool $quickbooks = false,
        string $quickbooksOperation = 'auto',
    ): array {
        try {
            $record = RecordModel::findOrFail($id);

            if ($disposition === 'void' && $record->status === Status::Void->value) {
                return ['success' => false, 'message' => 'This invoice is already void.'];
            }

            if ($disposition === 'delete' && $record->paid_at !== null) {
                return ['success' => false, 'message' => 'Paid invoices cannot be deleted. Void the invoice instead.'];
            }

            $qboNote = null;

            if ($quickbooks && $record->isQuickbooksManaged()) {
                $qbo = app(QuickBooksAccountingService::class)->removeRemoteInvoice(
                    (string) $record->quickbooks_invoice_id,
                    $quickbooksOperation,
                );

                if (! ($qbo['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $qbo['message'] ?? 'Could not update this invoice in QuickBooks.',
                    ];
                }

                $op = (string) ($qbo['operation'] ?? '');
                $qboNote = match ($op) {
                    'void' => 'QuickBooks invoice was voided.',
                    'delete' => 'QuickBooks invoice was deleted.',
                    default => null,
                };

                $record->update([
                    'quickbooks_invoice_id' => null,
                    'quickbooks_invoice_url' => null,
                ]);
            }

            if ($disposition === 'void') {
                $record->update([
                    'status' => Status::Void->value,
                    'amount_due' => 0,
                ]);

                $message = 'Invoice voided.';
                if ($qboNote) {
                    $message .= ' '.$qboNote;
                }

                return ['success' => true, 'message' => $message, 'disposition' => 'void'];
            }

            $record->delete();

            $message = 'Invoice deleted successfully.';
            if ($qboNote) {
                $message .= ' '.$qboNote;
            }

            return ['success' => true, 'message' => $message, 'disposition' => 'delete'];
        } catch (QueryException $e) {
            Log::error('Database query error in RemoveInvoice', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in RemoveInvoice', [
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
