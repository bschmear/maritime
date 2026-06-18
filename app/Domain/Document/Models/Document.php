<?php

namespace App\Domain\Document\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\InventoryUnit\Models\InventoryUnit;
use App\Domain\Lead\Models\Lead;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $table = 'documents';

    protected $fillable = [
        'display_name',
        'description',
        'file',
        'file_extension',
        'file_size',
        'created_by_id',
        'updated_by_id',
        'assigned_id',
        'extracted_text',
        'ai_summary',
        'key_points',
        'ai_status',
        'ai_processed_at',
    ];

    protected $casts = [
        'key_points' => 'array',
        'ai_processed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Clean up file when document is deleted
        static::deleting(function ($document) {
            if ($document->file && Storage::disk('s3')->exists($document->file)) {
                Storage::disk('s3')->delete($document->file);
            }
        });
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function assigned()
    {
        return $this->belongsTo(User::class, 'assigned_id');
    }

    public function contacts()
    {
        return $this->morphedByMany(Contact::class, 'documentable')->withTimestamps();
    }

    public function transactions()
    {
        return $this->morphedByMany(Transaction::class, 'documentable')->withTimestamps();
    }

    public function vendors()
    {
        return $this->morphedByMany(Vendor::class, 'documentable')->withTimestamps();
    }

    public function leads()
    {
        return $this->morphedByMany(Lead::class, 'documentable')->withTimestamps();
    }

    public function warrantyClaims()
    {
        return $this->morphedByMany(WarrantyClaim::class, 'documentable')->withTimestamps();
    }

    public function serviceTickets()
    {
        return $this->morphedByMany(ServiceTicket::class, 'documentable')->withTimestamps();
    }

    public function deliveries()
    {
        return $this->morphedByMany(Delivery::class, 'documentable')->withTimestamps();
    }

    public function inventory_items()
    {
        return $this->morphedByMany(InventoryItem::class, 'documentable')->withTimestamps()->withPivot('sort_order', 'role');
    }

    public function inventory_units()
    {
        return $this->morphedByMany(InventoryUnit::class, 'documentable')->withTimestamps()->withPivot('sort_order', 'role');
    }

    /**
     * Get parsed key points as array.
     */
    public function getKeyPointsArrayAttribute(): ?array
    {
        if (! $this->key_points) {
            return null;
        }

        $decoded = json_decode($this->key_points, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Check if document has been analyzed.
     */
    public function hasAnalysis(): bool
    {
        return $this->ai_status === 'completed' && ! empty($this->ai_summary);
    }
}
