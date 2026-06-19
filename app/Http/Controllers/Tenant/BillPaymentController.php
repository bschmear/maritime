<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillPayment\Actions\CreateBillPayment as CreateAction;
use App\Domain\BillPayment\Actions\DeleteBillPayment as DeleteAction;
use App\Domain\BillPayment\Actions\UpdateBillPayment as UpdateAction;
use App\Domain\BillPayment\Models\BillPayment as RecordModel;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Jobs\PullBillPaymentsFromQuickBooks;
use App\Jobs\PushBillPaymentToQuickBooks;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Response as InertiaResponse;

class BillPaymentController extends RecordController
{
    protected $recordType = 'BillPayment';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'bill-payments',
            'Bill payment',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType,
        );
    }

    /**
     * Bill payments index — explicit eager loads only (no virtual form fields as relationships).
     */
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $tableName = $this->recordModel->getTable();
        $dbColumns = Schema::connection($this->recordModel->getConnectionName())->getColumnListing($tableName);

        $actualColumns = [];
        foreach ($this->getSchemaColumns() as $column) {
            if (! str_contains($column, '.') && in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }

        if (! in_array('sequence', $actualColumns, true)) {
            $actualColumns[] = 'sequence';
        }
        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';
        }

        $query = $this->recordModel->newQuery()
            ->select($actualColumns)
            ->with([
                'vendor' => fn ($q) => $q->select(['id', 'display_name']),
            ]);

        $searchQuery = $request->get('search');
        if (is_string($searchQuery) && trim($searchQuery) !== '') {
            $term = '%'.strtolower(trim($searchQuery)).'%';
            $query->where(function ($q) use ($term, $tableName): void {
                $q->whereRaw("CAST({$tableName}.sequence AS TEXT) LIKE ?", [$term])
                    ->orWhereRaw("CAST({$tableName}.id AS TEXT) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(COALESCE({$tableName}.doc_number, '')) LIKE ?", [$term])
                    ->orWhereHas('vendor', fn ($vq) => $vq->whereRaw('LOWER(display_name) LIKE ?', [$term]));
            });
        }

        $appliedFilters = $this->resolveIndexFiltersFromRequest($request, $schema);
        if ($appliedFilters !== []) {
            $query = $this->applyFilters($query, $appliedFilters, $fieldsSchema);
        }

        $statsBaseQuery = clone $query;

        if (! $this->applyRecordIndexSort($query, $request, $schema, $dbColumns, $tableName, $actualColumns, $fieldsSchema)) {
            $query->orderByDesc($tableName.'.txn_date')
                ->orderByDesc($tableName.'.id');
        }

        $perPage = table_per_page($request);
        $records = $query->paginate($perPage);
        $tableStats = $this->indexTableStats($request, $statsBaseQuery, $schema);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'stats' => $tableStats,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        $indexProps = $this->indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions, $appliedFilters);
        $indexProps['stats'] = $tableStats;
        $indexProps = array_merge($indexProps, $this->indexSupplementInertiaProps($request));

        return inertia('Tenant/BillPayment/Index', $indexProps);
    }

    public function show(Request $request, $id): InertiaResponse
    {
        $record = RecordModel::query()
            ->with([
                'vendor:id,display_name,quickbooks_id',
                'lines' => fn ($query) => $query
                    ->with(['bill:id,sequence,vendor_id,total_amt,balance,status,txn_date,quickbooks_bill_id'])
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->findOrFail($id);

        return inertia('Tenant/BillPayment/Show', array_merge([
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
        ], $this->showPageExtraProps($record)));
    }

    protected function appendShowRelationships(array &$relationships): void
    {
        $relationships['lines'] = fn ($query) => $query
            ->with(['bill:id,sequence,vendor_id,total_amt,balance,status,txn_date,quickbooks_bill_id'])
            ->orderBy('position')
            ->orderBy('id');
    }

    protected function editPageExtraProps($record): array
    {
        return array_merge(
            $this->quickbooksApSyncProps(),
            $this->billPaymentEditRestrictionsProps($record),
        );
    }

    protected function showPageExtraProps($record): array
    {
        $accounting = app(QuickBooksAccountingService::class);

        return array_merge(
            [
                'quickbooks' => [
                    'connected' => $accounting->isConnected(),
                    'sync_bill_payments_enabled' => QuickBooksSettings::forCurrentTenant()->isSyncBillPaymentsEnabled(),
                ],
            ],
            $this->billPaymentEditRestrictionsProps($record),
        );
    }

    protected function createPageExtraProps(): array
    {
        $props = $this->quickbooksApSyncProps();

        $billId = request()->integer('bill_id');
        if ($billId <= 0) {
            return $props;
        }

        $bill = Bill::query()
            ->with('vendor:id,display_name,quickbooks_id')
            ->find($billId);

        if ($bill === null) {
            return $props;
        }

        $props['initialData'] = [
            'vendor_id' => $bill->vendor_id,
            'currency_code' => $bill->currency_code ?: 'USD',
            'lines' => [
                [
                    'bill_id' => $bill->id,
                    'bill' => $bill,
                    'amount' => $bill->balance,
                    'quickbooks_bill_id' => $bill->quickbooks_bill_id,
                ],
            ],
        ];

        return $props;
    }

    protected function indexSupplementInertiaProps(Request $request): array
    {
        return $this->quickbooksApSyncProps();
    }

    protected function quickBooksApSyncEntityLabel(): ?string
    {
        return 'bill payment';
    }

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return redirect()
            ->route('bill-payments.show', $id)
            ->with('success', 'Bill payment updated successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function quickbooksApSyncProps(): array
    {
        $settings = QuickBooksSettings::forCurrentTenant();
        $accounting = app(QuickBooksAccountingService::class);

        return [
            'quickbooksApSync' => [
                'enabled' => $accounting->isConnected() && $settings->isSyncBillPaymentsEnabled(),
                'entityLabel' => 'bill payment',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billPaymentEditRestrictionsProps(Model $record): array
    {
        if (! $record instanceof RecordModel || ! $record->isQuickbooksManaged()) {
            return [
                'editRestrictions' => [
                    'restricted' => false,
                    'allowedFields' => ['vendor_id'],
                    'reason' => null,
                ],
            ];
        }

        return [
            'editRestrictions' => [
                'restricted' => true,
                'allowedFields' => ['vendor_id'],
                'reason' => 'quickbooks',
            ],
        ];
    }

    public function pushToQuickbooks(Request $request, int $billPayment): RedirectResponse
    {
        $record = RecordModel::query()->findOrFail($billPayment);

        if ($record->quickbooks_bill_payment_id) {
            return back()->with('error', 'This bill payment is already linked to QuickBooks.');
        }

        if (! app(QuickBooksAccountingService::class)->isConnected()) {
            return back()->with('error', 'QuickBooks is not connected.');
        }

        try {
            PushBillPaymentToQuickBooks::runSync($record->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not sync bill payment to QuickBooks: '.$e->getMessage());
        }

        return back()->with('success', 'Bill payment synced to QuickBooks.');
    }

    public function pullFromQuickbooks(Request $request, int $billPayment): RedirectResponse
    {
        $record = RecordModel::query()->findOrFail($billPayment);

        if (! $record->quickbooks_bill_payment_id) {
            return back()->with('error', 'This bill payment is not linked to QuickBooks.');
        }

        $settings = QuickBooksSettings::forCurrentTenant();
        if (! $settings->isSyncBillPaymentsEnabled()) {
            return back()->with('error', 'Bill payment sync is not enabled. Turn it on under Integrations → QuickBooks.');
        }

        if (! app(QuickBooksAccountingService::class)->isConnected()) {
            return back()->with('error', 'QuickBooks is not connected.');
        }

        $profile = current_tenant_profile();
        if (! $profile) {
            return back()->with('error', 'Tenant user profile not found.');
        }

        PullBillPaymentsFromQuickBooks::dispatch((int) $profile->getKey());

        return back()->with('success', 'QuickBooks bill payment refresh queued.');
    }
}
