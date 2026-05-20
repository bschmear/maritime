<?php

declare(strict_types=1);

namespace App\Domain\Note\Support;

use App\Domain\Qualification\Models\Qualification;
use Illuminate\Database\Eloquent\Model;

final class NotifiableTypeResolver
{
    /**
     * @return list<string>
     */
    public static function allowedShortNames(): array
    {
        return [
            'Qualification',
        ];
    }

    /**
     * @return class-string<Model>|null
     */
    public static function toClass(string $type): ?string
    {
        return match ($type) {
            'Qualification', Qualification::class => Qualification::class,
            default => null,
        };
    }
}
