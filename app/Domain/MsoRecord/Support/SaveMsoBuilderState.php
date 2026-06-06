<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Models\MsoSourceLayout;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\User\Models\User;
use App\Enums\MsoRecord\Status;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SaveMsoBuilderState
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(MsoRecord $record, array $data): MsoRecord
    {
        $validated = Validator::make($data, [
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['nullable', 'string'],
            'fields.*.type' => ['required_with:fields', 'string'],
            'fields.*.page' => ['required_with:fields', 'integer', 'min:1'],
            'fields.*.x' => ['required_with:fields', 'numeric', 'min:0', 'max:1'],
            'fields.*.y' => ['required_with:fields', 'numeric', 'min:0', 'max:1'],
            'fields.*.width' => ['required_with:fields', 'numeric', 'min:0', 'max:1'],
            'fields.*.height' => ['required_with:fields', 'numeric', 'min:0', 'max:1'],
            'fields.*.value' => ['nullable', 'string'],
            'fields.*.font_size' => ['nullable', 'integer', 'min:6', 'max:24'],
            'save_layout' => ['sometimes', 'boolean'],
        ])->validate();

        $assignedUserId = isset($validated['assigned_user_id'])
            ? (int) $validated['assigned_user_id']
            : ($record->created_by_id ?? current_tenant_user_id());

        $assignedUser = $assignedUserId ? User::query()->find($assignedUserId) : null;

        $fields = collect($validated['fields'] ?? [])
            ->map(function (array $field) {
                $field['id'] = $field['id'] ?? (string) Str::uuid();
                $field['font_size'] = (int) ($field['font_size'] ?? 10);

                return $field;
            })
            ->values()
            ->all();

        $fields = MsoValueResolver::hydrateFieldValues($fields, $record, $assignedUser);

        $transaction = $record->transaction_id
            ? Transaction::query()->find($record->transaction_id)
            : null;
        $lineItem = $record->transaction_line_item_id
            ? TransactionLineItem::query()->find($record->transaction_line_item_id)
            : null;
        $assetUnit = $record->asset_unit_id
            ? AssetUnit::query()->find($record->asset_unit_id)
            : null;

        $snapshot = ($transaction && $lineItem && $assetUnit)
            ? MsoRecordSnapshot::build($transaction, $lineItem, $assetUnit, $assignedUser)
            : (MsoRecordDetails::normalize($record->details)['snapshot'] ?? []);

        $record->fill([
            'created_by_id' => $record->created_by_id ?? $assignedUserId,
            'details' => MsoRecordDetails::build($snapshot, $assignedUserId, $fields),
            'status' => $record->status ?? Status::Draft,
        ])->save();

        if (! empty($validated['save_layout']) && $record->source_document_id) {
            self::saveLayoutTemplate($record, $fields, $assignedUserId);
        }

        return $record->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function saveLayoutTemplate(MsoRecord $record, array $fields, ?int $userId): void
    {
        $layout = collect($fields)
            ->map(fn (array $field) => [
                'id' => $field['id'] ?? (string) Str::uuid(),
                'type' => $field['type'],
                'page' => (int) $field['page'],
                'x' => (float) $field['x'],
                'y' => (float) $field['y'],
                'width' => (float) $field['width'],
                'height' => (float) $field['height'],
                'font_size' => (int) ($field['font_size'] ?? 10),
            ])
            ->values()
            ->all();

        MsoSourceLayout::query()->updateOrCreate(
            ['source_document_id' => $record->source_document_id],
            [
                'layout' => $layout,
                'created_by_id' => $userId,
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fieldsFromLayoutTemplate(?MsoSourceLayout $layout, MsoRecord $record, ?User $assignedUser): array
    {
        if (! $layout || ! is_array($layout->layout)) {
            return [];
        }

        $fields = collect($layout->layout)
            ->map(fn (array $item) => array_merge($item, [
                'id' => (string) Str::uuid(),
                'value' => '',
            ]))
            ->values()
            ->all();

        return MsoValueResolver::hydrateFieldValues($fields, $record, $assignedUser);
    }
}
