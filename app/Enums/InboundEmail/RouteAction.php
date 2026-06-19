<?php

namespace App\Enums\InboundEmail;

enum RouteAction: string
{
    case CreateLead = 'create_lead';

    public function label(): string
    {
        return match ($this) {
            self::CreateLead => 'Create lead',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
