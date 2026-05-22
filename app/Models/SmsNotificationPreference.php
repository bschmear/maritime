<?php

namespace App\Models;

use App\Enums\SMS;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsNotificationPreference extends Model
{
    protected $fillable = [
        'account_settings_id',
        'notify_estimate',
        'notify_invoice',
        'notify_delivery',
        'notify_contract',
    ];

    protected $casts = [
        'notify_estimate' => 'boolean',
        'notify_invoice' => 'boolean',
        'notify_delivery' => 'boolean',
        'notify_contract' => 'boolean',
    ];

    public function accountSettings(): BelongsTo
    {
        return $this->belongsTo(AccountSettings::class, 'account_settings_id');
    }

    /**
     * @return array<string, bool> keyed by SMS backed value (e.g. invoice => true)
     */
    public function toPreferenceMap(): array
    {
        $out = [];
        foreach (SMS::cases() as $case) {
            $out[$case->value] = (bool) $this->getAttribute($case->notifyColumn());
        }

        return $out;
    }
}
