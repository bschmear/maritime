<?php

namespace App\Services;

use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;

class WorkOrderCalculator
{
    /**
     * Recalculate a Line Item
     */
    public function recalculateLineItem(WorkOrderServiceItem $item): void
    {
        $quantity = $item->quantity ?? 1;
        $rate = $item->unit_price ?? 0;
        $cost = $item->unit_cost ?? 0;
        $estimatedHours = $item->estimated_hours ?? 0;
        $actualHours = $item->actual_hours ?? 0;

        // Calculate based on billing type
        switch ($item->billing_type) {
            case 1: // Hourly
                $item->total_price = $estimatedHours * $rate;
                $item->total_cost = $actualHours * $cost;
                break;
            case 2: // Flat
                $item->total_price = $rate;
                $item->total_cost = $cost;
                break;
            case 3: // Quantity
            default:
                $item->total_price = $quantity * $rate;
                $item->total_cost = $quantity * $cost;
                break;
        }

        // Warranty Override
        if ($item->warranty) {
            $item->total_price = 0;
        }

        $item->save();
    }

    /**
     * Recalculate Work Order Totals
     */
    public function recalculateWorkOrder(WorkOrder $workOrder): void
    {
        $lineItems = $workOrder->serviceItems;

        // Calculate sums from line items
        $workOrder->estimated_hours = $lineItems->sum('estimated_hours');
        $workOrder->actual_hours = $lineItems->sum('actual_hours');

        // Labor cost = sum of total_cost where billing_type = Hourly (1)
        $workOrder->labor_cost = $lineItems->where('billing_type', 1)->sum('total_cost');

        // Parts cost = sum of total_cost where billing_type = Quantity (3)
        $workOrder->parts_cost = $lineItems->where('billing_type', 3)->sum('total_cost');

        // Total cost = sum of all total_cost
        $workOrder->total_cost = $lineItems->sum('total_cost');

        // Subtotal = sum of total_price where billable = true
        $subtotal = $lineItems->where('billable', true)->sum('total_price');

        // Get tax rate from work order (stored as percentage, convert to decimal)
        $taxRate = ($workOrder->tax_rate ?? 0) / 100;
        $workOrder->estimated_tax = $subtotal * $taxRate;

        $workOrder->save();
    }

    /**
     * Get calculated totals for a work order (without saving)
     */
    public function calculateWorkOrderTotals(WorkOrder $workOrder): array
    {
        $lineItems = $workOrder->serviceItems;

        return [
            'estimated_hours' => $lineItems->sum('estimated_hours'),
            'actual_hours' => $lineItems->sum('actual_hours'),
            'labor_cost' => $lineItems->where('billing_type', 1)->sum('total_cost'),
            'parts_cost' => $lineItems->where('billing_type', 3)->sum('total_cost'),
            'total_cost' => $lineItems->sum('total_cost'),
            'subtotal' => $lineItems->where('billable', true)->sum('total_price'),
            'estimated_tax' => ($lineItems->where('billable', true)->sum('total_price')) * (($workOrder->tax_rate ?? 0) / 100),
            'grand_total' => ($lineItems->where('billable', true)->sum('total_price')) * (1 + (($workOrder->tax_rate ?? 0) / 100)),
            'gross_profit' => ($lineItems->where('billable', true)->sum('total_price')) - $lineItems->sum('total_cost'),
        ];
    }

    public function checkForReauthorization(ServiceTicket $ticket, float $actualTotal)
    {
        $threshold = $ticket->account->settings->estimate_threshold_percent ?? 20;

        $estimated = $ticket->estimated_total;

        if (!$estimated || $estimated == 0) {
            return false;
        }

        $percentIncrease = (($actualTotal - $estimated) / $estimated) * 100;

        if ($percentIncrease > $threshold) {
            $ticket->update([
                'requires_reauthorization' => true,
                'revised_estimated_total' => $actualTotal,
            ]);

            return true;
        }

        return false;
    }
}
