<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\UserFavorite\Models\UserFavorite;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;

class UserFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = current_tenant_user_id();
        if ($userId === null) {
            abort(403);
        }

        $favorites = UserFavorite::query()
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'label', 'route', 'route_params']);

        return response()->json(['data' => $favorites]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = current_tenant_user_id();
        if ($userId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'route' => ['required', 'string', 'max:255'],
            'route_params' => ['nullable', 'array'],
        ]);

        $routeName = $validated['route'];
        if (! RouteFacade::has($routeName)) {
            throw ValidationException::withMessages([
                'route' => ['The selected route is invalid.'],
            ]);
        }

        $routeParams = $this->normalizeRouteParams($validated['route_params'] ?? null);

        if ($this->favoriteExists($userId, $routeName, $routeParams)) {
            throw ValidationException::withMessages([
                'route' => ['This page is already in your favorites.'],
            ]);
        }

        $favorite = UserFavorite::query()->create([
            'user_id' => $userId,
            'label' => $validated['label'],
            'route' => $routeName,
            'route_params' => $routeParams,
        ]);

        return response()->json(['data' => $favorite->only(['id', 'label', 'route', 'route_params'])], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = current_tenant_user_id();
        if ($userId === null) {
            abort(403);
        }

        $favorite = UserFavorite::query()->findOrFail($id);
        $this->authorizeFavorite($favorite, $userId);

        $favorite->delete();

        return response()->json(['success' => true]);
    }

    protected function authorizeFavorite(UserFavorite $favorite, int $userId): void
    {
        if ($favorite->user_id !== $userId) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @return array<string, mixed>|null
     */
    protected function normalizeRouteParams(?array $params): ?array
    {
        if ($params === null || $params === []) {
            return null;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>|null  $routeParams
     */
    protected function favoriteExists(int $userId, string $route, ?array $routeParams): bool
    {
        return UserFavorite::query()
            ->where('user_id', $userId)
            ->where('route', $route)
            ->where('route_params_hash', UserFavorite::hashRouteParams($routeParams))
            ->exists();
    }
}
