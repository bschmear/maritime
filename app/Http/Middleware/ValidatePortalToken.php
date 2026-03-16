<?php

namespace App\Http\Middleware;

use App\Domain\PortalAccess\Models\PortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePortalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        $access = PortalAccess::where('token', $token)->first();

        if (!$access) {
            abort(404, 'Portal link not found.');
        }

        if (!$access->isValid()) {
            $message = $access->isRevoked()
                ? 'This portal link has been revoked.'
                : 'This portal link has expired.';

            abort(403, $message);
        }

        $access->markUsed();

        $record = $this->resolveRecord($access);

        $request->merge([
            'portal_access' => $access,
            'portal_record' => $record,
        ]);

        return $next($request);
    }

    protected function resolveRecord(PortalAccess $access): mixed
    {
        return match ($access->record_type) {
            'estimate' => \App\Domain\Estimate\Models\Estimate::findOrFail($access->record_id),
            'invoice' => \App\Domain\Invoice\Models\Invoice::findOrFail($access->record_id),
            'service_ticket' => \App\Domain\ServiceTicket\Models\ServiceTicket::findOrFail($access->record_id),
            'delivery' => \App\Domain\Delivery\Models\Delivery::findOrFail($access->record_id),
            default => abort(404, 'Unknown record type.'),
        };
    }
}
