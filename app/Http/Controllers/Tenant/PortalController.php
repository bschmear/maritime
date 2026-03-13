<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\PortalAccess\Models\PortalAccess;

class PortalController extends Controller
{
    /**
     * Customer portal entry point
     */
    public function index(Request $request, $token)
    {
        $access = PortalAccess::where('token', $token)
            ->whereNull('revoked_at')
            ->firstOrFail();

        if ($access->expires_at && now()->gt($access->expires_at)) {
            abort(403, 'Portal link expired');
        }

        // Track usage
        $access->update([
            'last_used_at' => now()
        ]);

        // Load the underlying record
        $record = match ($access->record_type) {
            'estimate' => \App\Domain\Estimate\Models\Estimate::findOrFail($access->record_id),
            'contract' => \App\Domain\Contract\Models\Contract::findOrFail($access->record_id),
            'invoice' => \App\Domain\Invoice\Models\Invoice::findOrFail($access->record_id),
            'delivery' => \App\Domain\Delivery\Models\Delivery::findOrFail($access->record_id),
            default => abort(404)
        };

        return view('portal.dashboard', [
            'access' => $access,
            'record' => $record
        ]);
    }
}
