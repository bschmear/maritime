<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Scopes\TeamScope;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'title',
        'description',
        'public_description',
        'slug',
        'type',
        'visibility',
        'status',
        'delivery_method',
        'automation_trigger',
        'automation_config',
        'thank_you_message',
        'redirect_url',
        'privacy_settings',
        'color_scheme',
        'custom_color',
    ];

    protected $casts = [
        'status' => 'boolean',
        'automation_config' => 'array',
        'privacy_settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TeamScope());
    }

    protected static function booted()
    {
        static::creating(function ($survey) {
            $survey->uuid = (string) Str::uuid();
            // if (empty($survey->slug)) {
            //     $survey->slug = Str::slug($survey->title) . '-' . uniqid();
            // }
        });
    }

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class);
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function followups()
    {
        return $this->hasMany(SurveyFollowup::class);
    }

    public function getSurveyResponse(): string
    {
        return config('app.crm_url') . '/surveys/survey/responses?id=' . $this->uuid;
    }

    public function getPublicUrl($agentId = null): string
    {
        $url = config('app.url') . '/survey/view?id=' . $this->uuid;
        if ($agentId) {
            $url .= '&aid=' . $agentId;
        }
        return $url;
    }

    public function getEmbedUrl(): string
    {
        return config('app.url') . '/survey/embed?id=' . $this->uuid;
    }

    public function surveysEdit(): string
    {
        return config('app.crm_url') . '/surveys/edit?id=' . $this->uuid;
    }

    public function surveysShow(): string
    {
        return config('app.crm_url') . '/surveys/survey?id=' . $this->uuid;
    }

    public function getEffectiveColor(): string
    {
        if ($this->color_scheme === 'custom' && $this->custom_color) {
            return $this->custom_color;
        } elseif ($this->color_scheme === 'team' && $this->team && isset($this->team->team_color)) {
            return $this->team->team_color;
        }

        // Default brand color
        return config('app.app_brand');
    }

}
