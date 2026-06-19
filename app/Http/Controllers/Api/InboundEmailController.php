<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InboundEmail\InboundEmailReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    public function __invoke(Request $request, InboundEmailReceiver $receiver): JsonResponse
    {
        $receiver->receive($request->all());

        return response()->json(['status' => 'accepted'], 200);
    }
}
