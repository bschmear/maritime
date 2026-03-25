<?php

namespace App\Services;

use App\Domain\BoatShowLayout\Models\BoatShowLayout;

class BoatShowLayoutService
{
    public function sync(BoatShowLayout $layout, array $data): BoatShowLayout
    {
        // Update layout size
        $layout->update([
            'width_ft' => $data['width_ft'],
            'height_ft' => $data['height_ft'],
        ]);

        $incoming = collect($data['items'] ?? []);

        $existing = $layout->items()->get()->keyBy('id');

        $keptIds = [];

        foreach ($incoming as $itemData) {

            $normalized = $this->normalizeItem($itemData);

            // Validate boundaries (optional strict)
            $this->validateBounds($layout, $normalized);

            if (! empty($itemData['id']) && $existing->has($itemData['id'])) {

                $item = $existing[$itemData['id']];
                $item->update($normalized);

                $keptIds[] = $item->id;

            } else {

                $item = $layout->items()->create($normalized);
                $keptIds[] = $item->id;
            }
        }

        // Delete removed items
        $layout->items()
            ->whereNotIn('id', $keptIds)
            ->delete();

        return $layout;
    }

    protected function normalizeItem(array $data): array
    {
        return [
            'name' => $data['name'],
            'length_ft' => round($data['length_ft'], 2),
            'width_ft' => round($data['width_ft'], 2),

            'x' => round($data['x'], 2),
            'y' => round($data['y'], 2),

            'rotation' => $data['rotation'] ?? 0,

            'color' => $data['color'] ?? null,

            'asset_unit_id' => $data['asset_unit_id'] ?? null,
            'inventory_unit_id' => $data['inventory_unit_id'] ?? null,
        ];
    }

    protected function validateBounds(BoatShowLayout $layout, array $item): void
    {
        [$length, $width] = $this->getEffectiveDimensions($item);

        $outOfBounds =
            $item['x'] < 0 ||
            $item['y'] < 0 ||
            ($layout->width_ft < $item['x'] + $length) ||
            ($layout->height_ft < $item['y'] + $width);

        // Optional: throw or ignore
        // For now: allow but could log
        if ($outOfBounds) {
            // throw ValidationException::withMessages([...]);
        }
    }

    protected function getEffectiveDimensions(array $item): array
    {
        if (in_array($item['rotation'], [90, 270])) {
            return [$item['width_ft'], $item['length_ft']];
        }

        return [$item['length_ft'], $item['width_ft']];
    }
}
