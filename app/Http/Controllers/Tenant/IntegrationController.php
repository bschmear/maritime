<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $centralUser = auth()->user();
        $profile = current_tenant_profile();

        $integrationData = IntegrationType::options();

        $activeIntegrations = Integration::query()
            ->select(['id', 'user_id', 'integration_type', 'active', 'last_synced_at'])
            ->get();

        $breadcrumbs = [
            'current' => 'Integrations',
            'links' => [
                ['url' => route('dashboard'), 'name' => 'Dashboard'],
            ],
        ];

        return Inertia::render('Tenant/Integrations/Index', [
            'breadcrumbs' => $breadcrumbs,
            'centralUser' => $centralUser ? [
                'id' => $centralUser->id,
                'name' => $centralUser->name ?? trim(($centralUser->first_name ?? '').' '.($centralUser->last_name ?? '')),
                'email' => $centralUser->email,
            ] : null,
            'tenantProfile' => $profile ? [
                'id' => $profile->id,
                'display_name' => $profile->display_name ?? $profile->email,
            ] : null,
            'integrations' => $integrationData,
            'activeIntegrations' => $activeIntegrations,
        ]);
    }
}
