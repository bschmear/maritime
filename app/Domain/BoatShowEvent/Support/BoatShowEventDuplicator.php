<?php

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Models\BoatShowEventAsset;
use App\Domain\Checklist\Models\Checklist;
use App\Domain\Checklist\Models\ChecklistItem;

class BoatShowEventDuplicator
{
    /**
     * @return array<string, mixed>
     */
    public static function formInitialData(BoatShowEvent $source): array
    {
        $recipientIds = [];
        if (is_array($source->recipients) && is_array($source->recipients['user_ids'] ?? null)) {
            $recipientIds = array_values(array_filter(
                array_map('intval', $source->recipients['user_ids']),
                fn (int $id) => $id > 0,
            ));
        }

        return [
            'duplicate_from_event_id' => $source->id,
            'boat_show_id' => $source->boat_show_id,
            'starts_at' => '',
            'ends_at' => '',
            'venue' => $source->venue ?? '',
            'booth' => $source->booth ?? '',
            'address_line_1' => $source->address_line_1 ?? '',
            'address_line_2' => $source->address_line_2 ?? '',
            'city' => $source->city ?? '',
            'state' => $source->state ?? '',
            'country' => $source->country ?? '',
            'postal_code' => $source->postal_code ?? '',
            'latitude' => $source->latitude ?? '',
            'longitude' => $source->longitude ?? '',
            'active' => 0,
            'auto_followup' => $source->auto_followup !== false ? 1 : 0,
            'delay_amount' => $source->delay_amount ?? 1,
            'delay_unit' => $source->delay_unit ?? 'days',
            'recipient_user_ids' => $recipientIds,
        ];
    }

    public static function copyRelations(BoatShowEvent $source, BoatShowEvent $target): void
    {
        self::copyChecklist($source, $target);
        self::copyAssets($source, $target);
        self::copyLayout($source, $target);
    }

    private static function copyChecklist(BoatShowEvent $source, BoatShowEvent $target): void
    {
        $sourceChecklist = $source->checklist()->with('items')->first();
        if (! $sourceChecklist) {
            return;
        }

        $checklist = Checklist::query()->create([
            'checklist_template_id' => $sourceChecklist->checklist_template_id,
            'name' => $sourceChecklist->name,
            'checklistable_type' => BoatShowEvent::class,
            'checklistable_id' => $target->id,
        ]);

        foreach ($sourceChecklist->items as $index => $item) {
            ChecklistItem::query()->create([
                'checklist_id' => $checklist->id,
                'label' => $item->label,
                'completed' => false,
                'required' => (bool) $item->required,
                'position' => $item->position ?? ($index + 1),
            ]);
        }
    }

    private static function copyAssets(BoatShowEvent $source, BoatShowEvent $target): void
    {
        $source->eventAssets()
            ->get()
            ->each(function (BoatShowEventAsset $row) use ($target) {
                BoatShowEventAsset::query()->create([
                    'boat_show_event_id' => $target->id,
                    'asset_id' => $row->asset_id,
                    'asset_unit_id' => $row->asset_unit_id,
                    'include_in_layout' => $row->include_in_layout,
                    'x' => $row->x,
                    'y' => $row->y,
                    'rotation' => $row->rotation,
                    'z_index' => $row->z_index,
                    'name' => $row->name,
                    'length_ft' => $row->length_ft,
                    'width_ft' => $row->width_ft,
                    'color' => $row->color,
                ]);
            });
    }

    private static function copyLayout(BoatShowEvent $source, BoatShowEvent $target): void
    {
        $layout = $source->layouts()->orderBy('id')->first();
        if (! $layout) {
            return;
        }

        $target->layouts()->create([
            'name' => $layout->name,
            'width_ft' => $layout->width_ft,
            'height_ft' => $layout->height_ft,
            'grid_size' => $layout->grid_size,
            'scale' => $layout->scale,
            'meta' => $layout->meta,
        ]);
    }
}
