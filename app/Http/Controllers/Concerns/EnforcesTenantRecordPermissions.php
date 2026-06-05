<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\RecordType;
use Illuminate\Http\Request;

trait EnforcesTenantRecordPermissions
{
    protected function registerTenantRecordPermissionMiddleware(): void
    {
        $this->middleware(function (Request $request, $next) {
            $this->authorizeTenantRecordAccess($request);

            return $next($request);
        });
    }

    protected function authorizeTenantRecordAccess(Request $request): void
    {
        $domainName = $this->domainName ?? $this->recordType ?? null;
        if (! is_string($domainName) || $domainName === '') {
            return;
        }

        $recordType = RecordType::fromDomainName($domainName);
        if ($recordType === null) {
            return;
        }

        if (! $recordType->tenantUserCanAccess()) {
            abort(403);
        }

        $permissionAction = match (true) {
            $request->isMethod('GET'), $request->isMethod('HEAD') => 'view',
            $request->isMethod('POST') => 'create',
            $request->isMethod('PUT'), $request->isMethod('PATCH') => 'edit',
            $request->isMethod('DELETE') => 'delete',
            default => null,
        };

        if ($permissionAction === null) {
            return;
        }

        abort_unless(
            tenant_has_permission($recordType->key().'.'.$permissionAction),
            403
        );
    }
}
