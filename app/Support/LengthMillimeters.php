<?php

namespace App\Support;

/**
 * Store linear dimensions in millimetres. Display in imperial (ft/in) or metric
 * is handled by the UI based on account preferences.
 */
final class LengthMillimeters
{
    public const MM_PER_INCH = 25.4;

    public const MM_PER_FOOT = 304.8; // 12 * 25.4

    public static function toFeetFloat(?int $mm): ?float
    {
        if ($mm === null) {
            return null;
        }
        if ($mm < 0) {
            return null;
        }

        return $mm / self::MM_PER_FOOT;
    }

    public static function fromImperial(int $feet, int $inches): int
    {
        $inches = max(0, min(11, $inches));
        $feet = max(0, $feet);

        return (int) round((($feet * 12) + $inches) * self::MM_PER_INCH);
    }

    /**
     * Convert a legacy string from the pre-mm columns (e.g. "24", "8'6\"") to millimetres, or null if unknown.
     */
    public static function fromLegacyString(null|int|float|string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        if (is_numeric($s) && ! str_contains($s, "'") && ! str_contains($s, '"')) {
            $ft = (float) $s;

            return (int) round($ft * self::MM_PER_FOOT);
        }
        if (preg_match("/^(\d+)\s*'\s*(\d+)\s*\"?$/i", $s, $m)) {
            return self::fromImperial((int) $m[1], (int) $m[2]);
        }
        if (preg_match("/^(\d+)\s*'\s*(\d*)\s*$/i", $s, $m)) {
            $in = $m[2] !== '' ? (int) $m[2] : 0;

            return self::fromImperial((int) $m[1], $in);
        }
        if (preg_match('/[\d.]+/', $s, $m)) {
            return (int) round((float) $m[0] * self::MM_PER_FOOT);
        }

        return null;
    }
}
