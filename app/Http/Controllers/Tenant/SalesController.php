<?php

namespace App\Http\Controllers\Tenant;

use App\Services\Sales\SalesOverviewDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesController extends Controller
{
    public function __construct(
        private SalesOverviewDataService $salesOverview,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Tenant/Sales/Index', $this->salesOverview->build($request));
    }

    public function flow(Request $request)
    {
        return Inertia::render('Tenant/Sales/Flow');
    }
}
