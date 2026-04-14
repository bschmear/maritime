<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Tenant/ServiceYard/Scheduling');
    }
}
