<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Carbon\Carbon;

final class MsoValueResolver
{
    /**
     * @return array<string, string>
     */
    public static function prefillMap(MsoRecord $record, ?User $assignedUser = null): array
    {
        $details = MsoRecordDetails::normalize($record->details);
        $snapshot = is_array($details['snapshot'] ?? null) ? $details['snapshot'] : [];
        $transaction = is_array($snapshot['transaction'] ?? null) ? $snapshot['transaction'] : [];
        $lineItem = is_array($snapshot['line_item'] ?? null) ? $snapshot['line_item'] : [];
        $subsidiary = is_array($snapshot['subsidiary'] ?? null) ? $snapshot['subsidiary'] : [];
        $location = is_array($snapshot['location'] ?? null) ? $snapshot['location'] : [];
        $user = $assignedUser ?? ($record->created_by_id ? User::query()->find($record->created_by_id) : null);

        $lineText = trim((string) ($lineItem['name'] ?? ''));
        if (! empty($lineItem['description'])) {
            $lineText = trim($lineText."\n".(string) $lineItem['description']);
        }

        return array_merge(self::dateTimePrefill(timezone: self::resolveAccountTimezone()), [
            'customer_name' => (string) ($transaction['customer_name'] ?? ''),
            'customer_address' => self::formatCustomerAddress($transaction, 'multiline'),
            'customer_phone' => (string) ($transaction['customer_phone'] ?? ''),
            'customer_title' => (string) ($transaction['customer_title'] ?? ''),
            'line_item' => $lineText,
            'line_item_price' => self::formatLineItemPrice($lineItem),
            'dealership_name' => (string) ($subsidiary['display_name'] ?? ''),
            'dealership_address' => self::formatLocationAddress($location, 'multiline'),
            'user_name' => (string) ($user?->display_name ?: $user?->full_name ?: ''),
            'user_position_title' => (string) ($user?->position_title ?? ''),
            'user_signature' => self::signatureText($user),
            'free_text' => '',
        ]);
    }

    /**
     * @return array{date: string, current_month: string, current_day: string, current_year: string, current_time: string}
     */
    public static function dateTimePrefill(?Carbon $at = null, ?string $timezone = null): array
    {
        $timezone = $timezone ?? self::resolveAccountTimezone();
        $now = ($at ?? Carbon::now())->copy()->timezone($timezone);

        return [
            'date' => $now->format('m/d/Y'),
            'current_month' => $now->format('F'),
            'current_day' => (string) $now->day,
            'current_year' => $now->format('Y'),
            'current_time' => $now->format('g:i A'),
        ];
    }

    public static function resolveAccountTimezone(): string
    {
        $timezone = self::accountSettings()?->timezone;

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        try {
            return (string) config('app.timezone');
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    private static function accountSettings(): ?AccountSettings
    {
        try {
            return AccountSettings::getCurrent();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $lineItem
     */
    public static function formatLineItemPrice(array $lineItem): string
    {
        if (! array_key_exists('unit_price', $lineItem) || $lineItem['unit_price'] === null || $lineItem['unit_price'] === '') {
            return '';
        }

        return '$'.number_format((float) $lineItem['unit_price'], 2);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    public static function formatCustomerAddress(array $transaction, string $layout = 'multiline'): string
    {
        $line1 = trim((string) ($transaction['billing_address_line1'] ?? ''));
        $line2 = trim((string) ($transaction['billing_address_line2'] ?? ''));
        $cityStateZip = trim(implode(', ', array_filter([
            trim((string) ($transaction['billing_city'] ?? '')),
            trim((string) ($transaction['billing_state'] ?? '')),
            trim((string) ($transaction['billing_postal'] ?? '')),
        ], fn ($part) => $part !== '')));
        $country = trim((string) ($transaction['billing_country'] ?? ''));

        $segments = array_values(array_filter([$line1, $line2, $cityStateZip, $country], fn ($part) => $part !== ''));

        if ($layout === 'single') {
            return implode(', ', $segments);
        }

        if ($segments !== []) {
            return implode("\n", $segments);
        }

        return trim((string) ($transaction['customer_address'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $location
     */
    public static function formatLocationAddress(array $location, string $layout = 'multiline'): string
    {
        if ($location === []) {
            return '';
        }

        $line1 = trim((string) ($location['address_line_1'] ?? ''));
        $line2 = trim((string) ($location['address_line_2'] ?? ''));
        $cityStateZip = trim(implode(', ', array_filter([
            trim((string) ($location['city'] ?? '')),
            trim((string) ($location['state'] ?? '')),
            trim((string) ($location['postal_code'] ?? '')),
        ], fn ($part) => $part !== '')));
        $country = trim((string) ($location['country'] ?? ''));

        $segments = array_values(array_filter([$line1, $line2, $cityStateZip, $country], fn ($part) => $part !== ''));

        if ($layout === 'single') {
            return implode(', ', $segments);
        }

        return implode("\n", $segments);
    }

    public static function defaultValueForType(string $type, MsoRecord $record, ?User $assignedUser = null): string
    {
        $map = self::prefillMap($record, $assignedUser);

        return $map[$type] ?? '';
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function hydrateFieldValues(array $fields, MsoRecord $record, ?User $assignedUser = null): array
    {
        $prefill = self::prefillMap($record, $assignedUser);

        $details = MsoRecordDetails::normalize($record->details);
        $snapshot = is_array($details['snapshot'] ?? null) ? $details['snapshot'] : [];
        $transaction = is_array($snapshot['transaction'] ?? null) ? $snapshot['transaction'] : [];
        $location = is_array($snapshot['location'] ?? null) ? $snapshot['location'] : [];

        return array_map(function (array $field) use ($prefill, $transaction, $location, $assignedUser) {
            $type = (string) ($field['type'] ?? 'free_text');

            if ($type === 'customer_address') {
                $layout = (string) ($field['address_layout'] ?? 'multiline');
                if (! in_array($layout, ['single', 'multiline'], true)) {
                    $layout = 'multiline';
                }
                $field['address_layout'] = $layout;
                if (! array_key_exists('value', $field) || $field['value'] === null || $field['value'] === '') {
                    $field['value'] = self::formatCustomerAddress($transaction, $layout);
                }
            } elseif ($type === 'dealership_address') {
                $layout = (string) ($field['address_layout'] ?? 'multiline');
                if (! in_array($layout, ['single', 'multiline'], true)) {
                    $layout = 'multiline';
                }
                $field['address_layout'] = $layout;
                if (! array_key_exists('value', $field) || $field['value'] === null || $field['value'] === '') {
                    $field['value'] = self::formatLocationAddress($location, $layout);
                }
            } elseif (! array_key_exists('value', $field) || $field['value'] === null || $field['value'] === '') {
                if ($type !== 'free_text' && $type !== 'user_signature') {
                    $field['value'] = $prefill[$type] ?? '';
                } elseif ($type === 'user_signature') {
                    $field['value'] = $prefill['user_signature'] ?? '';
                } else {
                    $field['value'] = '';
                }
            }

            if ($type === 'user_signature') {
                $field = array_merge($field, self::signatureFieldMeta($assignedUser));
                if (empty($field['value'])) {
                    $field['value'] = $prefill['user_signature'] ?? '';
                }
            }

            $field['font_size'] = (int) ($field['font_size'] ?? 10);
            $field['font_bold'] = (bool) ($field['font_bold'] ?? false);

            return $field;
        }, $fields);
    }

    private static function signatureText(?User $user): string
    {
        if (! $user) {
            return '';
        }

        $payload = $user->savedSignaturePayload();
        if (! $payload) {
            return '';
        }

        if (($payload['method'] ?? null) === 'type') {
            return (string) ($payload['typed_signature'] ?? $payload['signed_name'] ?? '');
        }

        return (string) ($payload['signed_name'] ?? $user->display_name ?? '');
    }

    /**
     * @return array{value?: string, signature_method: ?string, signature_url: ?string}
     */
    public static function signatureFieldMeta(?User $user): array
    {
        if (! $user || ! $user->hasSavedSignature()) {
            return [
                'signature_method' => null,
                'signature_url' => null,
            ];
        }

        $payload = $user->savedSignaturePayload();
        if (($payload['method'] ?? null) === 'type') {
            return [
                'value' => (string) ($payload['typed_signature'] ?? ''),
                'signature_method' => 'type',
                'signature_url' => null,
            ];
        }

        return [
            'value' => (string) ($payload['signed_name'] ?? $user->display_name ?? ''),
            'signature_method' => 'draw',
            'signature_url' => $payload['signature_url'] ?? null,
        ];
    }

    public static function signatureImagePath(?User $user): ?string
    {
        if (! $user || ! $user->hasSavedSignature()) {
            return null;
        }

        $payload = $user->savedSignaturePayload();
        if (($payload['method'] ?? null) === 'type') {
            return null;
        }

        return $user->signature_file ?: null;
    }
}
