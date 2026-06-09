<?php

namespace App\Domain\Notification\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class Notification extends Model
{
    /** @var array<string, string> */
    private const SCALAR_PARAM_BY_ROUTE = [
        'contracts.show' => 'contract',
        'servicetickets.show' => 'serviceticket',
        'estimates.show' => 'estimate',
        'deliveries.show' => 'delivery',
        'contacts.show' => 'contact',
        'warrantyclaims.show' => 'warrantyclaim',
        'opportunities.show' => 'opportunity',
        'tasks.show' => 'task',
        'workorders.show' => 'workorder',
    ];

    protected $fillable = [
        'assigned_to_user_id',
        'type',
        'title',
        'message',
        'route',
        'route_params',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'route_params' => 'array',
    ];

    /**
     * Resolve stored route_params for URL generation (named route + parameters).
     *
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        $params = $this->rawRouteParams();

        if (is_array($params) && $params !== []) {
            if (! array_is_list($params)) {
                return $params;
            }

            if (count($params) === 1 && is_scalar($params[0])) {
                return $this->wrapScalarForRoute($this->route, $params[0]);
            }

            return [];
        }

        if (is_scalar($params) && $params !== '' && $params !== null) {
            return $this->wrapScalarForRoute($this->route, $params);
        }

        return [];
    }

    /**
     * Decode route_params without the array cast dropping legacy scalar JSON values.
     */
    protected function rawRouteParams(): mixed
    {
        $raw = $this->getAttributes()['route_params'] ?? null;

        if ($raw === null) {
            return $this->route_params;
        }

        if (is_string($raw)) {
            return json_decode($raw, true);
        }

        return $raw;
    }

    /**
     * Map a legacy scalar ID to the named route's single parameter (e.g. contract, estimate).
     *
     * @return array<string, mixed>
     */
    protected function wrapScalarForRoute(?string $routeName, mixed $value): array
    {
        if ($routeName === null || $routeName === '') {
            return [];
        }

        if (isset(self::SCALAR_PARAM_BY_ROUTE[$routeName])) {
            return [self::SCALAR_PARAM_BY_ROUTE[$routeName] => $value];
        }

        if (Route::has($routeName)) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route !== null) {
                $names = $route->parameterNames();
                if (count($names) === 1) {
                    return [$names[0] => $value];
                }
            }
        }

        return [];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function markAsRead()
    {
        $this->update([
            'read_at' => now(),
        ]);
    }

    /**
     * Relative URL for in-app / push navigation (no host).
     */
    public static function relativeUrlForRoute(string $routeName, array $params = []): string
    {
        return route($routeName, $params, false);
    }
}
