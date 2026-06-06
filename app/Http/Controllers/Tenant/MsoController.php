<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Support\SyncTransactionMsoFlags;
use App\Domain\MsoRecord\Support\UpsertMsoRecordForLineItem;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Enums\MsoRecord\Status;
use App\Enums\Transaction\TransactionStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MsoController extends BaseController
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function pending(Request $request): Response
    {
        $transactions = Transaction::query()
            ->where('mso_needed', true)
            ->where('mso_created', false)
            ->where(function ($query) {
                $query->where('status', TransactionStatus::Completed->value)
                    ->orWhere('status', (string) TransactionStatus::Completed->id())
                    ->orWhere('status', 'won');
            })
            ->with(['customer'])
            ->withCount([
                'items as asset_unit_lines_count' => function ($query) {
                    $query->whereNotNull('asset_unit_id');
                },
            ])
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name ?? $transaction->customer?->display_name,
                'closed_at' => $transaction->closed_at?->toIso8601String(),
                'asset_unit_lines_count' => (int) $transaction->asset_unit_lines_count,
                'mso_needed' => (bool) $transaction->mso_needed,
                'mso_created' => (bool) $transaction->mso_created,
            ]);

        return Inertia::render('Tenant/Mso/Pending', [
            'transactions' => $transactions,
        ]);
    }

    public function units(Transaction $transaction): JsonResponse
    {
        $lines = TransactionLineItem::query()
            ->where('parent_type', Transaction::class)
            ->where('parent_id', $transaction->id)
            ->whereNotNull('asset_unit_id')
            ->with(['assetUnit'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $recordsByLine = MsoRecord::query()
            ->whereIn('transaction_line_item_id', $lines->pluck('id'))
            ->get()
            ->keyBy('transaction_line_item_id');

        $units = $lines->map(function (TransactionLineItem $line) use ($recordsByLine) {
            $record = $recordsByLine->get($line->id);
            $status = $record?->status?->value;

            return [
                'transaction_line_item_id' => $line->id,
                'asset_unit_id' => $line->asset_unit_id,
                'display_name' => $line->assetUnit?->display_name ?? $line->name ?? 'Asset unit',
                'line_name' => $line->name,
                'mso_record_id' => $record?->id,
                'mso_status' => $status,
                'is_resolved' => $record?->status?->isResolved() ?? false,
            ];
        })->values();

        return response()->json([
            'transaction' => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
            ],
            'units' => $units,
        ]);
    }

    public function batch(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.transaction_line_item_id' => ['required', 'integer', 'distinct'],
            'items.*.status' => ['nullable', 'string', 'in:submitted,not_required'],
        ]);

        $lineIds = collect($validated['items'])->pluck('transaction_line_item_id')->all();

        $lines = TransactionLineItem::query()
            ->where('parent_type', Transaction::class)
            ->where('parent_id', $transaction->id)
            ->whereIn('id', $lineIds)
            ->whereNotNull('asset_unit_id')
            ->get()
            ->keyBy('id');

        if ($lines->count() !== count($lineIds)) {
            return redirect()
                ->back()
                ->with('error', 'One or more line items are invalid for this deal.');
        }

        DB::transaction(function () use ($validated, $transaction, $lines) {
            foreach ($validated['items'] as $item) {
                $statusValue = $item['status'] ?? null;
                if (! $statusValue) {
                    continue;
                }

                $line = $lines->get((int) $item['transaction_line_item_id']);
                if (! $line) {
                    continue;
                }

                $status = Status::from($statusValue);
                UpsertMsoRecordForLineItem::handle($transaction, $line, $status);
            }

            SyncTransactionMsoFlags::forTransaction($transaction);
        });

        $transaction->refresh();

        $assetUnitLineIds = TransactionLineItem::query()
            ->where('parent_type', Transaction::class)
            ->where('parent_id', $transaction->id)
            ->whereNotNull('asset_unit_id')
            ->pluck('id');

        $resolvedLineIds = MsoRecord::query()
            ->whereIn('transaction_line_item_id', $assetUnitLineIds)
            ->whereIn('status', [Status::Submitted->value, Status::NotRequired->value])
            ->pluck('transaction_line_item_id');

        $pendingLineId = $assetUnitLineIds->first(fn ($id) => ! $resolvedLineIds->contains($id));

        $pendingLine = $pendingLineId
            ? TransactionLineItem::query()->find($pendingLineId)
            : null;

        if ($pendingLine) {
            UpsertMsoRecordForLineItem::ensureDraft($transaction, $pendingLine);

            return redirect()
                ->route('mso.create', [
                    'transaction_id' => $transaction->id,
                    'line_item_id' => $pendingLine->id,
                ])
                ->with('success', 'Continue creating MSOs for the remaining units.');
        }

        return redirect()
            ->route('mso.pending')
            ->with('success', 'MSO statuses updated for this deal.');
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'line_item_id' => ['required', 'integer', 'exists:transaction_line_items,id'],
        ]);

        $transaction = Transaction::query()
            ->with(['customer'])
            ->findOrFail((int) $validated['transaction_id']);

        $lineItem = TransactionLineItem::query()
            ->where('parent_type', Transaction::class)
            ->where('parent_id', $transaction->id)
            ->whereKey((int) $validated['line_item_id'])
            ->whereNotNull('asset_unit_id')
            ->firstOrFail();

        $assetUnit = AssetUnit::query()->findOrFail((int) $lineItem->asset_unit_id);
        $sourceDocument = $assetUnit->msoSourceDocument();

        $msoRecord = UpsertMsoRecordForLineItem::ensureDraft($transaction, $lineItem);

        return Inertia::render('Tenant/Mso/Create', [
            'transaction' => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name ?? $transaction->customer?->display_name,
                'customer_email' => $transaction->customer_email,
                'customer_phone' => $transaction->customer_phone,
                'closed_at' => $transaction->closed_at?->toIso8601String(),
            ],
            'lineItem' => [
                'id' => $lineItem->id,
                'name' => $lineItem->name,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
            ],
            'assetUnit' => [
                'id' => $assetUnit->id,
                'display_name' => $assetUnit->display_name,
                'serial_number' => $assetUnit->serial_number,
                'hin' => $assetUnit->hin,
            ],
            'sourceDocument' => $sourceDocument ? [
                'id' => $sourceDocument->id,
                'display_name' => $sourceDocument->display_name,
                'file_extension' => $sourceDocument->file_extension,
                'download_url' => route('documents.download', $sourceDocument->id),
            ] : null,
            'msoRecord' => [
                'id' => $msoRecord->id,
                'status' => $msoRecord->status?->value,
                'details' => $msoRecord->details,
            ],
        ]);
    }
}
