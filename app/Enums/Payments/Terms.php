<?php

namespace App\Enums\Payments;

enum Terms: string
{
    case DueOnReceipt = 'due_on_receipt';
    case Net15 = 'net_15';
    case Net30 = 'net_30';
    case Net60 = 'net_60';
    case Deposit50 = 'deposit_50';
    case Custom = 'custom';

    public function id(): int
    {
        return match ($this) {
            self::DueOnReceipt => 1,
            self::Net15 => 2,
            self::Net30 => 3,
            self::Net60 => 4,
            self::Deposit50 => 5,
            self::Custom => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DueOnReceipt => 'Due on Receipt',
            self::Net15 => 'Net 15',
            self::Net30 => 'Net 30',
            self::Net60 => 'Net 60',
            self::Deposit50 => '50% Deposit Required',
            self::Custom => 'Custom Terms',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DueOnReceipt => 'Payment due immediately upon invoice',
            self::Net15 => 'Payment due within 15 days',
            self::Net30 => 'Payment due within 30 days',
            self::Net60 => 'Payment due within 60 days',
            self::Deposit50 => '50% upfront, remaining balance due before delivery',
            self::Custom => 'Custom payment terms defined in contract',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }

    /**
     * Resolve a stored invoice/contract payment_term value to a Terms case.
     */
    public static function fromStored(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return self::DueOnReceipt;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            foreach (self::cases() as $case) {
                if ($case->id() === $id) {
                    return $case;
                }
            }
        }

        $normalized = strtolower(trim((string) $value));
        if (str_contains($normalized, '::')) {
            $normalized = trim(explode('::', $normalized, 2)[0], "'\" \t\n\r\0\x0B");
        }

        return self::tryFrom($normalized) ?? self::DueOnReceipt;
    }

    /**
     * QuickBooks Online Term.Name values to try (per company), in priority order.
     * IDs are resolved at runtime via {@see \App\Services\Payments\QuickBooksTermsService}.
     *
     * @return list<string>
     */
    public function quickbooksTermNames(): array
    {
        return match ($this) {
            self::DueOnReceipt => ['Due on receipt', 'Due on Receipt'],
            self::Net15 => ['Net 15'],
            self::Net30 => ['Net 30'],
            self::Net60 => ['Net 60'],
            self::Deposit50 => ['50% Deposit Required', '50% Deposit', 'Net 30'],
            self::Custom => [],
        };
    }
}
