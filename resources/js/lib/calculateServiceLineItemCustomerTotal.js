/**
 * Customer-facing line total (billable, warranty zeroes price, billing-type rules).
 * Used by service tickets, work orders, and the shared service-item line modal.
 */
export function calculateServiceLineItemCustomerTotal(item) {
    const rate = Number(item.unit_price) || 0;
    const quantity = Number(item.quantity) || 1;
    const estimatedHours = Number(item.estimated_hours) || 0;

    let total = 0;

    switch (item.billing_type) {
        case 1:
            total = estimatedHours * rate;
            break;
        case 2:
            total = rate;
            break;
        case 3:
        default:
            total = quantity * rate;
            break;
    }

    if (item.warranty) {
        return 0;
    }

    return total;
}
