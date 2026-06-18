<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class TaxJurisdictionRate extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'country_code',
        'state_code',
        'postal_code',
        'city',
        'county_name',
        'jurisdiction_code',
        'jurisdiction_label',
        'state_rate_percent',
        'local_rate_percent',
        'total_rate_percent',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'state_rate_percent' => 'float',
        'local_rate_percent' => 'float',
        'total_rate_percent' => 'float',
        'fetched_at' => 'datetime',
    ];

    public function isStale(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if ($this->fetched_at === null) {
            return true;
        }

        return $this->fetched_at->lt($now->copy()->startOfMonth());
    }

    /**
     * @return array{tax_rate: float, tax_rate_decimal: float, jurisdiction_code: string|null, jurisdiction_label: string|null}
     */
    public function toLookupResult(): array
    {
        $total = (float) $this->total_rate_percent;

        return [
            'tax_rate' => round($total, 4),
            'tax_rate_decimal' => round($total / 100, 6),
            'jurisdiction_code' => $this->jurisdiction_code,
            'jurisdiction_label' => $this->jurisdiction_label,
        ];
    }
}
