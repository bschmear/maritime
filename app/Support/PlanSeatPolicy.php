<?php

namespace App\Support;

final class PlanSeatPolicy
{
    /**
     * @return array{included: int, extra_monthly_price: float}
     */
    public static function forMarketing(): array
    {
        return [
            'included' => (int) config('app.included_seats', 5),
            'extra_monthly_price' => (float) config('app.extra_seats.monthly_price', 15),
        ];
    }
}
