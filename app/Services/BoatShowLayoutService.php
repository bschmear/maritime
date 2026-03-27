<?php

namespace App\Services;

use App\Domain\BoatShowLayout\Models\BoatShowLayout;

class BoatShowLayoutService
{
    /**
     * Update layout dimensions only (placements live on boat_show_event_assets).
     */
    public function sync(BoatShowLayout $layout, array $data): BoatShowLayout
    {
        $layout->update([
            'width_ft' => $data['width_ft'],
            'height_ft' => $data['height_ft'],
        ]);

        return $layout;
    }
}
