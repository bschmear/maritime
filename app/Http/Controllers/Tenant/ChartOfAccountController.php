<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Bill\Models\Bill;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Http\Controllers\Controller;
use App\Services\Payments\QuickBooksAccountingService;
use App\Support\ChartOfAccount\ChartOfAccountTreeBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $accountType = trim($request->string('account_type')->toString()) ?: null;
        $activeFilter = $request->query('active');

        $active = match ($activeFilter) {
            '1', 1, true => true,
            '0', 0, false => false,
            default => null,
        };

        $accounts = ChartOfAccount::query()
            ->select([
                'id',
                'parent_id',
                'name',
                'fully_qualified_name',
                'account_type',
                'detail_type',
                'quickbooks_account_id',
                'active',
            ])
            ->orderBy('fully_qualified_name')
            ->orderBy('name')
            ->get();

        $accountTree = ChartOfAccountTreeBuilder::build(
            $accounts,
            $search !== '' ? $search : null,
            $accountType,
            $active,
        );

        $accountTypes = ChartOfAccount::query()
            ->whereNotNull('account_type')
            ->where('account_type', '!=', '')
            ->distinct()
            ->orderBy('account_type')
            ->pluck('account_type')
            ->values();

        return Inertia::render('Tenant/ChartOfAccount/Index', [
            'accountTree' => $accountTree,
            'filters' => [
                'search' => $search,
                'account_type' => $accountType,
                'active' => $activeFilter === null ? null : (string) $activeFilter,
            ],
            'accountTypes' => $accountTypes,
            'stats' => [
                'total' => $accounts->count(),
                'active' => $accounts->where('active', true)->count(),
                'roots' => $accounts->whereNull('parent_id')->count(),
            ],
            'pluralTitle' => 'Chart of accounts',
            'recordType' => 'chart-of-accounts',
            'recordTitle' => 'Chart of account',
        ]);
    }

    public function show(ChartOfAccount $chartOfAccount): Response
    {
        $record = ChartOfAccount::query()
            ->with([
                'parent:id,name,fully_qualified_name,quickbooks_account_id,account_type',
                'children' => fn ($query) => $query
                    ->select([
                        'id',
                        'parent_id',
                        'name',
                        'fully_qualified_name',
                        'account_type',
                        'detail_type',
                        'quickbooks_account_id',
                        'active',
                    ])
                    ->orderBy('fully_qualified_name')
                    ->orderBy('name'),
                'bills' => fn ($query) => $query
                    ->select([
                        'id',
                        'sequence',
                        'vendor_id',
                        'txn_date',
                        'due_date',
                        'total_amt',
                        'balance',
                        'status',
                        'chart_of_account_id',
                    ])
                    ->with(['vendor:id,display_name'])
                    ->latest('txn_date')
                    ->latest('id')
                    ->limit(25),
                'billItems' => fn ($query) => $query
                    ->select([
                        'id',
                        'bill_id',
                        'description',
                        'amount',
                        'chart_of_account_id',
                    ])
                    ->with([
                        'bill:id,sequence,vendor_id,txn_date',
                        'bill.vendor:id,display_name',
                    ])
                    ->latest('id')
                    ->limit(25),
            ])
            ->findOrFail($chartOfAccount->id);

        $apBills = collect();
        if (filled($record->quickbooks_account_id)) {
            $apBills = Bill::query()
                ->select([
                    'id',
                    'sequence',
                    'vendor_id',
                    'txn_date',
                    'due_date',
                    'total_amt',
                    'balance',
                    'status',
                    'ap_account_ref_id',
                    'ap_account_ref_name',
                ])
                ->with(['vendor:id,display_name'])
                ->where('ap_account_ref_id', $record->quickbooks_account_id)
                ->latest('txn_date')
                ->latest('id')
                ->limit(25)
                ->get();
        }

        $accounting = app(QuickBooksAccountingService::class);

        return Inertia::render('Tenant/ChartOfAccount/Show', [
            'record' => $record,
            'recordType' => 'chart-of-accounts',
            'recordTitle' => 'Chart of account',
            'apBills' => $apBills,
            'quickbooks' => [
                'connected' => $accounting->isConnected(),
            ],
        ]);
    }
}
