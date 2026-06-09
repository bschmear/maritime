<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Notification\Models\PushSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function status(): JsonResponse
    {
        $userId = current_tenant_user_id();

        if ($userId === null) {
            abort(403);
        }

        return response()->json([
            'subscribed' => PushSubscription::query()->where('user_id', $userId)->exists(),
            'server_enabled' => (bool) config('webpush.enabled'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        if (! config('webpush.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Web push is not configured on this server.',
            ], 503);
        }

        $userId = current_tenant_user_id();

        if ($userId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'endpoint' => 'required|url|max:2000',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:500',
            'keys.auth' => 'required|string|max:500',
            'content_encoding' => 'nullable|string|max:50',
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $userId,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aesgcm',
                'user_agent' => $request->userAgent(),
            ],
        );

        return response()->json(['success' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $userId = current_tenant_user_id();

        if ($userId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'endpoint' => 'required|url|max:2000',
        ]);

        PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
