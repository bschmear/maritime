<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use Exception;

final class DeliveryFleetConflictException extends Exception
{
    /**
     * @param  list<array<string, mixed>>  $conflicts
     */
    public function __construct(
        string $message,
        public readonly array $conflicts = []
    ) {
        parent::__construct($message);
    }
}
