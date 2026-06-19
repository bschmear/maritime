<?php

namespace App\Domain\Vendor\Models;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillPayment\Models\BillPayment;
use App\Domain\Communication\Models\Communication;
use App\Domain\Contact\Models\Contact;
use App\Domain\Task\Models\Task;
use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'ach_account_number_masked',
        'ach_routing_number_masked',
        'tax_identifier_masked',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'ach_account_number',
        'ach_routing_number',
        'tax_identifier',
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
        'open_balance' => 'decimal:2',
        'overdue_balance' => 'decimal:2',
        'is_verified' => 'boolean',
        'vendor_1099' => 'boolean',
        'qbo_active' => 'boolean',
        'ach_account_number' => 'encrypted',
        'ach_routing_number' => 'encrypted',
        'tax_identifier' => 'encrypted',

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

    protected function achAccountNumberMasked(): Attribute
    {
        return Attribute::make(
            get: fn () => self::maskSensitiveValue($this->ach_account_number),
        );
    }

    protected function achRoutingNumberMasked(): Attribute
    {
        return Attribute::make(
            get: fn () => self::maskSensitiveValue($this->ach_routing_number),
        );
    }

    protected function taxIdentifierMasked(): Attribute
    {
        return Attribute::make(
            get: fn () => self::maskSensitiveValue($this->tax_identifier),
        );
    }

    public static function maskSensitiveValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', $length - 4).substr($value, -4);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'vendor_id')->orderByDesc('txn_date');
    }

    public function billPayments(): HasMany
    {
        return $this->hasMany(BillPayment::class, 'vendor_id')->orderByDesc('txn_date');
    }

    public function refreshOverdueBalanceFromBills(): void
    {
        if (! $this->id) {
            return;
        }

        $overdue = (float) Bill::query()
            ->where('vendor_id', $this->id)
            ->where('status', 'overdue')
            ->sum('balance');

        if ((float) $this->overdue_balance === $overdue) {
            return;
        }

        $this->overdue_balance = $overdue;
        $this->saveQuietly();
    }

    public static function refreshAllOverdueBalances(): void
    {
        static::query()->each(function (self $vendor): void {
            $vendor->refreshOverdueBalanceFromBills();
        });
    }

    /**
     * Assigned user relationship.
     */
    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id')
            ->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'office_phone', 'mobile_phone', 'current_role']);
    }

    /**
     * Contacts linked via {@code contact_vendor} pivot.
     * Named {@code linkedContacts} because the {@code vendors} table has a legacy JSON {@code contacts} column
     * that would shadow a {@code contacts()} relationship when accessed as a property.
     */
    public function linkedContacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_vendor')
            ->withPivot(['is_primary', 'portal_access']);
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class, 'vendor_id')->orderByDesc('updated_at');
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
                'portal_access' => false,
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
        return $this->morphMany(Communication::class, 'communicable')
            ->orderByDesc('created_at');
    }
}
