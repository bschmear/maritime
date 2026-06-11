<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;

final class DashboardFilterOptions
{
    /**
     * @return array{
     *   subsidiaries: list<array{id: int, label: string}>,
     *   locations: list<array{id: int, label: string, subsidiary_ids: list<int>}>
     * }
     */
    public static function build(): array
    {
        $subsidiaries = Subsidiary::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (Subsidiary $s) => [
                'id' => (int) $s->id,
                'label' => trim((string) ($s->display_name ?: 'Subsidiary #'.$s->id)),
            ])
            ->values()
            ->all();

        $locations = Location::query()
            ->whereHas('subsidiaries')
            ->with(['subsidiaries:id'])
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (Location $location) => [
                'id' => (int) $location->id,
                'label' => trim((string) ($location->display_name ?: 'Location #'.$location->id)),
                'subsidiary_ids' => $location->subsidiaries
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'subsidiaries' => $subsidiaries,
            'locations' => $locations,
        ];
    }
}
