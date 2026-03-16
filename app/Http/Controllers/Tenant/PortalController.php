<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function show(Request $request): Response
    {
        $access = $request->get('portal_access');
        $record = $request->get('portal_record');

        return Inertia::render('Portal/TokenView', [
            'access' => [
                'uuid' => $access->uuid,
                'record_type' => $access->record_type,
                'customer_id' => $access->customer_id,
                'expires_at' => $access->expires_at?->toISOString(),
            ],
            'record' => $record,
            'recordType' => $access->record_type,
        ]);
    }
}
