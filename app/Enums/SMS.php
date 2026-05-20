<?php

namespace App\Enums;

/**
 * Domains / record areas that can emit transactional SMS when enabled in
 * {@see \App\Models\SmsNotificationPreference}.
 *
 * Additional categories (e.g. service tickets) can be added later.
 */
enum SMS: string
{
    case Estimate = 'estimate';
    case Invoice = 'invoice';
    case Delivery = 'delivery';

    /** Database column on `sms_notification_preferences` (e.g. notify_invoice). */
    public function notifyColumn(): string
    {
        return 'notify_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Estimate => 'Estimate',
            self::Invoice => 'Invoice',
            self::Delivery => 'Delivery',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Estimate => 'SMS related to estimates (e.g. approval requests).',
            self::Invoice => 'SMS related to invoices.',
            self::Delivery => 'SMS related to deliveries.',
        };
    }
}
