<?php

namespace App\Domain\Contact\Models;

use App\Domain\Communication\Models\Communication;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Entity\ContactMethod;
use App\Enums\Entity\ContactStage;
use App\Enums\Entity\ContactStatus;
use App\Enums\Entity\ContactTimePreference;
use App\Enums\Entity\ContactType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Contact extends Authenticatable
{
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        // When a contact is deleted, clear it as primary contact on any vendor
        static::deleting(function (Contact $contact): void {
            Vendor::query()
                ->where('primary_contact_id', $contact->id)
                ->update(['primary_contact_id' => null]);
        });
    }

    protected $fillable = [
        'display_name',
        'first_name',
        'last_name',
        'type',
        'email',
        'secondary_email',
        'phone',
        'mobile',
        'company',
        'title',
        'position',
        'assigned_user_id',
        'preferred_contact_method',
        'preferred_contact_time',
        'source',
        'status',
        'stage_id',
        'inactive',
        'website',
        'linkedin',
        'facebook',
        'avatar',
        'notes',
        'stripe_customer_id',
        'quickbooks_customer_id',
    ];

    protected $casts = [
        'inactive' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /**
     * String-backed enums with numeric ids in forms: DB may store "1","2" or "email","phone".
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ContactType::tryFromStored($value),
            set: fn (mixed $value) => ['type' => ContactType::toStoredValue($value)],
        );
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ContactStatus::tryFromStored($value),
            set: fn (mixed $value) => ['status' => ContactStatus::toStoredValue($value)],
        );
    }

    protected function preferredContactMethod(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ContactMethod::tryFromStored($value),
            set: fn (mixed $value) => ['preferred_contact_method' => ContactMethod::toStoredValue($value)],
        );
    }

    protected function preferredContactTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ContactTimePreference::tryFromStored($value),
            set: fn (mixed $value) => ['preferred_contact_time' => ContactTimePreference::toStoredValue($value)],
        );
    }

    /**
     * {@code contacts.stage_id} is tinyint; app layer uses {@see ContactStage}.
     */
    protected function stageId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => $value !== null && $value !== '' ? ContactStage::tryFromStored($value) : null,
            set: fn (mixed $value) => ['stage_id' => ContactStage::toStoredId($value)],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'contact_vendor')
            ->withPivot('is_primary');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(ContactAddress::class)->where('is_primary', true);
    }

    /**
     * CRM lead rows on {@see Lead} (table {@code lead_profiles}) for this contact.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'contact_id');
    }

    /**
     * CRM customer row (customer_profiles) for this contact.
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'contact_id');
    }

    /**
     * Opportunities for customers that share this contact (contact → customer_profiles → opportunities).
     */
    public function opportunities(): HasManyThrough
    {
        return $this->hasManyThrough(
            Opportunity::class,
            Customer::class,
            'contact_id',
            'customer_id',
            'id',
            'id'
        );
    }

    // public function estimates(): HasMany
    // {
    //     return $this->hasMany(Estimate::class, 'contact_id');
    // }
    // public function invoices(): HasMany
    // {
    //     return $this->hasMany(Invoice::class, 'contact_id');
    // }

    /**
     * Communications where this contact is the morph parent.
     */
    public function communications(): MorphMany
    {
        return $this->morphMany(Communication::class, 'communicable');
    }

    public function leadProfile(): HasOne
    {
        return $this->hasOne(LeadProfile::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return $this->display_name
            ?? $this->full_name
            ?? $this->company
            ?? 'Unnamed Contact';
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPerson(): bool
    {
        return $this->type === ContactType::Person;
    }

    public function isCompany(): bool
    {
        return $this->type === ContactType::Company;
    }

    public function isActive(): bool
    {
        return $this->status?->isActive() ?? false;
    }

    public function hasEmail(): bool
    {
        return ! empty($this->email);
    }

    public function hasPhone(): bool
    {
        return ! empty($this->phone) || ! empty($this->mobile);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (VERY useful)
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', ContactStatus::Active);
    }

    public function scopePeople($query)
    {
        return $query->where('type', ContactType::Person);
    }

    public function scopeCompanies($query)
    {
        return $query->where('type', ContactType::Company);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Find a contact by primary or secondary email (case-insensitive).
     */
    public static function findByEmailCaseInsensitive(?string $email): ?self
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $normalized = strtolower(trim($email));

        $byPrimary = static::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->first();

        if ($byPrimary) {
            return $byPrimary;
        }

        return static::query()
            ->whereNotNull('secondary_email')
            ->where('secondary_email', '!=', '')
            ->whereRaw('LOWER(TRIM(secondary_email)) = ?', [$normalized])
            ->first();
    }

    public function hasPortalAccount(): bool
    {
        return ! is_null($this->password);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment-processor customer IDs (paired with email)
    |--------------------------------------------------------------------------
    |
    | {@code stripe_customer_id} and {@code quickbooks_customer_id} are external
    | IDs issued by those processors. They are tied to a Contact via email, so
    | use {@see resolveByEmailForProcessor()} to locate (or attach) them before
    | creating/paying against the matching remote customer record.
    */

    /**
     * Find a contact by email and return the current processor customer id (if any).
     *
     * @param  'stripe'|'quickbooks'  $processor
     * @return array{contact: ?self, customer_id: ?string}
     */
    public static function resolveByEmailForProcessor(?string $email, string $processor): array
    {
        $contact = self::findByEmailCaseInsensitive($email);
        if ($contact === null) {
            return ['contact' => null, 'customer_id' => null];
        }

        $column = match ($processor) {
            'stripe' => 'stripe_customer_id',
            'quickbooks' => 'quickbooks_customer_id',
            default => throw new \InvalidArgumentException("Unsupported processor: {$processor}"),
        };

        return [
            'contact' => $contact,
            'customer_id' => $contact->{$column} ?: null,
        ];
    }

    /**
     * Attach/refresh the processor customer id on this contact. No-op if already set
     * to the same value.
     *
     * @param  'stripe'|'quickbooks'  $processor
     */
    public function attachProcessorCustomerId(string $processor, string $customerId): void
    {
        $column = match ($processor) {
            'stripe' => 'stripe_customer_id',
            'quickbooks' => 'quickbooks_customer_id',
            default => throw new \InvalidArgumentException("Unsupported processor: {$processor}"),
        };

        if ($this->{$column} === $customerId) {
            return;
        }

        $this->{$column} = $customerId;
        $this->save();
    }

    public function scopeWithStripeCustomer($query)
    {
        return $query->whereNotNull('stripe_customer_id');
    }

    public function scopeWithQuickbooksCustomer($query)
    {
        return $query->whereNotNull('quickbooks_customer_id');
    }
}
