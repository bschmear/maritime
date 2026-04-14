<?php

namespace App\Domain\Customer\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\PortalAccess\Models\PortalAccess;
use App\Domain\Score\Models\Score;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Task\Models\Task;
use App\Domain\User\Models\User;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

class Customer extends Model
{
    use HasDocuments;

    protected $table = 'customer_profiles';

    /**
     * @var list<string>
     */
    protected $with = ['contact.primaryAddress'];

    /**
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
        'purchase_timeline' => 'string',
        'last_contacted_at' => 'date',
        'next_followup_at' => 'date',
        'has_trade_in' => 'boolean',
        'trade_in_value' => 'decimal:2',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'marketing_opt_in' => 'boolean',
        'latest_score' => 'decimal:2',
        'created_by_user_id' => 'integer',
        'last_updated_by_user_id' => 'integer',
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'lifetime_value' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'total_orders' => 'integer',
        'loyalty_points' => 'integer',
        'payment_terms' => 'integer',
        'tax_exempt' => 'boolean',
        'auto_renew' => 'boolean',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'first_purchase_at' => 'date',
        'last_purchase_at' => 'date',
        'subsidiary_id' => 'integer',
    ];

    /**
     * Default subsidiary for programmatic customer_profile creation (estimates, migrations, etc.).
     */
    public static function defaultSubsidiaryId(): ?int
    {
        return Subsidiary::query()
            ->where('inactive', false)
            ->orderBy('id')
            ->value('id')
            ?? Subsidiary::query()->orderBy('id')->value('id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subsidiary::class);
    }

    /**
     * Eager-load constraint for relations: display_name / email / phone live on contacts.
     *
     * Works with {@see Builder} and with relation instances passed from {@see Model::with()} (e.g. BelongsTo).
     *
     * @param  list<string>  $extraContactColumns
     * @return \Closure(Builder|Relation): mixed
     */
    public static function eagerWithContactSelect(array $extraContactColumns = []): \Closure
    {
        $contactCols = array_values(array_unique(array_merge(
            ['id', 'display_name', 'first_name', 'last_name'],
            $extraContactColumns
        )));

        return function (Builder|Relation $query) use ($contactCols) {
            $model = $query instanceof Builder
                ? $query->getModel()
                : $query->getRelated();

            $table = $model->getTable();

            return $query->select([$table.'.id', $table.'.contact_id'])
                ->with(['contact' => fn ($q) => $q->select($contactCols)]);
        };
    }

    /**
     * id + display_name sorted by contact name (pickers / enum options).
     *
     * @return Builder<Customer>
     */
    public static function queryOrderedByContactDisplayName(): Builder
    {
        $table = (new static)->getTable();

        return static::query()
            ->join('contacts', 'contacts.id', '=', $table.'.contact_id')
            ->select([$table.'.id', 'contacts.display_name'])
            ->orderBy('contacts.display_name');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class, 'contact_id', 'contact_id');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function leads()
    {
        return $this->hasMany(\App\Domain\Lead\Models\Lead::class, 'converted_customer_id');
    }

    public function converted_from_lead()
    {
        return $this->belongsTo(\App\Domain\Lead\Models\Lead::class, 'converted_from_lead_id');
    }

    public function asset_units()
    {
        return $this->hasMany(AssetUnit::class, 'customer_id');
    }

    public function assetUnits()
    {
        return $this->hasMany(AssetUnit::class, 'customer_id');
    }

    public function opportunities()
    {
        return $this->hasMany(\App\Domain\Opportunity\Models\Opportunity::class);
    }

    public function estimates()
    {
        return $this->hasMany(\App\Domain\Estimate\Models\Estimate::class);
    }

    public function invoices()
    {
        return $this->hasMany(\App\Domain\Invoice\Models\Invoice::class);
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->select('id', 'display_name');
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->select('id', 'display_name');
    }

    public function last_updated_by_user()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id')->select('id', 'display_name');
    }

    public function documents()
    {
        return $this->morphToMany(\App\Domain\Document\Models\Document::class, 'documentable')
            ->withTimestamps();
    }

    public function scores()
    {
        return $this->morphMany(Score::class, 'scorable');
    }

    public function currentScores()
    {
        return $this->morphMany(Score::class, 'scorable')->where('is_current', true);
    }

    public function latestScore()
    {
        return $this->belongsTo(Score::class, 'latest_score_id');
    }

    public function portalAccesses()
    {
        return $this->hasMany(PortalAccess::class);
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
            'notes',
            'inactive',
            // Set by CreateCustomer / UpdateCustomer from inactive; lives on contacts, not customer_profiles
            'status',
        ];
    }

    /**
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

        unset($profile['id'], $profile['created_at'], $profile['updated_at'], $profile['contact_id']);

        return [$contact, $address, $profile];
    }
}
