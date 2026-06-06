<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Models\MsoLayoutTemplate;
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
     * @return array{record: MsoRecord, layoutTemplate: ?array{id: int, name: string}}
     */
    public static function handle(MsoRecord $record, array $data): array
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
            'fields.*.font_bold' => ['nullable', 'boolean'],
            'fields.*.address_layout' => ['nullable', 'string', 'in:single,multiline'],
            'fields.*.signature_method' => ['nullable', 'string', 'in:draw,type'],
            'fields.*.signature_url' => ['nullable', 'string', 'max:2048'],
            'layout_template_name' => ['nullable', 'string', 'max:255'],
            'layout_template_id' => ['nullable', 'integer', 'exists:mso_layout_templates,id'],
            'page_sizes' => ['nullable', 'array'],
            'page_sizes.*.width' => ['required_with:page_sizes', 'numeric', 'min:1'],
            'page_sizes.*.height' => ['required_with:page_sizes', 'numeric', 'min:1'],
        ])->validate();

        $assignedUserId = isset($validated['assigned_user_id'])
            ? (int) $validated['assigned_user_id']
            : ($record->created_by_id ?? current_tenant_user_id());

        $assignedUser = $assignedUserId ? User::query()->find($assignedUserId) : null;

        $fields = collect($validated['fields'] ?? [])
            ->map(function (array $field) {
                $field['id'] = $field['id'] ?? (string) Str::uuid();
                $field['font_size'] = (int) ($field['font_size'] ?? 10);
                $field['font_bold'] = (bool) ($field['font_bold'] ?? false);

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

        $layoutTemplate = null;
        $linkedTemplateId = null;
        $templateId = isset($validated['layout_template_id'])
            ? (int) $validated['layout_template_id']
            : null;
        $templateName = trim((string) ($validated['layout_template_name'] ?? ''));

        if ($templateId) {
            $template = MsoLayoutTemplate::query()->find($templateId);
            if ($template) {
                $layoutTemplate = self::serializeLayoutTemplate(
                    self::updateLayoutTemplate($template, $fields),
                );
                $linkedTemplateId = $templateId;
            }
        } elseif ($templateName !== '') {
            $savedTemplate = self::saveNamedLayoutTemplate($templateName, $fields, $assignedUserId);
            $layoutTemplate = self::serializeLayoutTemplate($savedTemplate);
            $linkedTemplateId = (int) $savedTemplate->id;
        }

        $pageSizes = self::normalizePageSizes($validated['page_sizes'] ?? null, $record->details);

        $record->fill([
            'created_by_id' => $record->created_by_id ?? $assignedUserId,
            'details' => MsoRecordDetails::build($snapshot, $assignedUserId, $fields, $pageSizes),
            'status' => $record->status ?? Status::Draft,
            'layout_template_id' => $linkedTemplateId ?? $record->layout_template_id,
        ])->save();

        return [
            'record' => $record->fresh(),
            'layoutTemplate' => $layoutTemplate,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $incoming
     * @return array<int|string, array{width: float, height: float}>
     */
    private static function normalizePageSizes(?array $incoming, ?array $existingDetails): array
    {
        if (! is_array($incoming) || $incoming === []) {
            return MsoRecordDetails::pageSizes($existingDetails);
        }

        $sizes = [];
        foreach ($incoming as $page => $dimensions) {
            if (! is_array($dimensions)) {
                continue;
            }

            $width = (float) ($dimensions['width'] ?? 0);
            $height = (float) ($dimensions['height'] ?? 0);
            if ($width <= 0 || $height <= 0) {
                continue;
            }

            $sizes[(int) $page] = [
                'width' => $width,
                'height' => $height,
            ];
        }

        return $sizes;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function updateLayoutTemplate(MsoLayoutTemplate $template, array $fields): MsoLayoutTemplate
    {
        $template->layout = self::layoutItemsFromFields($fields);
        $template->save();

        return $template->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function saveNamedLayoutTemplate(string $name, array $fields, ?int $userId): MsoLayoutTemplate
    {
        return MsoLayoutTemplate::query()->updateOrCreate(
            ['name' => $name],
            [
                'layout' => self::layoutItemsFromFields($fields),
                'created_by_id' => $userId,
            ],
        );
    }

    /**
     * @return array{id: int, name: string}
     */
    private static function serializeLayoutTemplate(MsoLayoutTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'name' => (string) $template->name,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private static function layoutItemsFromFields(array $fields): array
    {
        return collect($fields)
            ->map(function (array $field) {
                $item = [
                    'type' => $field['type'],
                    'page' => (int) $field['page'],
                    'x' => (float) $field['x'],
                    'y' => (float) $field['y'],
                    'width' => (float) $field['width'],
                    'height' => (float) $field['height'],
                    'font_size' => (int) ($field['font_size'] ?? 10),
                ];

                if (! empty($field['address_layout'])) {
                    $item['address_layout'] = (string) $field['address_layout'];
                }

                if (! empty($field['font_bold'])) {
                    $item['font_bold'] = true;
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fieldsFromTemplate(?array $layout, MsoRecord $record, ?User $assignedUser): array
    {
        if (! is_array($layout) || $layout === []) {
            return [];
        }

        $fields = collect($layout)
            ->map(fn (array $item) => array_merge($item, [
                'id' => (string) Str::uuid(),
                'value' => '',
            ]))
            ->values()
            ->all();

        return MsoValueResolver::hydrateFieldValues($fields, $record, $assignedUser);
    }
}
