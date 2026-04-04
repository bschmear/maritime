<?php

namespace App\Services;

class SpecificationNormalizer
{
    public function normalize(array $specs): array
    {
        return [
            'length' => $this->length($specs['length'] ?? null),
            'width' => $this->length($specs['width'] ?? null),
            'height' => $this->length($specs['height'] ?? null),
            'weight' => $this->weight($specs['weight'] ?? null),
            'capacity_persons' => $this->capacity($specs['capacity_persons'] ?? null),
            'max_hp' => $this->hp($specs['max_hp'] ?? null),
            'fuel_capacity' => $this->gallons($specs['fuel_capacity'] ?? null),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LENGTH (feet + inches)
    |--------------------------------------------------------------------------
    | Accepts:
    | - 21' 8"
    | - 21 ft 8 in
    | - 21.5 ft
    | - 260 inches
    */
    public function length($value): ?string
    {
        if (!$value) return null;

        $value = strtolower(trim($value));

        // inches only
        if (preg_match('/(\d+)\s*(in|inch|inches)/', $value, $m)) {
            $inches = (int)$m[1];
            $feet = intdiv($inches, 12);
            $rem = $inches % 12;
            return "{$feet}' {$rem}\"";
        }

        // feet + inches
        if (preg_match('/(\d+)[\'\s]*(\d+)?\s*(\"|in)?/', $value, $m)) {
            $feet = (int)$m[1];
            $inches = isset($m[2]) ? (int)$m[2] : 0;

            return "{$feet}' {$inches}\"";
        }

        // decimal feet (21.5 ft)
        if (preg_match('/(\d+(\.\d+)?)\s*(ft|feet)/', $value, $m)) {
            $feetFloat = (float)$m[1];
            $feet = floor($feetFloat);
            $inches = round(($feetFloat - $feet) * 12);

            return "{$feet}' {$inches}\"";
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | WEIGHT (lbs)
    |--------------------------------------------------------------------------
    | Accepts:
    | - 2,300 lbs
    | - 2300lb
    | - 1.2 tons
    */
    public function weight($value): ?string
    {
        if (!$value) return null;

        $value = strtolower($value);

        // tons → lbs
        if (preg_match('/(\d+(\.\d+)?)\s*(ton|tons)/', $value, $m)) {
            $lbs = (float)$m[1] * 2000;
            return number_format($lbs) . ' lbs';
        }

        // lbs
        if (preg_match('/([\d,]+)/', $value, $m)) {
            $num = str_replace(',', '', $m[1]);
            return number_format((int)$num) . ' lbs';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | HORSEPOWER (hp)
    |--------------------------------------------------------------------------
    */
    public function hp($value): ?string
    {
        if (!$value) return null;

        if (preg_match('/(\d+)/', $value, $m)) {
            return (int)$m[1] . 'hp';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | FUEL (gallons)
    |--------------------------------------------------------------------------
    | Accepts:
    | - 40 gal
    | - 150 liters
    */
    public function gallons($value): ?string
    {
        if (!$value) return null;

        $value = strtolower($value);

        // liters → gallons
        if (preg_match('/(\d+(\.\d+)?)\s*(l|liter|liters)/', $value, $m)) {
            $gal = (float)$m[1] * 0.264172;
            return round($gal) . ' gal';
        }

        if (preg_match('/(\d+)/', $value, $m)) {
            return (int)$m[1] . ' gal';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | CAPACITY (int)
    |--------------------------------------------------------------------------
    | Accepts:
    | - 10 persons
    | - 8 people
    */
    public function capacity($value): ?int
    {
        if ($value === null) return null;

        if (is_numeric($value)) {
            return (int)$value;
        }

        if (preg_match('/(\d+)/', $value, $m)) {
            return (int)$m[1];
        }

        return null;
    }
}