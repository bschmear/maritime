<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShow\Support\BoatShowWordPressPayload;
use App\Http\Controllers\Controller;
use App\Support\TenantAbsoluteUrl;
use Illuminate\Http\JsonResponse;

class WordPressBoatShowController extends Controller
{
    public function status(): JsonResponse
    {
        $tenant = tenant();
        $profile = current_tenant_profile();

        return response()->json([
            'status' => 'ok',
            'tenant_id' => $tenant?->getTenantKey(),
            'tenant_name' => $profile?->display_name ?? $profile?->email,
            'tenant_url' => TenantAbsoluteUrl::root(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(BoatShowWordPressPayload::all());
    }

    public function show(string $uuid): JsonResponse
    {
        $show = BoatShow::query()->where('uuid', $uuid)->firstOrFail();

        return response()->json(BoatShowWordPressPayload::forShowWithEvents($show));
    }
}
