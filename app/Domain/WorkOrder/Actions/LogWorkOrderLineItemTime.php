<?php

namespace App\Domain\WorkOrder\Actions;

use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Services\WorkOrderCalculator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LogWorkOrderLineItemTime
{
    /**
     * Add or set actual hours on a work order line item and recalculate totals.
     *
     * @return array{line_item: array<string, mixed>, work_order: array<string, mixed>}
     */
    public function __invoke(int $workOrderId, array $data): array
    {
        $mode = ($data['mode'] ?? 'add') === 'set' ? 'set' : 'add';

        $validated = Validator::make(array_merge($data, ['mode' => $mode]), [
            'line_item_id' => ['required', 'integer', 'exists:work_order_service_items,id'],
            'mode' => ['required', 'string', 'in:add,set'],
            'hours' => [Rule::requiredIf($mode === 'add'), 'nullable', 'numeric', 'min:0.01', 'max:999'],
            'actual_hours' => [Rule::requiredIf($mode === 'set'), 'nullable', 'numeric', 'min:0', 'max:999'],
        ])->validate();

        $workOrder = WorkOrder::query()->findOrFail($workOrderId);

        $lineItem = WorkOrderServiceItem::query()
            ->where('work_order_id', $workOrder->id)
            ->where('inactive', false)
            ->findOrFail($validated['line_item_id']);

        if ($validated['mode'] === 'set') {
            $lineItem->actual_hours = round((float) $validated['actual_hours'], 2);
        } else {
            $hoursToAdd = round((float) $validated['hours'], 2);
            $lineItem->actual_hours = round((float) ($lineItem->actual_hours ?? 0) + $hoursToAdd, 2);
        }

        $calculator = app(WorkOrderCalculator::class);
        $calculator->recalculateLineItem($lineItem);
        $calculator->recalculateWorkOrder($workOrder->fresh(['serviceItems']));

        $workOrder->refresh();

        return [
            'line_item' => [
                'id' => $lineItem->id,
                'actual_hours' => (float) $lineItem->actual_hours,
                'estimated_hours' => (float) ($lineItem->estimated_hours ?? 0),
            ],
            'work_order' => [
                'id' => $workOrder->id,
                'actual_hours' => (float) ($workOrder->actual_hours ?? 0),
                'estimated_hours' => (float) ($workOrder->estimated_hours ?? 0),
            ],
        ];
    }
}
