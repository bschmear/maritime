<?php

namespace App\Domain\ConsignmentPolicy\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentPolicy extends Model
{
    protected $table = 'consignment_policies';

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Idempotent starter bullets for new tenants (see ConsignmentPolicySeeder).
     *
     * @return list<string>
     */
    public static function defaultBodies(): array
    {
        return [
            'Boats will only be taken in if space is available.',
            'The consignor represents that they hold clear title to the property and have authority to enter this agreement.',
            'The consignor agrees to maintain adequate insurance on the property until it is sold or returned.',
            'The dealer may display, advertise, and show the property to prospective buyers during normal business hours.',
            'Pricing, minimum sale amounts, and listing duration are as stated on this agreement unless modified in writing by both parties.',
            'The consignment fee will be deducted from sale proceeds at the rate shown on this agreement.',
        ];
    }

    public static function ensureDefaultsExist(): void
    {
        foreach (static::defaultBodies() as $index => $body) {
            static::query()->firstOrCreate(
                ['body' => $body],
                [
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
