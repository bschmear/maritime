<?php

namespace App\Domain\Subsidiary\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasDocuments;
use App\Domain\Document\Models\Document;
use Illuminate\Support\Facades\Storage;
use App\Enums\Timezone;

class Subsidiary extends Model
{
    use HasDocuments;
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'inactive'                 => 'boolean',
        'latitude'                 => 'decimal:7',
        'longitude'                => 'decimal:7',
        'default_labor_rate'       => 'decimal:2',
        'next_work_order_number'   => 'integer',
        'settings'                 => 'array',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = ['logo_url', 'full_address'];

    public function locations()
    {
        return $this->belongsToMany(
            \App\Domain\Location\Models\Location::class,
            'location_subsidiary'
        )->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(
            \App\Domain\User\Models\User::class,
            'subsidiary_user'
        )->withPivot(['primary'])
        ->withTimestamps();
    }

    /**
     * Accessor for logo URL. Resolves through the Document record.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        $document = Document::find($this->logo);
        if (!$document || !$document->file) {
            return null;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . $document->file;
        }

        return Storage::disk('s3')->temporaryUrl(
            $document->file,
            now()->addDays(7)
        );
    }

    /**
     * Accessor for full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ];

        return implode(', ', array_filter($parts));
    }

    /**
     * Accessor for timezone label.
     */
    public function getTimezoneLabelAttribute(): ?string
    {
        if ($this->timezone && Timezone::tryFrom($this->timezone)) {
            return Timezone::from($this->timezone)->label();
        }

        return null;
    }
}
