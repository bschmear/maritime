<?php

namespace App\Domain\Survey\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class PrivacySettingsCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $settings = is_array($value) ? $value : json_decode($value, true);

        if (! is_array($settings)) {
            return null;
        }

        if (! array_key_exists('require_email', $settings) && ! empty($settings['require_identity'])) {
            $settings['require_email'] = (bool) $settings['require_identity'];
        }

        return $settings;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return is_string($value) ? $value : json_encode($value);
        }

        if (! empty($value['require_identity']) && empty($value['require_email'])) {
            $value['require_email'] = (bool) $value['require_identity'];
        }

        unset($value['require_identity']);

        return json_encode($value);
    }
}
