<?php

namespace App\Http\Controllers\Tenant;

use App\Services\ServiceYard\ServiceYardOverviewDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceYardController extends Controller
{
    public function index(Request $request, ServiceYardOverviewDataService $overview)
    {
        return Inertia::render('Tenant/ServiceYard/Index', $overview->build($request));
    }
}
