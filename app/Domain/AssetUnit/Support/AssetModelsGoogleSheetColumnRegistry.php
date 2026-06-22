<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;

final class AssetModelsGoogleSheetColumnRegistry
{
    public const HEADER_MAKE = 'Make';

    public const HEADER_MODEL = 'Model';

    public const HEADER_VARIANT = 'Variant';

    public const HEADER_MODEL_YEAR = 'Model Year';

    public const HEADER_HULL_TYPE = 'Hull Type';

    public const HEADER_HULL_MATERIAL = 'Hull Material';

    public const HEADER_BOAT_TYPE = 'Boat Type';

    public const HEADER_LENGTH = 'Length';

    public const HEADER_WIDTH = 'Width';

    public function __construct(
        private readonly GoogleSheetSpecSupport $specs = new GoogleSheetSpecSupport,
    ) {}

    /**
     * @return list<string>
     */
    public function baseHeaders(): array
    {
        return [
            self::HEADER_MAKE,
            self::HEADER_MODEL,
            self::HEADER_VARIANT,
            self::HEADER_MODEL_YEAR,
            self::HEADER_HULL_TYPE,
            self::HEADER_HULL_MATERIAL,
            self::HEADER_BOAT_TYPE,
            self::HEADER_LENGTH,
            self::HEADER_WIDTH,
        ];
    }

    /**
     * @return list<string>
     */
    public function allHeaders(): array
    {
        return array_merge($this->baseHeaders(), $this->specs->specHeaders());
    }

    /**
     * @return list<AssetSpecDefinition>
     */
    public function specDefinitions(): array
    {
        return $this->specs->specDefinitions();
    }
}
