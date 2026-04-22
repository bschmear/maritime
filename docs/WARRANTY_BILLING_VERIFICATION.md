# Warranty Billing Verification

## Backfill mapping validation

After running tenant migrations, verify backfilled `billable_to` values:

```sql
-- Service Ticket line items
select warranty, warranty_type, billable_to, count(*)
from service_ticket_service_items
group by 1,2,3
order by 1,2,3;

-- Work Order line items
select warranty, warranty_type, billable_to, count(*)
from work_order_service_items
group by 1,2,3
order by 1,2,3;
```

Expected mapping:

- `warranty = false` => `billable_to = customer`
- `warranty = true` + `warranty_type = manufacturer` => `billable_to = manufacturer`
- `warranty = true` + `warranty_type = dealership` => `billable_to = internal`

## End-to-end manual scenarios

1. Create/update a Work Order line with:
   - non-warranty (`billable_to=customer`)
   - manufacturer warranty (`billable_to=manufacturer`)
   - dealership warranty (`billable_to=internal`)
2. Create invoice from work order.
3. Verify invoice line items preserve:
   - `is_warranty`
   - `warranty_type`
   - `billable_to`
   - `cost`
4. Verify customer invoice rendering:
   - warranty lines show **Covered under warranty**
   - customer-facing line total is `$0.00` for warranty lines
5. Verify P&L:
   - internal/dealership warranty excluded from revenue
   - manufacturer warranty included in revenue
   - costs included for all work

## Regression checks

- Transaction-based invoice create flow still works.
- Non-warranty invoice line totals/tax remain unchanged.
- Sales by item report still renders both summary and detail views.
