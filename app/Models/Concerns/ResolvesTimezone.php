<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

trait ResolvesTimezone
{
    /**
     * Resolve the timezone for this model.
     *
     * Priority:
     * 1. Model's location timezone
     * 2. Account settings timezone
     * 3. UTC fallback
     */
    public function resolveTimezone(): string
    {
        if (
            method_exists($this, 'location')
            && $this->relationLoaded('location')
            && $this->location?->timezone
        ) {
            return $this->location->timezone;
        }

        if (function_exists('account_settings')) {
            return account_settings()->timezone ?? 'UTC';
        }

        return 'UTC';
    }

    /**
     * Convert a UTC datetime to the resolved timezone.
     */
    public function inResolvedTimezone(
        DateTimeInterface|string|null $value,
        ?string $format = null
    ): Carbon|null {
        if (! $value) {
            return null;
        }

        $carbon = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'UTC');

        $carbon = $carbon->setTimezone($this->resolveTimezone());

        return $format
            ? Carbon::createFromFormat('Y-m-d H:i:s', $carbon->format('Y-m-d H:i:s'))
                ->setTimezone($this->resolveTimezone())
            : $carbon;
    }

    /**
     * Convert a local datetime (from UI) into UTC for storage.
     */
    public function toUtc(
        DateTimeInterface|string|null $value
    ): Carbon|null {
        if (! $value) {
            return null;
        }

        $timezone = $this->resolveTimezone();

        return $value instanceof DateTimeInterface
            ? Carbon::instance($value)->setTimezone('UTC')
            : Carbon::parse($value, $timezone)->utc();
    }
}
