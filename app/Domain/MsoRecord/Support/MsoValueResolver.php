<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\User\Models\User;
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
        $user = $assignedUser ?? ($record->created_by_id ? User::query()->find($record->created_by_id) : null);

        $lineText = trim((string) ($lineItem['name'] ?? ''));
        if (! empty($lineItem['description'])) {
            $lineText = trim($lineText."\n".(string) $lineItem['description']);
        }

        return [
            'customer_name' => (string) ($transaction['customer_name'] ?? ''),
            'customer_address' => (string) ($transaction['customer_address'] ?? ''),
            'customer_phone' => (string) ($transaction['customer_phone'] ?? ''),
            'customer_title' => (string) ($transaction['customer_title'] ?? ''),
            'line_item' => $lineText,
            'date' => Carbon::now()->format('m/d/Y'),
            'dealership_name' => (string) ($subsidiary['display_name'] ?? ''),
            'user_name' => (string) ($user?->display_name ?: $user?->full_name ?: ''),
            'user_signature' => self::signatureText($user),
            'free_text' => '',
        ];
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

        return array_map(function (array $field) use ($prefill) {
            $type = (string) ($field['type'] ?? 'free_text');
            if (! array_key_exists('value', $field) || $field['value'] === null || $field['value'] === '') {
                if ($type !== 'free_text' && $type !== 'user_signature') {
                    $field['value'] = $prefill[$type] ?? '';
                } elseif ($type === 'user_signature') {
                    $field['value'] = $prefill['user_signature'] ?? '';
                } else {
                    $field['value'] = '';
                }
            }

            if ($type === 'user_signature' && empty($field['value'])) {
                $field['value'] = $prefill['user_signature'] ?? '';
            }

            $field['font_size'] = (int) ($field['font_size'] ?? 10);

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
