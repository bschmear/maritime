<?php

namespace App\Domain\Lead\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Customer\Models\Customer;
use App\Domain\Qualification\Models\Qualification;
use App\Domain\Task\Models\Task;
use App\Domain\User\Models\User;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasDocuments;

    protected $table = 'lead_profiles';

    /**
     * Eager-load contact + primary address for flat accessors and forms.
     *
     * @var list<string>
     */
    protected $with = ['contact.primaryAddress'];

    /**
     * Virtual attributes merged for Inertia / table rows (backed by contact or primary address).
     *
     * @var list<string>
     */
    protected $appends = [
        'display_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'company',
        'position',
        'title',
        'secondary_email',
        'website',
        'linkedin',
        'facebook',
        'notes',
        'inactive',
        'preferred_contact_method',
        'preferred_contact_time',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'status_id' => 'integer',
        'source_id' => 'integer',
        'priority_id' => 'integer',
        'budget_range' => 'integer',
        'purchase_timeline' => 'string',
        'converted_customer_id' => 'integer',
        'converted' => 'boolean',
        'converted_at' => 'datetime',
        'is_qualified' => 'boolean',
        'qualified_at' => 'date',
        'last_contacted_at' => 'date',
        'next_followup_at' => 'date',
        'has_trade_in' => 'boolean',
        'trade_in_value' => 'decimal:2',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'marketing_opt_in' => 'boolean',
        'opted_in_at' => 'datetime',
        'latest_score' => 'decimal:2',
        'created_by_user_id' => 'integer',
        'last_updated_by_user_id' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Addresses for this lead's contact (same contact_id as lead_profiles.contact_id).
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class, 'contact_id', 'contact_id');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function qualitifications()
    {
        return $this->hasMany(Qualification::class, 'lead_id');
    }

    /**
     * Sales / CRM assignment for this lead (source of truth for “assigned rep”).
     * The related contact may mirror the same user on `contacts.assigned_user_id` for filters and legacy forms.
     */
    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id')
            ->select('id', 'display_name');
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by_user_id')
            ->select('id', 'display_name');
    }

    public function last_updated_by_user()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id')
            ->select('id', 'display_name');
    }

    public function converted_customer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function scores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable');
    }

    public function currentScores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable')->where('is_current', true);
    }

    public function latestScore()
    {
        return $this->belongsTo(\App\Domain\Score\Models\Score::class, 'latest_score_id');
    }

    public function communications()
    {
        return $this->morphMany(\App\Domain\Communication\Models\Communication::class, 'communicable')
            ->orderByDesc('created_at');
    }

    protected function primaryAddressRow(): ?ContactAddress
    {
        $contact = $this->relationLoaded('contact') ? $this->contact : $this->contact()->first();
        if (! $contact) {
            return null;
        }

        if ($contact->relationLoaded('primaryAddress')) {
            return $contact->primaryAddress;
        }

        return $contact->primaryAddress()->first();
    }

    public function getDisplayNameAttribute(): ?string
    {
        return $this->contact?->display_name;
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->contact?->first_name;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->contact?->last_name;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->contact?->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->contact?->phone;
    }

    public function getMobileAttribute(): ?string
    {
        return $this->contact?->mobile;
    }

    public function getCompanyAttribute(): ?string
    {
        return $this->contact?->company;
    }

    public function getPositionAttribute(): ?string
    {
        return $this->contact?->position;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->contact?->title;
    }

    public function getSecondaryEmailAttribute(): ?string
    {
        return $this->contact?->secondary_email;
    }

    public function getWebsiteAttribute(): ?string
    {
        return $this->contact?->website;
    }

    public function getLinkedinAttribute(): ?string
    {
        return $this->contact?->linkedin;
    }

    public function getFacebookAttribute(): ?string
    {
        return $this->contact?->facebook;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->contact?->notes;
    }

    public function getInactiveAttribute(): bool
    {
        return (bool) ($this->contact?->inactive);
    }

    public function getPreferredContactMethodAttribute(): mixed
    {
        return $this->contact?->preferred_contact_method;
    }

    public function getPreferredContactTimeAttribute(): mixed
    {
        return $this->contact?->preferred_contact_time;
    }

    public function getAddressLine1Attribute(): ?string
    {
        return $this->primaryAddressRow()?->address_line_1;
    }

    public function getAddressLine2Attribute(): ?string
    {
        return $this->primaryAddressRow()?->address_line_2;
    }

    public function getCityAttribute(): ?string
    {
        return $this->primaryAddressRow()?->city;
    }

    public function getStateAttribute(): ?string
    {
        return $this->primaryAddressRow()?->state;
    }

    public function getPostalCodeAttribute(): ?string
    {
        return $this->primaryAddressRow()?->postal_code;
    }

    public function getCountryAttribute(): ?string
    {
        return $this->primaryAddressRow()?->country;
    }

    public function getLatitudeAttribute(): ?string
    {
        $v = $this->primaryAddressRow()?->latitude;

        return $v === null ? null : (string) $v;
    }

    public function getLongitudeAttribute(): ?string
    {
        $v = $this->primaryAddressRow()?->longitude;

        return $v === null ? null : (string) $v;
    }

    /**
     * Keys stored on contacts (flat form / API).
     *
     * @return list<string>
     */
    public static function contactAttributeKeys(): array
    {
        return [
            'display_name',
            'first_name',
            'last_name',
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
            'website',
            'linkedin',
            'facebook',
            'notes',
            'inactive',
        ];
    }

    /**
     * Keys stored on contact_addresses (primary row).
     *
     * @return list<string>
     */
    public static function addressAttributeKeys(): array
    {
        return [
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'latitude',
            'longitude',
        ];
    }

    /**
     * Split a flat payload into contact, primary address, and lead profile columns.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    public static function splitPayload(array $data): array
    {
        $contact = array_intersect_key($data, array_flip(self::contactAttributeKeys()));
        $address = array_intersect_key($data, array_flip(self::addressAttributeKeys()));
        $profile = $data;

        foreach (array_merge(self::contactAttributeKeys(), self::addressAttributeKeys()) as $k) {
            unset($profile[$k]);
        }

        unset($profile['id'], $profile['created_at'], $profile['updated_at']);

        return [$contact, $address, $profile];
    }
}
