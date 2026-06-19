<?php

namespace App\Domain\BoatShowEvent\Support;

use Carbon\Carbon;

class BoatShowEventYear
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveFromDates(array $data): array
    {
        if (! empty($data['starts_at'])) {
            $data['year'] = (int) Carbon::parse($data['starts_at'])->year;
        } elseif (! empty($data['ends_at'])) {
            $data['year'] = (int) Carbon::parse($data['ends_at'])->year;
        }

        return $data;
    }
}
