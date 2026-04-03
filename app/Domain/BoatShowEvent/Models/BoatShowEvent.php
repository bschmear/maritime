<?php

namespace App\Domain\BoatShowEvent\Models;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\BoatShowEvent\Support\EventAssetsPayload;
use App\Domain\BoatShowLayout\Models\BoatShowLayout;
use App\Domain\Checklist\Models\Checklist;
use App\Domain\EmailTemplate\Models\EmailTemplate;
use App\Domain\Survey\Models\SurveyResponse;
use App\Domain\Task\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BoatShowEvent extends Model
{
    use SoftDeletes;

    protected $table = 'boat_show_events';

    protected $fillable = [
        'boat_show_id',
        'display_name',
        'year',
        'starts_at',
        'ends_at',
        'venue',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'booth',
        'active',
        'meta',
        'auto_followup',
        'delay_amount',
        'delay_unit',
        'recipients',
        'email_template_id',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'active' => 'boolean',
        'meta' => 'array',
        'auto_followup' => 'boolean',
        'delay_amount' => 'integer',
        'recipients' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if ($record->email_template_id === null) {
                $record->email_template_id = EmailTemplate::query()
                    ->followUpTemplates()
                    ->value('id');
            }
        });
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(BoatShow::class, 'boat_show_id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(BoatShowLayout::class, 'boat_show_event_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(BoatShowLead::class, 'boat_show_event_id');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function checklist(): MorphOne
    {
        return $this->morphOne(Checklist::class, 'checklistable');
    }

    public function eventAssets(): HasMany
    {
        return $this->hasMany(BoatShowEventAsset::class, 'boat_show_event_id');
    }

    public function followUpEmailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function surveyResponses(): MorphMany
    {
        return $this->morphMany(SurveyResponse::class, 'sourceable');
    }

    /**
     * @return array{boats: array, engines: array, trailers: array}
     */
    public function assetsGroupedForInertia(): array
    {
        return EventAssetsPayload::grouped($this);
    }
}
