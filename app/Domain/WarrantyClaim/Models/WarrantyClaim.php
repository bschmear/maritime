<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Models;

use App\Domain\Attachment\Concerns\HasLinkedInventoryImages;
use App\Domain\Contact\Models\Contact;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\WarrantyClaim\Status;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarrantyClaim extends Model
{
    use HasDocuments;
    use HasLinkedInventoryImages;

    protected $table = 'warrantyclaims';

    protected $guarded = ['id'];

    protected $appends = ['display_name'];

    protected $fillable = [
        'vendor_id',
        'work_order_id',
        'subsidiary_id',
        'location_id',
        'created_by_user_id',
        'status',
        'total_amount',
        'submitted_at',
        'approved_at',
        'paid_at',
        'voided_at',
        'rejection_reason',
        'notes',
        'approved_by_vendor',
        'vendor_approved_at',
        'vendor_approved_by_contact_id',
        'vendor_notes',
        'vendor_rejected_at',
        'vendor_rejected_by_contact_id',
    ];

    protected $casts = [
        'status' => Status::class,
        'total_amount' => 'decimal:2',
        'subsidiary_id' => 'integer',
        'location_id' => 'integer',
        'created_by_user_id' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'approved_by_vendor' => 'boolean',
        'vendor_approved_at' => 'datetime',
        'vendor_approved_by_contact_id' => 'integer',
        'vendor_rejected_at' => 'datetime',
        'vendor_rejected_by_contact_id' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (WarrantyClaim $record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('warrantyclaims')->max('sequence') ?? 999);
                $record->sequence = $next + 1;
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subsidiary::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function vendorApprovedByContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_approved_by_contact_id');
    }

    public function vendorRejectedByContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_rejected_by_contact_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(WarrantyClaimLineItem::class)->orderBy('id');
    }

    public function getDisplayNameAttribute()
    {
        return 'WCL-'.($this->sequence ?: $this->id ?: '???');
    }
}
