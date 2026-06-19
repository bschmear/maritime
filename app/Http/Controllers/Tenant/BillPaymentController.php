<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\BillPayment\Actions\CreateBillPayment as CreateAction;
use App\Domain\BillPayment\Actions\DeleteBillPayment as DeleteAction;
use App\Domain\BillPayment\Actions\UpdateBillPayment as UpdateAction;
use App\Domain\BillPayment\Models\BillPayment as RecordModel;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Jobs\PullBillPaymentsFromQuickBooks;
use App\Jobs\PushBillPaymentToQuickBooks;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    protected function showPageExtraProps($record): array
    {
        $accounting = app(QuickBooksAccountingService::class);

        return [
            'quickbooks' => [
                'connected' => $accounting->isConnected(),
                'sync_bill_payments_enabled' => QuickBooksSettings::forCurrentTenant()->isSyncBillPaymentsEnabled(),
            ],
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
        return 'bill payment';
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
