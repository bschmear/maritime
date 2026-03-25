<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Domain\BoatShowLayout\Models\BoatShowLayout;
use App\Services\BoatShowLayoutService;

class BoatShowLayoutController extends BaseController
{
    public function show($id)
    {
        $layout = BoatShowLayout::with('items')->findOrFail($id);

        return response()->json($layout);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'boat_show_event_id' => ['required', 'exists:boat_show_events,id'],
            'name' => ['nullable', 'string'],
            'width_ft' => ['required', 'integer', 'min:1'],
            'height_ft' => ['required', 'integer', 'min:1'],
        ]);

        $layout = BoatShowLayout::create($data);

        return response()->json($layout);
    }

    public function update(Request $request, $id)
    {
        $layout = BoatShowLayout::findOrFail($id);

        $data = $request->validate([
            'name' => ['nullable', 'string'],
            'width_ft' => ['required', 'integer', 'min:1'],
            'height_ft' => ['required', 'integer', 'min:1'],
        ]);

        $layout->update($data);

        return response()->json($layout);
    }

    public function sync(Request $request, $id, BoatShowLayoutService $service)
    {
        $layout = BoatShowLayout::findOrFail($id);

        $data = $request->validate([
            'width_ft' => ['required', 'integer', 'min:1'],
            'height_ft' => ['required', 'integer', 'min:1'],
            'items' => ['array'],

            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string'],
            'items.*.length_ft' => ['required', 'numeric', 'min:0'],
            'items.*.width_ft' => ['required', 'numeric', 'min:0'],
            'items.*.x' => ['required', 'numeric'],
            'items.*.y' => ['required', 'numeric'],
            'items.*.rotation' => ['required', 'in:0,90,180,270'],
            'items.*.color' => ['nullable', 'string'],

            'items.*.asset_unit_id' => ['nullable', 'integer'],
            'items.*.inventory_unit_id' => ['nullable', 'integer'],
        ]);

        $layout = $service->sync($layout, $data);

        return response()->json($layout->load('items'));
    }
}