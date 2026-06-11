<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Domain\Location\Models\Location;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolved subsidiary / location scope for the tenant dashboard.
 */
final class DashboardFilters
{
    public function __construct(
        public readonly ?int $subsidiaryId,
        public readonly ?int $locationId,
    ) {}

    public static function fromRequest(Request $request, ?User $user): self
    {
        if ($request->has('subsidiary_id') || $request->has('location_id')) {
            $subsidiaryId = $request->integer('subsidiary_id') ?: null;
            $locationId = $request->integer('location_id') ?: null;
        } else {
            $subsidiaryId = $user?->preferred_subsidiary_id ? (int) $user->preferred_subsidiary_id : null;
            $locationId = $user?->preferred_location_id ? (int) $user->preferred_location_id : null;
        }

        return self::validated($subsidiaryId, $locationId);
    }

    public static function validated(?int $subsidiaryId, ?int $locationId): self
    {
        if ($subsidiaryId !== null && $locationId !== null) {
            $linked = Location::query()
                ->whereKey($locationId)
                ->whereHas('subsidiaries', fn (Builder $q) => $q->where('subsidiaries.id', $subsidiaryId))
                ->exists();

            if (! $linked) {
                $locationId = null;
            }
        }

        return new self($subsidiaryId, $locationId);
    }

    public function isActive(): bool
    {
        return $this->subsidiaryId !== null || $this->locationId !== null;
    }

    /**
     * @return array{subsidiary_id: ?int, location_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'subsidiary_id' => $this->subsidiaryId,
            'location_id' => $this->locationId,
        ];
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDirectScope(Builder $query, ?string $table = null): void
    {
        if (! $this->isActive()) {
            return;
        }

        $prefix = $table !== null && $table !== '' ? "{$table}." : '';

        if ($this->subsidiaryId !== null) {
            $query->where("{$prefix}subsidiary_id", $this->subsidiaryId);
        }

        if ($this->locationId !== null) {
            $query->where("{$prefix}location_id", $this->locationId);
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyToPaymentQuery(Builder $query): void
    {
        if (! $this->isActive()) {
            return;
        }

        $query->whereHas('invoice', function (Builder $invoice) {
            $this->applyDirectScope($invoice);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyToOpportunityQuery(Builder $query): void
    {
        if ($this->subsidiaryId === null) {
            return;
        }

        $query->whereHas('customer', fn (Builder $customer) => $customer->where('subsidiary_id', $this->subsidiaryId));
    }
}
