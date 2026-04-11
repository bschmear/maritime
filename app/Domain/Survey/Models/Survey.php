<?php

namespace App\Domain\Survey\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Survey extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
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

    protected static function booted(): void
    {
        static::creating(function (Survey $survey) {
            $survey->uuid = (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function getPublicUrl(?int $agentId = null): string
    {
        $url = route('surveysPublicShow', ['id' => $this->uuid], absolute: true);
        if ($agentId) {
            $url .= (str_contains($url, '?') ? '&' : '?').'aid='.$agentId;
        }

        return $url;
    }

    public function getEmbedUrl(): string
    {
        return route('surveysPublicEmbed', ['id' => $this->uuid], absolute: true);
    }

    public function getEffectiveColor(): string
    {
        if ($this->color_scheme === 'custom' && $this->custom_color) {
            return $this->custom_color;
        }

        return config('app.app_brand', '#0d9488');
    }
}
