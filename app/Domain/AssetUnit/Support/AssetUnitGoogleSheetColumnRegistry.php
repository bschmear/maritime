<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

final class AssetUnitGoogleSheetColumnRegistry
{
    public const HEADER_MAKE = 'Make';

    public const HEADER_ASSET_MODEL = 'Model';

    public const HEADER_VARIANT = 'Variant';

    public const HEADER_STATUS = 'Status';

    public const HEADER_CONDITION = 'Condition';

    public const HEADER_HIN = 'HID';

    public const HEADER_SERIAL = 'Serial ID';

    public const HEADER_UNIT_YEAR = 'Unit Year';

    public const HEADER_COST = 'Cost';

    public const HEADER_ASKING_PRICE = 'Asking Price';

    public const HEADER_LOCATION = 'Location';

    public const HEADER_SUBSIDIARY = 'Subsidiary';

    /**
     * @return list<string>
     */
    public function baseHeaders(): array
    {
        return [
            self::HEADER_MAKE,
            self::HEADER_ASSET_MODEL,
            self::HEADER_VARIANT,
            self::HEADER_STATUS,
            self::HEADER_CONDITION,
            self::HEADER_HIN,
            self::HEADER_SERIAL,
            self::HEADER_UNIT_YEAR,
            self::HEADER_COST,
            self::HEADER_ASKING_PRICE,
            self::HEADER_LOCATION,
            self::HEADER_SUBSIDIARY,
        ];
    }

    /**
     * @return list<string>
     */
    public function allHeaders(): array
    {
        return $this->baseHeaders();
    }

    /**
     * @return list<string>
     */
    public function statusLabels(): array
    {
        return GoogleSheetEnumLabels::statusLabels();
    }

    /**
     * @return list<string>
     */
    public function conditionLabels(): array
    {
        return GoogleSheetEnumLabels::conditionLabels();
    }
}
