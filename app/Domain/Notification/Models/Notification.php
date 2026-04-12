<?php

namespace App\Domain\Notification\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
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
     * Get the route parameters for URL generation
     */
    public function getRouteParameters(): array
    {
        // Handle different formats of route_params
        if (is_array($this->route_params)) {
            return $this->route_params;
        }

        // If route_params is a scalar value, treat it as a single ID parameter
        // For service tickets, the parameter name is 'serviceticket'
        if (is_scalar($this->route_params) && ! empty($this->route_params)) {
            return ['serviceticket' => $this->route_params];
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
}
