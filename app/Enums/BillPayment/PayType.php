<?php

declare(strict_types=1);

namespace App\Enums\BillPayment;

/**
 * QuickBooks Online BillPayment.PayType ({@code BillPaymentTypeEnum}).
 *
 * @see https://static.developer.intuit.com/sdkdocs/qbv3doc/ipp-v3-java-devkit-javadoc/com/intuit/ipp/data/BillPaymentTypeEnum.html
 *
 * QBO defines Check and CreditCard. ACH is not a separate BillPayment PayType in the API;
 * bank-based ACH payments are sent as Check with {@code CheckPayment.BankAccountRef}.
 */
enum PayType: string
{
    case Check = 'Check';
    case CreditCard = 'CreditCard';
    case Ach = 'ACH';

    public function label(): string
    {
        return match ($this) {
            self::Check => 'Check',
            self::CreditCard => 'Credit card',
            self::Ach => 'ACH',
        };
    }

    /**
     * Value for QuickBooks BillPayment.PayType.
     */
    public function quickbooksValue(): string
    {
        return match ($this) {
            self::Ach => self::Check->value,
            default => $this->value,
        };
    }

    public function usesCreditCardAccount(): bool
    {
        return $this === self::CreditCard;
    }

    public function usesBankAccount(): bool
    {
        return $this === self::Check || $this === self::Ach;
    }

    public static function default(): self
    {
        return self::Check;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }

    public static function tryFromValue(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $direct = self::tryFrom($value);
        if ($direct !== null) {
            return $direct;
        }

        return match (strtolower(str_replace(['_', ' ', '-'], '', $value))) {
            'check' => self::Check,
            'creditcard' => self::CreditCard,
            'ach' => self::Ach,
            default => null,
        };
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFromValue($value) ?? self::default();
    }

    public static function fromQuickBooks(?string $payType): self
    {
        return self::tryFromValue($payType) ?? self::default();
    }
}
