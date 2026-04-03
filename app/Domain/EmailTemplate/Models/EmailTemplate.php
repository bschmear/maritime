<?php

declare(strict_types=1);

namespace App\Domain\EmailTemplate\Models;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    public const TYPE_BOAT_SHOW_FOLLOWUP = 'boat_show_event_followup';

    protected $table = 'email_templates';

    protected $fillable = [
        'type',
        'recipients',
        'email_subject',
        'email_message',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function boatShowEvents(): HasMany
    {
        return $this->hasMany(BoatShowEvent::class, 'email_template_id');
    }

    public function scopeFollowUpTemplates($query)
    {
        return $query->where('type', self::TYPE_BOAT_SHOW_FOLLOWUP);
    }

    public static function ensureBoatShowFollowUpSingleton(): self
    {
        $defaults = self::getDefaultTemplate();

        return self::query()->firstOrCreate(
            ['type' => self::TYPE_BOAT_SHOW_FOLLOWUP],
            [
                'email_subject' => $defaults['email_subject'],
                'email_message' => $defaults['email_message'],
                'is_active' => true,
                'recipients' => null,
            ]
        );
    }

    /**
     * @return array<string, string> token => label
     */
    public static function getAvailableVariables(): array
    {
        return [
            '{{ lead_name }}' => 'Lead full name',
            '{{ lead_email }}' => 'Lead email',
            '{{ event_name }}' => 'Boat show event name',
            '{{ event_venue }}' => 'Event venue',
            '{{ boat_show_name }}' => 'Parent boat show name',
            '{{ dealer_name }}' => 'Dealership / account name',
            '{{ salesperson_name }}' => 'Salesperson name',
            '{{ today }}' => "Today's date",
            '{{ selected_asset_list }}' => 'Assets the lead selected (HTML list)',
        ];
    }

    /**
     * @return array{email_subject: string, email_message: string}
     */
    public static function getDefaultTemplate(): array
    {
        return [
            'email_subject' => 'Thanks for visiting {{ event_name }}',
            'email_message' => '<p>Hi {{ lead_name }},</p>'
                .'<p>Thank you for stopping by our booth at <strong>{{ event_name }}</strong> at {{ event_venue }}. We appreciate your interest.</p>'
                .'<p>Here are the units you asked about:</p>'
                .'{{ selected_asset_list }}'
                .'<p>If you would like a sea trial, more photos, or pricing on any of these boats (or similar inventory), reply to this email or call us anytime.</p>'
                .'<p>Best regards,<br>'
                .'{{ salesperson_name }}<br>'
                .'{{ dealer_name }}</p>',
        ];
    }
}
