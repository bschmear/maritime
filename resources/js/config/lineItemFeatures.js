/**
 * Line-item catalog add-ons (distinct from boat/asset options).
 *
 * Temporarily disabled — staff confused add-ons with asset options on estimates,
 * opportunities, deals, invoices, and public forms. Set to `true` to restore the UI.
 */
export const LINE_ITEM_ADDONS_UI_ENABLED = false;

/** Table colspan adjustment when the add-ons column is hidden (one fewer column). */
export function lineItemTableColspan(withAddonsColspan) {
    return LINE_ITEM_ADDONS_UI_ENABLED ? withAddonsColspan : withAddonsColspan - 1;
}
