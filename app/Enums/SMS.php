<?php

namespace App\Enums;

use App\Models\SmsNotificationPreference;

/**
 * Domains / record areas that can emit transactional SMS when enabled in
 * {@see SmsNotificationPreference}.
 */
enum SMS: string
{
    case Estimate = 'estimate';
    case Invoice = 'invoice';
    case Delivery = 'delivery';
    case Contract = 'contract';
    case ServiceTicket = 'service_ticket';

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
            self::Contract => 'Contract',
            self::ServiceTicket => 'Service ticket',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Estimate => 'SMS related to estimates (e.g. approval requests).',
            self::Invoice => 'SMS related to invoices.',
            self::Delivery => 'SMS related to deliveries.',
            self::Contract => 'SMS when sending a contract for review and signature.',
            self::ServiceTicket => 'SMS when sending a service ticket for customer approval.',
        };
    }
}
