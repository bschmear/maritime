<?php

declare(strict_types=1);

namespace App\Domain\ServiceTicket\Support;

use Illuminate\Support\Arr;

/**
 * Whitelist mass-assignable service ticket fields for staff create/update.
 */
final class ServiceTicketMassAssignment
{
    /**
     * @var list<string>
     */
    private const ALLOWED = [
        'customer_id',
        'subsidiary_id',
        'location_id',
        'transaction_id',
        'asset_unit_id',
        'expedite',
        'pickup_delivery_requested_at',
        'status',
        'type',
        'approved',
        'repair_description',
        'internal_notes',
        'estimated_labor_hours',
        'estimated_labor_amount',
        'estimated_parts_amount',
        'tax_rate',
        'revised_estimated_total',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filter(array $data): array
    {
        return Arr::only($data, self::ALLOWED);
    }
}
