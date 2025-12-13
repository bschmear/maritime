<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use Inertia\Inertia;

class OperationsController extends Controller
{
    public function index(Request $request)
    {
        $recordsSections = [
            [
                'title' => 'Transactions',
                'description' => 'View and manage all financial transactions, including payments, refunds, and applied invoice activity.',
                'icon' => 'M9 10V6a3 3 0 0 1 3-3v0a3 3 0 0 1 3 3v4m3-2 .917 11.923A1 1 0 0 1 17.92 21H6.08a1 1 0 0 1-.997-1.077L6 8h12Z',
                'href' => route('transactions.index'),
                'color' => 'blue',
                'stats' => null,
            ],
            [
                'title' => 'Inventory',
                'description' => 'Manage inventory items and units. Track quantities, serialized units, pricing, and availability.',
                'icon' => 'M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z',
                'href' => route('inventoryitems.index'),
                'color' => 'green',
                'stats' => null,
            ],
            [
                'title' => 'Invoices',
                'description' => 'Create, send, and track invoices. Monitor payment status, due dates, and outstanding balances.',
                'icon' => 'M10 3v4a1 1 0 0 1-1 1H5m8-2h3m-3 3h3m-4 3v6m4-3H8M19 4v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1ZM8 12v6h8v-6H8Z',
                'href' => route('invoices.index'),
                'color' => 'purple',
                'stats' => null,
            ],
        ];

        return Inertia::render('Tenant/Operations/Index', [
            'recordsSections' => $recordsSections,
        ]);
    }
}
