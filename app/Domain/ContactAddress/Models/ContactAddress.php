<?php

declare(strict_types=1);

namespace App\Domain\ContactAddress\Models;

/**
 * Domain wrapper for schema/routes; table and fillable match {@see \App\Domain\Contact\Models\ContactAddress}.
 */
class ContactAddress extends \App\Domain\Contact\Models\ContactAddress
{
    protected $table = 'contact_addresses';

    protected $appends = [
        'display_name',
    ];

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->city,
            $this->state,
        ]);
        $line = implode(', ', $parts);

        return $line !== '' && $line !== '0' ? $line : 'Address #'.$this->id;
    }
}
