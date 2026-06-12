<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Enums;

enum CatalogImportDuplicateStrategy: string
{
    case Skip = 'skip';
    case Overwrite = 'overwrite';

    public function overwritesDuplicates(): bool
    {
        return $this === self::Overwrite;
    }

    public static function fromRequest(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? $value : '') ?? self::Skip;
    }
}
