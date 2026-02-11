<?php

namespace App\Enums;

enum Timezone: string
{
    // US Timezones
    case Eastern     = 'America/New_York';
    case Central     = 'America/Chicago';
    case Mountain    = 'America/Denver';
    case Pacific     = 'America/Los_Angeles';
    case Alaska      = 'America/Anchorage';
    case Hawaii      = 'Pacific/Honolulu';

    // Common international timezones
    case GMT         = 'Etc/GMT';
    case UTC         = 'UTC';
    case London      = 'Europe/London';
    case Paris       = 'Europe/Paris';
    case Berlin      = 'Europe/Berlin';
    case Tokyo       = 'Asia/Tokyo';
    case Sydney      = 'Australia/Sydney';

    public function label(): string
    {
        return match($this) {
            self::Eastern   => 'Eastern - US & Canada',
            self::Central   => 'Central - US & Canada',
            self::Mountain  => 'Mountain - US & Canada',
            self::Pacific   => 'Pacific - US & Canada',
            self::Alaska    => 'Alaska',
            self::Hawaii    => 'Hawaii',
            self::GMT       => 'GMT',
            self::UTC       => 'UTC',
            self::London    => 'London',
            self::Paris     => 'Paris',
            self::Berlin    => 'Berlin',
            self::Tokyo     => 'Tokyo',
            self::Sydney    => 'Sydney',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
