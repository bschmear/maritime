<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\RadarAddressAutocompleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressAutocompleteController extends Controller
{
    public function __construct(
        private readonly RadarAddressAutocompleteService $autocomplete,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:200'],
            'country_code' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $addresses = $this->autocomplete->search(
            $validated['query'],
            $validated['country_code'] ?? null,
            (int) ($validated['limit'] ?? 10),
        );

        return response()->json(['data' => $addresses]);
    }
}
