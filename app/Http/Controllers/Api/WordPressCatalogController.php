<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Asset\Support\AssetWordPressPayload;
use App\Domain\BoatMake\Support\BoatMakeWordPressPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class WordPressCatalogController extends Controller
{
    public function brands(): JsonResponse
    {
        return response()->json(BoatMakeWordPressPayload::all());
    }

    public function inventory(): JsonResponse
    {
        return response()->json(AssetWordPressPayload::all());
    }
}
