<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Document\Actions\CreateDocument;
use App\Domain\Document\Models\Document;
use App\Domain\MsoRecord\Actions\DeleteMsoRecord;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Models\MsoLayoutTemplate;
use App\Domain\MsoRecord\Support\GenerateMsoPdf;
use App\Domain\MsoRecord\Support\MsoRecordDetails;
use App\Domain\MsoRecord\Support\MsoValueResolver;
use App\Domain\MsoRecord\Support\SaveMsoBuilderState;
use App\Domain\MsoRecord\Support\SyncTransactionMsoFlags;
use App\Domain\MsoRecord\Support\UpsertMsoRecordForLineItem;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\User\Models\User;
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
use RuntimeException;

class MsoController extends BaseController
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $tab = (string) $request->get('tab', 'pending');
        if (! in_array($tab, ['pending', 'existing'], true)) {
            $tab = 'pending';
        }

        if ($tab === 'existing') {
            $records = MsoRecord::query()
                ->with([
                    'transaction' => fn ($query) => $query->select(['id', 'sequence', 'title']),
                    'assetUnit' => fn ($query) => $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                        ->with(['asset' => fn ($assetQuery) => $assetQuery->select(['id', 'display_name'])]),
                    'createdBy' => fn ($query) => $query->select(['id', 'display_name', 'first_name', 'last_name']),
                ])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (MsoRecord $record) => [
                    'id' => $record->id,
                    'display_name' => $record->display_name,
                    'status' => $record->status?->value,
                    'status_label' => $record->status?->label() ?? '—',
                    'transaction_id' => $record->transaction_id,
                    'transaction_line_item_id' => $record->transaction_line_item_id,
                    'transaction_display_name' => $record->transaction?->display_name,
                    'asset_unit_display_name' => $record->assetUnit?->display_name,
                    'submitted_at' => $record->submitted_at?->toIso8601String(),
                    'created_at' => $record->created_at?->toIso8601String(),
                    'created_by_display_name' => $record->createdBy?->display_name
                        ?: trim(($record->createdBy?->first_name ?? '').' '.($record->createdBy?->last_name ?? ''))
                        ?: null,
                ]);

            return Inertia::render('Tenant/Mso/Index', [
                'tab' => $tab,
                'transactions' => null,
                'records' => $records,
            ]);
        }

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
            ->withQueryString()
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name ?? $transaction->customer?->display_name,
                'closed_at' => $transaction->closed_at?->toIso8601String(),
                'asset_unit_lines_count' => (int) $transaction->asset_unit_lines_count,
                'mso_needed' => (bool) $transaction->mso_needed,
                'mso_created' => (bool) $transaction->mso_created,
            ]);

        return Inertia::render('Tenant/Mso/Index', [
            'tab' => $tab,
            'transactions' => $transactions,
            'records' => null,
        ]);
    }

    public function pending(Request $request): RedirectResponse
    {
        return redirect()->route('mso.index', ['tab' => 'pending']);
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
            ->route('mso.index', ['tab' => 'pending'])
            ->with('success', 'MSO statuses updated for this deal.');
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'line_item_id' => ['required', 'integer', 'exists:transaction_line_items,id'],
            'layout_template_id' => ['nullable', 'integer', 'exists:mso_layout_templates,id'],
        ]);

        $transaction = Transaction::query()
            ->with(['customer.contact', 'subsidiary'])
            ->findOrFail((int) $validated['transaction_id']);

        $lineItem = TransactionLineItem::query()
            ->where('parent_type', Transaction::class)
            ->where('parent_id', $transaction->id)
            ->whereKey((int) $validated['line_item_id'])
            ->whereNotNull('asset_unit_id')
            ->firstOrFail();

        $assetUnit = AssetUnit::query()->findOrFail((int) $lineItem->asset_unit_id);
        $msoRecord = UpsertMsoRecordForLineItem::ensureDraft($transaction, $lineItem);

        if (isset($validated['layout_template_id'])) {
            $msoRecord->layout_template_id = (int) $validated['layout_template_id'];
            $msoRecord->save();
        }

        $msoRecord->load('sourceDocument');
        $msoRecord->refresh();

        return Inertia::render('Tenant/Mso/Create', $this->builderPageProps(
            $msoRecord,
            $transaction,
            $lineItem,
            $assetUnit,
            isset($validated['layout_template_id']) ? (int) $validated['layout_template_id'] : null,
        ));
    }

    public function show(MsoRecord $msoRecord): Response
    {
        $msoRecord->load([
            'transaction.customer',
            'transaction.subsidiary',
            'assetUnit',
            'sourceDocument',
            'outputDocument',
            'createdBy',
        ]);

        $transaction = $msoRecord->transaction;
        $lineItem = $msoRecord->transaction_line_item_id
            ? TransactionLineItem::query()->find($msoRecord->transaction_line_item_id)
            : null;

        return Inertia::render('Tenant/Mso/Show', [
            'msoRecord' => [
                'id' => $msoRecord->id,
                'display_name' => $msoRecord->display_name,
                'status' => $msoRecord->status?->value,
                'status_label' => $msoRecord->status?->label(),
                'submitted_at' => $msoRecord->submitted_at?->toIso8601String(),
                'created_at' => $msoRecord->created_at?->toIso8601String(),
                'details' => MsoRecordDetails::normalize($msoRecord->details),
            ],
            'transaction' => $transaction ? [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name,
            ] : null,
            'assetUnit' => $msoRecord->assetUnit ? [
                'id' => $msoRecord->assetUnit->id,
                'display_name' => $msoRecord->assetUnit->display_name,
            ] : null,
            'lineItem' => $lineItem ? [
                'id' => $lineItem->id,
                'name' => $lineItem->name,
                'transaction_id' => $transaction?->id,
            ] : null,
            'sourceDocument' => $this->serializeDocument($msoRecord->sourceDocument),
            'outputDocument' => $this->serializeDocument($msoRecord->outputDocument),
            'builderUrl' => ($transaction && $lineItem)
                ? route('mso.create', [
                    'transaction_id' => $transaction->id,
                    'line_item_id' => $lineItem->id,
                ])
                : null,
        ]);
    }

    public function saveBuilder(Request $request, MsoRecord $msoRecord): JsonResponse|RedirectResponse
    {
        $result = SaveMsoBuilderState::handle($msoRecord, $request->all());
        $record = $result['record'];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'msoRecord' => [
                    'id' => $record->id,
                    'status' => $record->status?->value,
                    'layout_template_id' => $record->layout_template_id,
                    'details' => MsoRecordDetails::normalize($record->details),
                ],
                'layoutTemplate' => $result['layoutTemplate'],
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'MSO draft saved.');
    }

    public function uploadSourceDocument(Request $request, MsoRecord $msoRecord): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $assetUnit = AssetUnit::query()->findOrFail((int) $msoRecord->asset_unit_id);

        $result = (new CreateDocument)([
            'file' => $validated['file'],
            'display_name' => $validated['display_name'] ?? $validated['file']->getClientOriginalName(),
            'description' => 'Original MSO for asset unit #'.$assetUnit->id,
            'created_by_id' => current_tenant_user_id(),
        ]);

        if (! ($result['success'] ?? false) || ! $result['record']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to upload MSO document.',
            ], 422);
        }

        /** @var Document $document */
        $document = $result['record'];

        $assetUnit->attachDocumentWithRole($document, [
            'role' => 'mso',
            'visible_to_customer' => false,
            'visible_to_vendor' => false,
        ]);

        $msoRecord->source_document_id = $document->id;
        $msoRecord->save();

        return response()->json([
            'success' => true,
            'sourceDocument' => $this->serializeDocument($document),
        ]);
    }

    public function generatePdf(Request $request, MsoRecord $msoRecord): JsonResponse|RedirectResponse
    {
        SaveMsoBuilderState::handle($msoRecord, $request->all())['record'];
        $msoRecord->refresh();

        try {
            $document = GenerateMsoPdf::handle($msoRecord);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'outputDocument' => $this->serializeDocument($document),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'MSO PDF generated.');
    }

    public function submit(Request $request, MsoRecord $msoRecord): RedirectResponse
    {
        SaveMsoBuilderState::handle($msoRecord, $request->all())['record'];
        $msoRecord->refresh();

        try {
            GenerateMsoPdf::handle($msoRecord);
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        $msoRecord->refresh();
        $msoRecord->fill([
            'status' => Status::Submitted,
            'submitted_at' => now(),
        ])->save();

        if ($msoRecord->transaction_id) {
            $transaction = Transaction::query()->find($msoRecord->transaction_id);
            if ($transaction) {
                SyncTransactionMsoFlags::forTransaction($transaction);
            }
        }

        return redirect()
            ->route('mso.show', $msoRecord->id)
            ->with('success', 'MSO submitted successfully.');
    }

    public function destroy(Request $request, MsoRecord $msoRecord): RedirectResponse
    {
        $resetTransaction = $request->boolean('reset_transaction', true);

        $result = (new DeleteMsoRecord)($msoRecord->id, $resetTransaction);

        if (! ($result['success'] ?? false)) {
            return redirect()
                ->back()
                ->with('error', $result['message'] ?? 'Failed to delete MSO.');
        }

        $message = $resetTransaction
            ? 'MSO deleted. The deal will require an MSO again if applicable.'
            : 'MSO deleted.';

        return redirect()
            ->route('mso.index', ['tab' => 'existing'])
            ->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function builderPageProps(
        MsoRecord $msoRecord,
        Transaction $transaction,
        TransactionLineItem $lineItem,
        AssetUnit $assetUnit,
        ?int $layoutTemplateId = null,
    ): array {
        $details = MsoRecordDetails::normalize($msoRecord->details);
        $assignedUserId = $details['assigned_user_id'] ?? current_tenant_user_id();
        $assignedUser = $assignedUserId ? User::query()->find((int) $assignedUserId) : null;
        $sourceDocument = $msoRecord->sourceDocument ?? $assetUnit->msoSourceDocument();

        if ($sourceDocument && ! $msoRecord->source_document_id) {
            $msoRecord->source_document_id = $sourceDocument->id;
            $msoRecord->save();
        }

        $layoutTemplates = MsoLayoutTemplate::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MsoLayoutTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
            ])
            ->values()
            ->all();

        $storedFields = MsoRecordDetails::fields($msoRecord->details);
        $resolvedTemplateId = $msoRecord->layout_template_id ?? $layoutTemplateId;
        $appliedTemplate = $resolvedTemplateId
            ? MsoLayoutTemplate::query()->find($resolvedTemplateId)
            : null;

        if ($storedFields !== []) {
            $fields = MsoValueResolver::hydrateFieldValues($storedFields, $msoRecord, $assignedUser);
        } elseif ($appliedTemplate) {
            $fields = SaveMsoBuilderState::fieldsFromTemplate($appliedTemplate->layout, $msoRecord, $assignedUser);
        } else {
            $fields = [];
        }

        $showTemplatePicker = $storedFields === [] && ! $resolvedTemplateId && $layoutTemplates !== [];

        $users = User::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'first_name', 'last_name', 'position_title', 'signature_method', 'signature_file', 'typed_signature', 'signature_saved_at'])
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'display_name' => $user->display_name ?: $user->full_name,
                    'position_title' => $user->position_title,
                    'signature' => $user->savedSignaturePayload(),
                ];
            })
            ->values()
            ->all();

        $addressParts = [
            'line1' => $transaction->billing_address_line1,
            'line2' => $transaction->billing_address_line2,
            'city_state_zip' => trim(implode(', ', array_filter([
                $transaction->billing_city,
                $transaction->billing_state,
                $transaction->billing_postal,
            ]))),
            'country' => $transaction->billing_country,
        ];

        $transaction->loadMissing('location');

        $dealershipAddressParts = [
            'line1' => $transaction->location?->address_line_1,
            'line2' => $transaction->location?->address_line_2,
            'city_state_zip' => trim(implode(', ', array_filter([
                $transaction->location?->city,
                $transaction->location?->state,
                $transaction->location?->postal_code,
            ]))),
            'country' => $transaction->location?->country,
        ];

        $prefill = MsoValueResolver::prefillMap($msoRecord, $assignedUser);
        $prefill['customer_address_parts'] = $addressParts;
        $prefill['dealership_address_parts'] = $dealershipAddressParts;
        if ($transaction->location) {
            $prefill['dealership_address'] = MsoValueResolver::formatLocationAddress([
                'address_line_1' => $transaction->location->address_line_1,
                'address_line_2' => $transaction->location->address_line_2,
                'city' => $transaction->location->city,
                'state' => $transaction->location->state,
                'postal_code' => $transaction->location->postal_code,
                'country' => $transaction->location->country,
            ], 'multiline');
        }

        return [
            'transaction' => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name ?? $transaction->customer?->display_name,
                'customer_email' => $transaction->customer_email,
                'customer_phone' => $transaction->customer_phone,
                'customer_title' => $transaction->customer?->title ?? $transaction->customer?->contact?->title,
                'customer_address' => MsoValueResolver::formatCustomerAddress([
                    'billing_address_line1' => $transaction->billing_address_line1,
                    'billing_address_line2' => $transaction->billing_address_line2,
                    'billing_city' => $transaction->billing_city,
                    'billing_state' => $transaction->billing_state,
                    'billing_postal' => $transaction->billing_postal,
                    'billing_country' => $transaction->billing_country,
                ], 'multiline'),
                'closed_at' => $transaction->closed_at?->toIso8601String(),
                'subsidiary_name' => $transaction->subsidiary?->display_name,
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
            'sourceDocument' => $this->serializeDocument($sourceDocument),
            'msoRecord' => [
                'id' => $msoRecord->id,
                'status' => $msoRecord->status?->value,
                'assigned_user_id' => $assignedUserId,
                'layout_template_id' => $msoRecord->layout_template_id,
                'page_sizes' => MsoRecordDetails::pageSizes($msoRecord->details),
                'fields' => $fields,
                'prefill' => $prefill,
            ],
            'users' => $users,
            'fieldGroups' => $this->fieldTypeGroups(),
            'layoutTemplates' => $layoutTemplates,
            'showTemplatePicker' => $showTemplatePicker,
            'appliedTemplate' => $appliedTemplate ? [
                'id' => $appliedTemplate->id,
                'name' => $appliedTemplate->name,
            ] : null,
        ];
    }

    /**
     * @return list<array{label: string, fields: list<array{value: string, label: string}>}>
     */
    private function fieldTypeGroups(): array
    {
        return [
            [
                'label' => 'Customer fields',
                'fields' => [
                    ['value' => 'customer_name', 'label' => 'Customer name'],
                    ['value' => 'customer_address', 'label' => 'Customer address'],
                    ['value' => 'customer_phone', 'label' => 'Customer phone'],
                    ['value' => 'customer_title', 'label' => 'Customer title'],
                ],
            ],
            [
                'label' => 'Dealership fields',
                'fields' => [
                    ['value' => 'dealership_name', 'label' => 'Dealership name'],
                    ['value' => 'dealership_address', 'label' => 'Dealership address'],
                ],
            ],
            [
                'label' => 'Date & time',
                'fields' => [
                    ['value' => 'date', 'label' => 'Date (MM/DD/YYYY)'],
                    ['value' => 'current_month', 'label' => 'Current month'],
                    ['value' => 'current_day', 'label' => 'Current day'],
                    ['value' => 'current_year', 'label' => 'Current year'],
                    ['value' => 'current_time', 'label' => 'Current time'],
                ],
            ],
            [
                'label' => 'Line item',
                'fields' => [
                    ['value' => 'line_item', 'label' => 'Line item'],
                    ['value' => 'line_item_price', 'label' => 'Line item price'],
                ],
            ],
            [
                'label' => 'User fields',
                'fields' => [
                    ['value' => 'user_name', 'label' => 'User name'],
                    ['value' => 'user_position_title', 'label' => 'User position / title'],
                    ['value' => 'user_signature', 'label' => 'User signature'],
                ],
            ],
            [
                'label' => 'Other',
                'fields' => [
                    ['value' => 'free_text', 'label' => 'Free text'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeDocument(?Document $document): ?array
    {
        if (! $document) {
            return null;
        }

        return [
            'id' => $document->id,
            'display_name' => $document->display_name,
            'file_extension' => $document->file_extension,
            'download_url' => route('documents.download', $document->id),
            'preview_url' => route('documents.stream', $document->id),
        ];
    }
}
