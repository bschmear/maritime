<?php

namespace App\Domain\Vendor\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\Task\Models\Task;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Vendor extends Model
{
    use HasDocuments;

    /**
     * Eager-load primary contact for lists and {@see $appends} summary.
     *
     * @var list<string>
     */
    protected $with = [
        'primaryContact',
    ];

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
     * @var list<string>
     */
    protected $appends = [
        'primary_contact_summary',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Status and enums
        'status_id' => 'integer',
        'rating' => 'integer',
        'credit_limit' => 'decimal:2',
        'is_verified' => 'boolean',

        // Optional foreign keys
        'assigned_user_id' => 'integer',
        'primary_contact_id' => 'integer',

        // JSON fields
        'tags' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string, string>
     */
    protected $dates = [
        'last_contacted_at',
        'next_followup_at',
        'contract_start',
        'contract_end',
        'verified_at',
    ];

    /**
     * Table / API display for the linked primary contact (not a DB column).
     */
    protected function primaryContactSummary(): Attribute
    {
        return Attribute::make(
            get: function () {
                $c = $this->primaryContact;
                if (! $c) {
                    return null;
                }

                return $c->display_name
                    ?: trim(($c->first_name ?? '').' '.($c->last_name ?? ''))
                    ?: $c->email
                    ?: ($c->company ?? null);
            }
        );
    }

    /**
     * Assigned user relationship.
     */
    public function assigned_user()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assigned_user_id')->select('id', 'display_name');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_vendor')
            ->withPivot('is_primary');
    }

    /**
     * Keep {@see primary_contact_id} and pivot in sync: primary row is attached with {@code is_primary} true.
     */
    public function syncPrimaryContactPivot(): void
    {
        $vendorId = (int) $this->id;
        $primaryId = $this->primary_contact_id !== null ? (int) $this->primary_contact_id : null;

        DB::table('contact_vendor')
            ->where('vendor_id', $vendorId)
            ->update(['is_primary' => false]);

        if ($primaryId === null) {
            return;
        }

        $affected = DB::table('contact_vendor')
            ->where('vendor_id', $vendorId)
            ->where('contact_id', $primaryId)
            ->update(['is_primary' => true]);

        if ($affected === 0) {
            DB::table('contact_vendor')->insert([
                'vendor_id' => $vendorId,
                'contact_id' => $primaryId,
                'is_primary' => true,
            ]);
        }
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }

    /**
     * Tasks related to this vendor.
     */
    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function communications()
    {
        return $this->morphMany(\App\Domain\Communication\Models\Communication::class, 'communicable')
            ->orderByDesc('created_at');
    }
}
