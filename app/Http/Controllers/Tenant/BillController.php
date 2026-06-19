<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Bill\Actions\CreateBill as CreateAction;
use App\Domain\Bill\Actions\DeleteBill as DeleteAction;
use App\Domain\Bill\Actions\PayBill;
use App\Domain\Bill\Actions\UpdateBill as UpdateAction;
use App\Domain\Bill\Models\Bill as RecordModel;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Enums\Bill\Status as BillStatus;
use App\Jobs\PullSingleBillFromQuickBooks;
use App\Jobs\PushBillToQuickBooks;
use App\Services\Payments\QuickBooksAccountingService;
use App\Support\QuickBooks\QuickBooksChartOfAccountResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class BillController extends RecordController
{
    protected $recordType = 'Bill';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'bills',
            'Bill',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType,
        );
    }

    protected function appendShowRelationships(array &$relationships): void
    {
        $relationships['items'] = fn ($query) => $query
            ->with(['chartOfAccount:id,name,fully_qualified_name,quickbooks_account_id'])
            ->orderBy('position')
            ->orderBy('id');

        $relationships['billPaymentLines'] = fn ($query) => $query
            ->with(['billPayment:id,sequence,txn_date,total_amt,pay_type,doc_number,quickbooks_bill_payment_id'])
            ->orderBy('id');
    }

    protected function hydrateRecordAfterLoad(Model $record): void
    {
        parent::hydrateRecordAfterLoad($record);

        if (! $record instanceof RecordModel) {
            return;
        }

        $apAccount = QuickBooksChartOfAccountResolver::resolveSummaryByQuickbooksAccountId(
            $record->ap_account_ref_id,
        );

        if ($apAccount !== null) {
            $record->setAttribute('ap_chart_of_account', $apAccount);
            $record->setAttribute('apChartOfAccount', $apAccount);
        }
    }

    protected function editPageExtraProps($record): array
    {
        return array_merge(
            $this->quickbooksApSyncProps(),
            $this->billEditRestrictionsProps($record),
        );
    }

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return Redirect::route('bills.show', $id)
            ->with('success', 'Bill updated successfully');
    }

    protected function showPageExtraProps($record): array
    {
        $accounting = app(QuickBooksAccountingService::class);

        return [
            'quickbooks' => [
                'connected' => $accounting->isConnected(),
                'sync_bills_enabled' => QuickBooksSettings::forCurrentTenant()->isSyncBillsEnabled(),
                'sync_bill_payments_enabled' => QuickBooksSettings::forCurrentTenant()->isSyncBillPaymentsEnabled(),
            ],
            ...$this->billEditRestrictionsProps($record),
        ];
    }

    protected function createPageExtraProps(): array
    {
        return $this->quickbooksApSyncProps();
    }

    protected function indexSupplementInertiaProps(Request $request): array
    {
        return $this->quickbooksApSyncProps();
    }

    protected function quickBooksApSyncEntityLabel(): ?string
    {
        return 'bill';
    }

    protected function indexTableStats(Request $request, $query, ?array $schema): array
    {
        if (! is_array($schema) || empty($schema['stats']) || ! is_array($schema['stats'])) {
            return [];
        }

        $out = [];
        foreach ($schema['stats'] as $def) {
            $key = $def['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $scope = $def['scope'] ?? $key;
            if (! is_string($scope) || $scope === '') {
                $scope = $key;
            }

            $scoped = clone $query;
            $this->applyBillStatScope($scoped, $scope);
            $out[$key] = $scoped->count();
        }

        return $out;
    }

    /**
     * @param  Builder  $query
     */
    private function applyBillStatScope($query, string $scope): void
    {
        match ($scope) {
            'open' => $query->where('status', BillStatus::Open->value),
            'overdue' => $query->where('status', BillStatus::Overdue->value),
            'paid', 'paid_30d' => $query->where('status', BillStatus::Paid->value)
                ->where('updated_at', '>=', now()->subDays(30)),
            default => $query->whereRaw('0 = 1'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function billEditRestrictionsProps(RecordModel $record): array
    {
        return [
            'editRestrictions' => [
                'restricted' => $record->hasRestrictedEditing(),
                'allowedFields' => RecordModel::RESTRICTED_EDIT_ALLOWED_FIELDS,
                'reason' => $record->isPaid()
                    ? 'paid'
                    : ($record->isQuickbooksManaged() ? 'quickbooks' : null),
            ],
        ];
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
                'enabled' => $accounting->isConnected() && $settings->isSyncBillsEnabled(),
                'entityLabel' => 'bill',
            ],
        ];
    }

    public function pushToQuickbooks(Request $request, int $bill): RedirectResponse
    {
        $record = RecordModel::query()->findOrFail($bill);

        if ($record->quickbooks_bill_id) {
            return back()->with('error', 'This bill is already linked to QuickBooks.');
        }

        if (! app(QuickBooksAccountingService::class)->isConnected()) {
            return back()->with('error', 'QuickBooks is not connected.');
        }

        try {
            PushBillToQuickBooks::runSync($record->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not sync bill to QuickBooks: '.$e->getMessage());
        }

        return back()->with('success', 'Bill synced to QuickBooks.');
    }

    public function payBill(Request $request, int $bill): RedirectResponse
    {
        $record = RecordModel::query()->findOrFail($bill);

        try {
            $result = app(PayBill::class)($record);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            return back()->with('error', is_string($message) ? $message : 'Could not pay bill.');
        }

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return back()->with('error', $result['message'] ?? 'Could not pay bill.');
        }

        $payment = $result['record'];

        return redirect()
            ->route('bill-payments.show', $payment->id)
            ->with('success', 'Bill payment recorded.');
    }

    public function pullFromQuickbooks(Request $request, int $bill): RedirectResponse
    {
        $record = RecordModel::query()->findOrFail($bill);

        if (! $record->quickbooks_bill_id) {
            return back()->with('error', 'This bill is not linked to QuickBooks.');
        }

        $settings = QuickBooksSettings::forCurrentTenant();
        if (! $settings->isSyncBillsEnabled()) {
            return back()->with('error', 'Bill sync is not enabled. Turn it on under Integrations → QuickBooks.');
        }

        if (! app(QuickBooksAccountingService::class)->isConnected()) {
            return back()->with('error', 'QuickBooks is not connected.');
        }

        $profile = current_tenant_profile();
        if (! $profile) {
            return back()->with('error', 'Tenant user profile not found.');
        }

        PullSingleBillFromQuickBooks::dispatch((int) $profile->getKey(), (int) $record->id);

        return back()->with('success', 'QuickBooks bill refresh queued. This record will update shortly.');
    }
}
