Work Order Calculation Service
Purpose

Centralize all pricing + cost logic for:

Line items

Work order totals

Profit calculations

Never calculate totals in controllers or models.

All calculations must go through:

App\Services\WorkOrderCalculator

Responsibilities
1. Recalculate a Line Item

Method:

recalculateLineItem(WorkOrderLineItem $item): void

Logic
Hourly
total_price = estimated_hours * unit_price
total_cost  = actual_hours * unit_cost

Flat
total_price = unit_price
total_cost  = unit_cost

Quantity
total_price = quantity * unit_price
total_cost  = quantity * unit_cost

Warranty Override

If:

warranty = true


Then:

total_price = 0


Save updated totals to the line item.

2. Recalculate Work Order Totals

Method:

recalculateWorkOrder(WorkOrder $workOrder): void

Calculations
estimated_hours = sum(line_items.estimated_hours)
actual_hours    = sum(line_items.actual_hours)

labor_cost = sum(line_items.total_cost where billing_type = Hourly)
parts_cost = sum(line_items.total_cost where billing_type = Quantity)

total_cost = sum(line_items.total_cost)

subtotal = sum(line_items.total_price where billable = true)

estimated_tax = subtotal * tax_rate

grand_total = subtotal + estimated_tax

gross_profit = subtotal - total_cost


Save to:

work_orders.estimated_hours
work_orders.actual_hours
work_orders.labor_cost
work_orders.parts_cost
work_orders.total_cost
work_orders.estimated_tax


Grand total may be stored or computed dynamically.

When To Call The Service

Call the calculator when:

A line item is created

A line item is updated

A line item is deleted

A work order tax rate changes

Work order is finalized

Never calculate inside controllers.