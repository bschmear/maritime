<?php

namespace App\Enums\WorkOrder;

enum Type: string
{
    case Service       = 'service';
    case Repair        = 'repair';
    case Maintenance   = 'maintenance';
    case Inspection    = 'inspection';
    case Warranty      = 'warranty';
    case Install       = 'install';
    case Diagnostic    = 'diagnostic';
    case Custom        = 'custom';

    public function id(): int
    {
        return match ($this) {
            self::Service     => 1,
            self::Repair      => 2,
            self::Maintenance => 3,
            self::Inspection  => 4,
            self::Warranty    => 5,
            self::Install     => 6,
            self::Diagnostic  => 7,
            self::Custom      => 8,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Service     => 'Service',
            self::Repair      => 'Repair',
            self::Maintenance => 'Maintenance',
            self::Inspection  => 'Inspection',
            self::Warranty    => 'Warranty',
            self::Install     => 'Install',
            self::Diagnostic  => 'Diagnostic',
            self::Custom      => 'Custom',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Service     => 'General service work',
            self::Repair      => 'Fixing a reported issue or failure',
            self::Maintenance => 'Routine or preventative maintenance',
            self::Inspection  => 'Condition or compliance inspection',
            self::Warranty    => 'Manufacturer or dealer warranty work',
            self::Install     => 'New equipment or accessory installation',
            self::Diagnostic  => 'Troubleshooting or diagnostics only',
            self::Custom      => 'Non-standard or one-off work',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => [
                'id'          => $case->id(),
                'value'       => $case->value,
                'name'        => $case->label(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }
}
