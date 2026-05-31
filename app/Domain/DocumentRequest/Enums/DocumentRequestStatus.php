<?php

declare(strict_types=1);

namespace App\Domain\DocumentRequest\Enums;

final class DocumentRequestStatus
{
    public const Pending = 'pending';

    public const Fulfilled = 'fulfilled';

    public const Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Pending,
            self::Fulfilled,
            self::Cancelled,
        ];
    }
}
