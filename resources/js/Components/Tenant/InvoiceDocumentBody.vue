<script setup>
import PublicDocumentLineItemCard from '@/Components/Tenant/Public/PublicDocumentLineItemCard.vue';
import PublicDocumentLineItemField from '@/Components/Tenant/Public/PublicDocumentLineItemField.vue';
import { computed } from 'vue';
import { LINE_ITEM_ADDONS_UI_ENABLED } from '@/config/lineItemFeatures';

const lineItemAddonsUiEnabled = LINE_ITEM_ADDONS_UI_ENABLED;
import { lineAssetSelectedOptions, selectedOptionLabel } from '@/Utils/lineItemsFromEstimate';

const ASSET_TYPE = 'App\\Domain\\Asset\\Models\\Asset';
const INVENTORY_TYPE = 'App\\Domain\\InventoryItem\\Models\\InventoryItem';
const INVOICE_ADDON_NAME_SEP = ' — ';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
    /** Line items + totals only (service-ticket-style chrome lives on the parent page). */
    bodyOnly: { type: Boolean, default: false },
});

const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';
const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';

const lineItems = computed(() => {
    const raw = props.record?.items ?? props.record?.line_items ?? [];
    return Array.isArray(raw) ? raw : [];
});

const sortedLineItems = computed(() => {
    const list = [...lineItems.value];
    return list.sort((a, b) => {
        const pa = Number(a.position);
        const pb = Number(b.position);
        if (!Number.isNaN(pa) && !Number.isNaN(pb) && pa !== pb) {
            return pa - pb;
        }
        return (Number(a.id) || 0) - (Number(b.id) || 0);
    });
});

const isIndentedInvoiceAddonRow = (primary, row) => {
    if (!primary?.name || !row?.name) return false;
    if (row.itemable_type) return false;
    return String(row.name).startsWith(String(primary.name) + INVOICE_ADDON_NAME_SEP);
};

const groupedInvoiceLineItems = computed(() => {
    const groups = [];
    for (const row of sortedLineItems.value) {
        const last = groups[groups.length - 1];
        if (last && isIndentedInvoiceAddonRow(last.primary, row)) {
            last.flatAddons.push(row);
        } else {
            groups.push({ primary: row, flatAddons: [] });
        }
    }
    return groups;
});

const flatAddonDisplayName = (primaryName, row) => {
    const prefix = String(primaryName) + INVOICE_ADDON_NAME_SEP;
    const n = String(row.name ?? '');
    return n.startsWith(prefix) ? n.slice(prefix.length) : (row.name ?? '—');
};

const invoiceLineBoatOptions = (item) => {
    if (item.itemable_type !== ASSET_TYPE) return [];
    const tli = item.transaction_line_item ?? item.transactionLineItem;
    if (tli) return lineAssetSelectedOptions(tli);
    return lineAssetSelectedOptions(item);
};

const effectiveLogoUrl = computed(() => props.logoUrl ?? props.account?.logo_url ?? null);

const transactionLocationPreview = computed(() => {
    const loc = props.record?.transaction?.location;
    if (!loc) return null;
    const line1 = loc.address_line_1 ?? loc.address_line1 ?? '';
    const line2 = loc.address_line_2 ?? loc.address_line2 ?? '';
    const city = loc.city ?? '';
    const state = loc.state ?? '';
    const postal = loc.postal_code ?? '';
    const phone = loc.phone ?? '';
    const email = loc.email ?? '';
    if (!line1 && !city && !phone && !email) return null;
    return { line1, line2, city, state, postal, phone, email };
});

const invoiceHeaderTitle = computed(
    () => props.record.display_name || `INV-${props.record.sequence ?? props.record.id}`,
);

const accountDisplayName = computed(() =>
    props.account?.settings?.business_name ?? props.account?.business_name ?? 'Company'
);

const transaction = computed(() => props.record?.transaction ?? null);

const companyLocation = computed(() => transaction.value?.location ?? null);

const companyAddressLines = computed(() => {
    const loc = companyLocation.value;
    if (!loc) return [];
    const lines = [];
    if (loc.display_name) lines.push(loc.display_name);
    const a1 = loc.address_line_1 ?? loc.address_line1;
    if (a1) lines.push(a1);
    const a2 = loc.address_line_2 ?? loc.address_line2;
    if (a2) lines.push(a2);
    const cityLine = [loc.city, loc.state, loc.postal_code].filter(Boolean).join(', ');
    if (cityLine) lines.push(cityLine);
    if (loc.country) lines.push(loc.country);
    return lines;
});

const companyPhone = computed(() => companyLocation.value?.phone ?? null);
const companyEmail = computed(() => companyLocation.value?.email ?? null);

const statusLabel = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM_KEY] ?? [];
    const s = props.record?.status;
    return opts.find(o => o.id == s || o.value === s)?.name ?? s ?? 'Draft';
});

const statusBadgeClass = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM_KEY] ?? [];
    const s = props.record?.status;
    const opt = opts.find(o => o.id == s || o.value === s);
    if (opt?.bgClass) return opt.bgClass;
    const map = {
        draft:   'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        sent:    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        viewed:  'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        paid:    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        void:    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };
    const v = typeof s === 'string' ? s : opts.find(o => o.id == s)?.value;
    return map[v] ?? map.draft;
});

const paymentTermLabel = computed(() => {
    const raw = props.record?.payment_term;
    const opts = props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? [];
    const opt = opts.find(o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw));
    return opt?.name ?? raw ?? null;
});

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : null;

const itemPrimaryLabel  = (item) => item.name ?? '—';
const assetVariantOf    = (item) => item.asset_variant ?? item.assetVariant ?? null;
const variantLabel      = (item) => { const v = assetVariantOf(item); return v ? (v.display_name ?? v.name ?? null) : null; };

const unitLabel = (item) => {
    const u = item.asset_unit ?? item.assetUnit ?? null;
    const raw = u?.display_name;
    if (!raw) return null;
    const parts = String(raw).split(' - ');
    return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
};

const itemableBadge = (item) => {
    if (!item.itemable_type) return null;
    if (item.itemable_type === ASSET_TYPE) return 'Asset';
    if (item.itemable_type === INVENTORY_TYPE) return 'Part';
    return null;
};

const discountCell = (item) => {
    if (item.discount_percent != null) return `${item.discount_percent}%`;
    if (item.discount != null && parseFloat(item.discount) !== 0) return formatCurrency(item.discount);
    return '—';
};

const isCoveredWarranty = (item) => {
    const billableTo = item.billable_to ?? 'customer';
    return billableTo !== 'customer' || !!item.is_warranty;
};

const customerFacingLineTotal = (item) => {
    if (isCoveredWarranty(item)) return 0;
    return item.total ?? item.line_total ?? 0;
};

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const itemPreTax = (item) => {
    if (isCoveredWarranty(item)) return 0;
    if (item.subtotal != null && item.subtotal !== '') {
        return Number(item.subtotal);
    }
    const qty = Number(item.quantity ?? 1);
    const price = Number(item.unit_price ?? item.price ?? 0);
    const discount = Number(item.discount ?? 0);
    return Math.max(0, qty * price - discount);
};

const itemTax = (item) => {
    if (isCoveredWarranty(item)) return 0;
    if (item.tax_amount != null && item.tax_amount !== '') {
        return Number(item.tax_amount);
    }
    if (!item.taxable || !Number(item.tax_rate)) {
        return 0;
    }
    return roundMoney(itemPreTax(item) * (Number(item.tax_rate) / 100));
};

const itemTotalWithTax = (item) => {
    if (isCoveredWarranty(item)) return 0;
    if (item.total != null && item.total !== '') {
        return Number(item.total);
    }
    return roundMoney(itemPreTax(item) + itemTax(item));
};

const optionRowTaxable = (opt) => opt.taxable !== false && opt.taxable !== 0 && opt.taxable !== '0';

const optionPreTax = (opt) => Number(opt?.price ?? 0);

const optionTax = (opt) => {
    const rate = Number(opt?.tax_rate ?? props.record?.tax_rate ?? 0);
    if (!optionRowTaxable(opt) || rate <= 0) {
        return 0;
    }
    return roundMoney(optionPreTax(opt) * (rate / 100));
};

const optionTotalWithTax = (opt) => roundMoney(optionPreTax(opt) + optionTax(opt));

const showLineTax = computed(() => {
    if (Number(props.record?.tax_total ?? 0) > 0) {
        return true;
    }
    return lineItems.value.some(
        (item) => itemTax(item) > 0 || (item.taxable && Number(item.tax_rate) > 0),
    );
});

const lineItemColspan = computed(() => (showLineTax.value ? 7 : 5));
</script>

<template>
    <!-- Public invoice shell: parent provides header / customer row (matches ServiceTicketReview). -->
    <div v-if="bodyOnly" class="invoice-document-body-only text-gray-900">
        <div v-if="record.notes" class="border-t border-gray-200 px-8 py-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Notes
            </h2>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-900">
                {{ record.notes }}
            </p>
        </div>

        <div class="border-t border-gray-200 px-4 py-6 sm:px-8">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Line items
            </h2>

            <div v-if="lineItems.length" class="mb-4 space-y-3 md:hidden print:hidden">
                <template v-for="(group, gIdx) in groupedInvoiceLineItems" :key="`m-bo-${group.primary.id}-${gIdx}`">
                    <PublicDocumentLineItemCard
                        :title="itemPrimaryLabel(group.primary)"
                        :amount="formatCurrency(showLineTax ? itemTotalWithTax(group.primary) : customerFacingLineTotal(group.primary))"
                    >
                        <span
                            v-if="itemableBadge(group.primary)"
                            class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                        >
                            {{ itemableBadge(group.primary) }}
                        </span>
                        <PublicDocumentLineItemField
                            v-if="variantLabel(group.primary)"
                            label="Variant"
                            :value="variantLabel(group.primary)"
                        />
                        <PublicDocumentLineItemField
                            v-if="unitLabel(group.primary)"
                            label="Unit"
                            :value="unitLabel(group.primary)"
                        />
                        <PublicDocumentLineItemField label="Qty" :value="group.primary.quantity ?? 1" />
                        <PublicDocumentLineItemField label="Unit price">
                            <span v-if="isCoveredWarranty(group.primary)" class="text-blue-700">Covered under warranty</span>
                            <span v-else>{{ formatCurrency(group.primary.unit_price ?? group.primary.price) }}</span>
                        </PublicDocumentLineItemField>
                        <PublicDocumentLineItemField label="Discount">
                            <span v-if="isCoveredWarranty(group.primary)">—</span>
                            <span v-else>{{ discountCell(group.primary) }}</span>
                        </PublicDocumentLineItemField>
                        <template v-if="showLineTax">
                            <PublicDocumentLineItemField label="Pre-tax" :value="formatCurrency(itemPreTax(group.primary))" />
                            <PublicDocumentLineItemField label="Tax" :value="formatCurrency(itemTax(group.primary))" />
                        </template>
                        <template #children>
                            <PublicDocumentLineItemCard
                                v-for="(opt, oi) in invoiceLineBoatOptions(group.primary)"
                                :key="`m-bo-opt-${group.primary.id}-${oi}`"
                                accent="sky"
                                :title="selectedOptionLabel(opt)"
                                :amount="formatCurrency(showLineTax ? optionTotalWithTax(opt) : opt.price)"
                            >
                                <PublicDocumentLineItemField label="Qty" value="1" />
                                <PublicDocumentLineItemField label="Unit price" :value="formatCurrency(opt.price)" />
                                <template v-if="showLineTax">
                                    <PublicDocumentLineItemField label="Pre-tax" :value="formatCurrency(optionPreTax(opt))" />
                                    <PublicDocumentLineItemField label="Tax" :value="formatCurrency(optionTax(opt))" />
                                </template>
                            </PublicDocumentLineItemCard>
                            <PublicDocumentLineItemCard
                                v-if="lineItemAddonsUiEnabled"
                                v-for="(add, ai) in group.flatAddons"
                                :key="`m-bo-ad-${add.id}-${ai}`"
                                muted
                                :title="flatAddonDisplayName(group.primary.name, add)"
                                :amount="formatCurrency(showLineTax ? itemTotalWithTax(add) : customerFacingLineTotal(add))"
                            >
                                <PublicDocumentLineItemField label="Qty" :value="add.quantity ?? 1" />
                                <PublicDocumentLineItemField label="Unit price" :value="formatCurrency(add.unit_price ?? add.price)" />
                                <PublicDocumentLineItemField label="Discount">
                                    <span v-if="isCoveredWarranty(add)">—</span>
                                    <span v-else>{{ discountCell(add) }}</span>
                                </PublicDocumentLineItemField>
                                <template v-if="showLineTax">
                                    <PublicDocumentLineItemField label="Pre-tax" :value="formatCurrency(itemPreTax(add))" />
                                    <PublicDocumentLineItemField label="Tax" :value="formatCurrency(itemTax(add))" />
                                </template>
                            </PublicDocumentLineItemCard>
                        </template>
                    </PublicDocumentLineItemCard>
                </template>
            </div>
            <p v-else class="py-6 text-center text-sm text-gray-500 md:hidden print:hidden">No line items</p>

            <table class="hidden w-full md:table print:table">
                <thead>
                    <tr class="border-b-2 border-gray-900">
                        <th class="py-3 pr-4 text-left text-sm font-semibold text-gray-900">Item</th>
                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Qty</th>
                        <th class="py-3 text-right text-sm font-semibold text-gray-900">Unit price</th>
                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Discount</th>
                        <th v-if="showLineTax" class="py-3 text-right text-sm font-semibold text-gray-900">Pre-tax</th>
                        <th v-if="showLineTax" class="py-3 text-right text-sm font-semibold text-gray-900">Tax</th>
                        <th class="py-3 text-right text-sm font-semibold text-gray-900">{{ showLineTax ? 'Total' : 'Line total' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template v-if="lineItems.length">
                        <template v-for="(group, gIdx) in groupedInvoiceLineItems" :key="`bo-${group.primary.id}-${gIdx}`">
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 pr-4 align-top">
                                    <div class="space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-gray-900">{{ itemPrimaryLabel(group.primary) }}</span>
                                            <span
                                                v-if="itemableBadge(group.primary)"
                                                class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                                            >
                                                {{ itemableBadge(group.primary) }}
                                            </span>
                                        </div>
                                        <div v-if="variantLabel(group.primary)" class="text-sm text-gray-600">
                                            <span class="font-medium text-gray-700">Variant:</span>
                                            {{ variantLabel(group.primary) }}
                                        </div>
                                        <div v-if="unitLabel(group.primary)" class="text-sm text-gray-600">
                                            <span class="font-medium text-gray-700">Unit:</span>
                                            {{ unitLabel(group.primary) }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-center align-top text-gray-900">{{ group.primary.quantity ?? 1 }}</td>
                                <td class="py-3 text-right align-top text-gray-900">
                                    <span v-if="isCoveredWarranty(group.primary)" class="text-sm text-blue-700">Covered under warranty</span>
                                    <span v-else>{{ formatCurrency(group.primary.unit_price ?? group.primary.price) }}</span>
                                </td>
                                <td class="py-3 text-center align-top text-gray-900">
                                    <span v-if="isCoveredWarranty(group.primary)">—</span>
                                    <span v-else>{{ discountCell(group.primary) }}</span>
                                </td>
                                <td v-if="showLineTax" class="py-3 text-right align-top text-gray-900">{{ formatCurrency(itemPreTax(group.primary)) }}</td>
                                <td v-if="showLineTax" class="py-3 text-right align-top text-gray-900">{{ formatCurrency(itemTax(group.primary)) }}</td>
                                <td class="py-3 text-right align-top font-medium text-gray-900">{{ formatCurrency(showLineTax ? itemTotalWithTax(group.primary) : customerFacingLineTotal(group.primary)) }}</td>
                            </tr>
                            <tr
                                v-for="(opt, oi) in invoiceLineBoatOptions(group.primary)"
                                :key="`bo-opt-${group.primary.id}-${oi}`"
                                class="bg-sky-50/50"
                            >
                                <td class="py-2 pl-6 pr-4 text-sm italic text-gray-700">
                                    <span class="text-sky-700">↳</span> {{ selectedOptionLabel(opt) }}
                                </td>
                                <td class="py-2 text-center text-sm text-gray-800">1</td>
                                <td class="py-2 text-right text-sm text-gray-800">{{ formatCurrency(opt.price) }}</td>
                                <td class="py-2 text-center text-sm text-gray-500">—</td>
                                <td v-if="showLineTax" class="py-2 text-right text-sm text-gray-800">{{ formatCurrency(optionPreTax(opt)) }}</td>
                                <td v-if="showLineTax" class="py-2 text-right text-sm text-gray-800">{{ formatCurrency(optionTax(opt)) }}</td>
                                <td class="py-2 text-right text-sm font-medium text-gray-900">{{ formatCurrency(showLineTax ? optionTotalWithTax(opt) : opt.price) }}</td>
                            </tr>
                            <tr
                                v-if="lineItemAddonsUiEnabled"
                                v-for="(add, ai) in group.flatAddons"
                                :key="`bo-ad-${add.id}-${ai}`"
                                class="bg-blue-50/40"
                            >
                                <td class="py-2 pl-6 pr-4 text-sm italic text-gray-700">
                                    ↳ {{ flatAddonDisplayName(group.primary.name, add) }}
                                </td>
                                <td class="py-2 text-center text-sm text-gray-800">{{ add.quantity ?? 1 }}</td>
                                <td class="py-2 text-right text-sm text-gray-800">{{ formatCurrency(add.unit_price ?? add.price) }}</td>
                                <td class="py-2 text-center text-sm text-gray-800">
                                    <span v-if="isCoveredWarranty(add)">—</span>
                                    <span v-else>{{ discountCell(add) }}</span>
                                </td>
                                <td v-if="showLineTax" class="py-2 text-right text-sm text-gray-800">{{ formatCurrency(itemPreTax(add)) }}</td>
                                <td v-if="showLineTax" class="py-2 text-right text-sm text-gray-800">{{ formatCurrency(itemTax(add)) }}</td>
                                <td class="py-2 text-right text-sm font-medium text-gray-900">{{ formatCurrency(showLineTax ? itemTotalWithTax(add) : customerFacingLineTotal(add)) }}</td>
                            </tr>
                        </template>
                    </template>
                    <tr v-else>
                        <td :colspan="lineItemColspan" class="py-8 text-center text-sm text-gray-500">No line items</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-8 py-6">
            <div class="flex justify-end">
                <div class="w-full space-y-3 md:w-1/2 lg:w-1/3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.subtotal) }}</span>
                    </div>
                    <div
                        v-if="record.discount_total && parseFloat(record.discount_total) !== 0"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-600">Discount:</span>
                        <span class="font-medium text-green-700">-{{ formatCurrency(record.discount_total) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.tax_total) }}</span>
                    </div>
                    <div
                        v-if="record.fees_total && parseFloat(record.fees_total) !== 0"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-600">Fees:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.fees_total) }}</span>
                    </div>
                    <div class="flex justify-between border-t-2 border-gray-900 pt-3 text-xl font-bold text-gray-900">
                        <span>Total:</span>
                        <span>{{ formatCurrency(record.total) }}</span>
                    </div>
                    <div
                        v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0"
                        class="flex justify-between text-sm text-green-700"
                    >
                        <span>Amount paid:</span>
                        <span>-{{ formatCurrency(record.amount_paid) }}</span>
                    </div>
                    <div
                        v-if="record.amount_due != null"
                        class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-bold text-gray-900"
                    >
                        <span>Amount due:</span>
                        <span>{{ formatCurrency(record.amount_due) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-else
        id="invoice-print-document"
        class="invoice-document-for-print bg-white text-gray-900 shadow-lg print:shadow-none"
    >
        <!-- Contract-style document header -->
        <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2 print:break-inside-avoid">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-6">
                    <div v-if="effectiveLogoUrl" class="flex-shrink-0">
                        <img :src="effectiveLogoUrl" alt="" class="h-20 w-auto max-w-[150px] object-contain">
                    </div>
                    <div v-else class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded bg-gray-200 print:hidden">
                        <span class="material-icons text-4xl text-gray-400">business</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ accountDisplayName }}</h1>
                        <p
                            v-if="record.transaction?.subsidiary?.display_name"
                            class="mt-1 text-sm font-semibold text-gray-700"
                        >
                            {{ record.transaction.subsidiary.display_name }}
                        </p>
                        <div
                            v-if="transactionLocationPreview"
                            class="mt-2 space-y-1 text-sm text-gray-600"
                        >
                            <p v-if="transactionLocationPreview.line1">
                                {{ transactionLocationPreview.line1
                                }}<span v-if="transactionLocationPreview.line2">, {{ transactionLocationPreview.line2 }}</span>
                            </p>
                            <p v-if="transactionLocationPreview.city">
                                {{ transactionLocationPreview.city
                                }}<span v-if="transactionLocationPreview.state">, {{ transactionLocationPreview.state }}</span>
                                <template v-if="transactionLocationPreview.postal"> {{ transactionLocationPreview.postal }}</template>
                            </p>
                            <p v-if="transactionLocationPreview.phone" class="flex items-center gap-1">
                                <span class="material-icons text-sm">phone</span>
                                {{ transactionLocationPreview.phone }}
                            </p>
                            <p v-if="transactionLocationPreview.email" class="flex items-center gap-1">
                                <span class="material-icons text-sm">email</span>
                                {{ transactionLocationPreview.email }}
                            </p>
                        </div>
                        <div
                            v-else-if="companyAddressLines.length"
                            class="mt-2 space-y-1 text-sm text-gray-600"
                        >
                            <p v-for="(addrLine, idx) in companyAddressLines" :key="idx" class="leading-snug">{{ addrLine }}</p>
                            <p v-if="companyPhone" class="flex items-center gap-1">
                                <span class="material-icons text-sm">phone</span>{{ companyPhone }}
                            </p>
                            <p v-if="companyEmail" class="flex items-center gap-1">
                                <span class="material-icons text-sm">email</span>{{ companyEmail }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium uppercase tracking-wide text-gray-600">Invoice</div>
                    <div class="font-mono text-3xl font-bold text-gray-900">
                        {{ invoiceHeaderTitle }}
                    </div>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ formatDate(record.created_at) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-gray-200 px-8 py-4">
            <span :class="['inline-flex items-center rounded-full px-3 py-1 text-sm font-medium', statusBadgeClass]">
                {{ statusLabel }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-6 border-b border-gray-200 bg-gray-50 px-8 py-6 print:bg-white md:grid-cols-2">
            <div>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Bill to
                </h2>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <address class="not-italic space-y-1 text-sm text-gray-700">
                        <span v-if="record.customer_name" class="block text-lg font-semibold text-gray-900">
                            {{ record.customer_name }}
                        </span>
                        <span v-if="record.customer_email" class="block">{{ record.customer_email }}</span>
                        <span v-if="record.customer_phone" class="block text-gray-600">{{ record.customer_phone }}</span>
                        <template v-if="record.billing_address_line1">
                            <span class="mt-2 block">{{ record.billing_address_line1 }}</span>
                            <span v-if="record.billing_address_line2" class="block">{{ record.billing_address_line2 }}</span>
                            <span class="block">{{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}</span>
                            <span v-if="record.billing_country" class="block text-gray-600">{{ record.billing_country }}</span>
                        </template>
                    </address>
                </div>
            </div>
            <div v-if="paymentTermLabel" class="md:text-right">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Payment terms
                </h2>
                <div class="rounded-lg border border-gray-200 bg-white p-4 md:inline-block md:min-w-[12rem] md:text-left">
                    <p class="text-sm font-medium text-gray-900">
                        {{ paymentTermLabel }}
                    </p>
                </div>
            </div>
        </div>

        <div v-if="record.notes" class="border-b border-gray-200 px-8 py-6">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Notes
            </h3>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700">
                {{ record.notes }}
            </p>
        </div>

        <div class="px-4 py-6 sm:px-8 print:break-inside-avoid">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Line items
            </h2>

            <div v-if="lineItems.length" class="mb-4 space-y-3 md:hidden print:hidden">
                <template v-for="(group, gIdx) in groupedInvoiceLineItems" :key="`m-inv-${group.primary.id}-${gIdx}`">
                    <PublicDocumentLineItemCard
                        :title="itemPrimaryLabel(group.primary)"
                        :amount="formatCurrency(showLineTax ? itemTotalWithTax(group.primary) : customerFacingLineTotal(group.primary))"
                    >
                        <span
                            v-if="itemableBadge(group.primary)"
                            class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                        >
                            {{ itemableBadge(group.primary) }}
                        </span>
                        <PublicDocumentLineItemField
                            v-if="variantLabel(group.primary)"
                            label="Variant"
                            :value="variantLabel(group.primary)"
                        />
                        <PublicDocumentLineItemField
                            v-if="unitLabel(group.primary)"
                            label="Unit"
                            :value="unitLabel(group.primary)"
                        />
                        <PublicDocumentLineItemField label="Qty" :value="group.primary.quantity ?? 1" />
                        <PublicDocumentLineItemField label="Unit price">
                            <span v-if="isCoveredWarranty(group.primary)" class="text-blue-700">Covered under warranty</span>
                            <span v-else>{{ formatCurrency(group.primary.unit_price ?? group.primary.price) }}</span>
                        </PublicDocumentLineItemField>
                        <PublicDocumentLineItemField label="Discount">
                            <span v-if="isCoveredWarranty(group.primary)">—</span>
                            <span v-else>{{ discountCell(group.primary) }}</span>
                        </PublicDocumentLineItemField>
                        <template v-if="showLineTax">
                            <PublicDocumentLineItemField label="Pre-tax" :value="formatCurrency(itemPreTax(group.primary))" />
                            <PublicDocumentLineItemField label="Tax" :value="formatCurrency(itemTax(group.primary))" />
                        </template>
                        <template #children>
                            <PublicDocumentLineItemCard
                                v-for="(opt, oi) in invoiceLineBoatOptions(group.primary)"
                                :key="`m-inv-opt-${group.primary.id}-${oi}`"
                                accent="sky"
                                :title="selectedOptionLabel(opt)"
                                :amount="formatCurrency(showLineTax ? optionTotalWithTax(opt) : opt.price)"
                            >
                                <PublicDocumentLineItemField label="Qty" value="1" />
                                <PublicDocumentLineItemField label="Unit price" :value="formatCurrency(opt.price)" />
                                <template v-if="showLineTax">
                                    <PublicDocumentLineItemField label="Pre-tax" :value="formatCurrency(optionPreTax(opt))" />
                                    <PublicDocumentLineItemField label="Tax" :value="formatCurrency(optionTax(opt))" />
                                </template>
                            </PublicDocumentLineItemCard>
                            <PublicDocumentLineItemCard
                                v-if="lineItemAddonsUiEnabled"
                                v-for="(add, ai) in group.flatAddons"
                                :key="`m-inv-ad-${add.id}-${ai}`"
                                muted
                                :title="flatAddonDisplayName(group.primary.name, add)"
                                :amount="formatCurrency(showLineTax ? itemTotalWithTax(add) : customerFacingLineTotal(add))"
                            >
                                <PublicDocumentLineItemField label="Qty" :value="add.quantity ?? 1" />
                                <PublicDocumentLineItemField label="Unit price" :value="formatCurrency(add.unit_price ?? add.price)" />
                                <PublicDocumentLineItemField label="Discount">
                                    <span v-if="isCoveredWarranty(add)">—</span>
                                    <span v-else>{{ discountCell(add) }}</span>
                                </PublicDocumentLineItemField>
                                <template v-if="showLineTax">
                                    <PublicDocumentLineItemField label="Pre-tax" :value="formatCurrency(itemPreTax(add))" />
                                    <PublicDocumentLineItemField label="Tax" :value="formatCurrency(itemTax(add))" />
                                </template>
                            </PublicDocumentLineItemCard>
                        </template>
                    </PublicDocumentLineItemCard>
                </template>
            </div>
            <p v-else class="py-6 text-center text-sm text-gray-500 md:hidden print:hidden">No line items</p>

            <div class="hidden overflow-x-auto border border-gray-200 md:block print:block print:border-0">
                <table class="w-full text-left text-sm text-gray-900">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold sm:px-6">Item</th>
                            <th class="px-4 py-3 font-semibold sm:px-6">Qty</th>
                            <th class="px-4 py-3 font-semibold sm:px-6">Unit price</th>
                            <th class="px-4 py-3 font-semibold sm:px-6">Discount</th>
                            <th v-if="showLineTax" class="px-4 py-3 text-right font-semibold sm:px-6">Pre-tax</th>
                            <th v-if="showLineTax" class="px-4 py-3 text-right font-semibold sm:px-6">Tax</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right font-semibold sm:px-6">{{ showLineTax ? 'Total' : 'Line total' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-if="lineItems.length">
                            <template v-for="(group, gIdx) in groupedInvoiceLineItems" :key="`inv-${group.primary.id}-${gIdx}`">
                                <tr class="bg-white">
                                    <th scope="row" class="px-4 py-4 align-top font-medium sm:px-6">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-base text-gray-900">{{ itemPrimaryLabel(group.primary) }}</span>
                                                <span
                                                    v-if="itemableBadge(group.primary)"
                                                    class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                                >
                                                    {{ itemableBadge(group.primary) }}
                                                </span>
                                            </div>
                                            <div v-if="variantLabel(group.primary)" class="text-xs text-gray-600">
                                                <span class="font-medium text-gray-700">Variant:</span>
                                                {{ variantLabel(group.primary) }}
                                            </div>
                                            <div v-if="unitLabel(group.primary)" class="text-xs text-gray-600">
                                                <span class="font-medium text-gray-700">Unit:</span>
                                                {{ unitLabel(group.primary) }}
                                            </div>
                                        </div>
                                    </th>
                                    <td class="px-4 py-4 align-top sm:px-6">{{ group.primary.quantity ?? 1 }}</td>
                                    <td class="px-4 py-4 align-top sm:px-6">
                                        <span v-if="isCoveredWarranty(group.primary)" class="text-sm text-blue-700">Covered under warranty</span>
                                        <span v-else>{{ formatCurrency(group.primary.unit_price ?? group.primary.price) }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-top sm:px-6">
                                        <span v-if="isCoveredWarranty(group.primary)">—</span>
                                        <span v-else>{{ discountCell(group.primary) }}</span>
                                    </td>
                                    <td v-if="showLineTax" class="px-4 py-4 text-right align-top sm:px-6">{{ formatCurrency(itemPreTax(group.primary)) }}</td>
                                    <td v-if="showLineTax" class="px-4 py-4 text-right align-top sm:px-6">{{ formatCurrency(itemTax(group.primary)) }}</td>
                                    <td class="px-4 py-4 text-right align-top font-medium sm:px-6">{{ formatCurrency(showLineTax ? itemTotalWithTax(group.primary) : customerFacingLineTotal(group.primary)) }}</td>
                                </tr>
                                <tr
                                    v-for="(opt, oi) in invoiceLineBoatOptions(group.primary)"
                                    :key="`inv-opt-${group.primary.id}-${oi}`"
                                    class="bg-sky-50/40"
                                >
                                    <td class="px-4 py-2 pl-8 text-sm italic text-gray-700 sm:px-6">
                                        <span class="text-sky-700">↳</span> {{ selectedOptionLabel(opt) }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-sm text-gray-800 sm:px-6">1</td>
                                    <td class="px-4 py-2 text-right text-sm text-gray-800 sm:px-6">{{ formatCurrency(opt.price) }}</td>
                                    <td class="px-4 py-2 text-center text-sm text-gray-500 sm:px-6">—</td>
                                    <td v-if="showLineTax" class="px-4 py-2 text-right text-sm text-gray-800 sm:px-6">{{ formatCurrency(optionPreTax(opt)) }}</td>
                                    <td v-if="showLineTax" class="px-4 py-2 text-right text-sm text-gray-800 sm:px-6">{{ formatCurrency(optionTax(opt)) }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-medium text-gray-900 sm:px-6">{{ formatCurrency(showLineTax ? optionTotalWithTax(opt) : opt.price) }}</td>
                                </tr>
                                <tr
                                    v-if="lineItemAddonsUiEnabled"
                                    v-for="(add, ai) in group.flatAddons"
                                    :key="`inv-ad-${add.id}-${ai}`"
                                    class="bg-blue-50/40"
                                >
                                    <td class="px-4 py-2 pl-8 text-sm italic text-gray-700 sm:px-6">
                                        ↳ {{ flatAddonDisplayName(group.primary.name, add) }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-sm sm:px-6">{{ add.quantity ?? 1 }}</td>
                                    <td class="px-4 py-2 text-right text-sm sm:px-6">{{ formatCurrency(add.unit_price ?? add.price) }}</td>
                                    <td class="px-4 py-2 text-center text-sm sm:px-6">
                                        <span v-if="isCoveredWarranty(add)">—</span>
                                        <span v-else>{{ discountCell(add) }}</span>
                                    </td>
                                    <td v-if="showLineTax" class="px-4 py-2 text-right text-sm sm:px-6">{{ formatCurrency(itemPreTax(add)) }}</td>
                                    <td v-if="showLineTax" class="px-4 py-2 text-right text-sm sm:px-6">{{ formatCurrency(itemTax(add)) }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-medium sm:px-6">{{ formatCurrency(showLineTax ? itemTotalWithTax(add) : customerFacingLineTotal(add)) }}</td>
                                </tr>
                            </template>
                        </template>
                        <tr v-else>
                            <td :colspan="lineItemColspan" class="px-6 py-8 text-center text-sm text-gray-500">No line items</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border-t border-gray-200 px-8 py-6">
            <div class="ms-auto max-w-xs">
                <h3 class="mb-3 font-semibold text-gray-900">Summary</h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.subtotal) }}</span>
                    </li>
                    <li v-if="record.discount_total && parseFloat(record.discount_total) !== 0" class="flex justify-between">
                        <span class="text-gray-500">Discount</span>
                        <span class="font-medium text-green-700">-{{ formatCurrency(record.discount_total) }}</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-500">Tax</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.tax_total) }}</span>
                    </li>
                    <li v-if="record.fees_total && parseFloat(record.fees_total) !== 0" class="flex justify-between">
                        <span class="text-gray-500">Fees</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.fees_total) }}</span>
                    </li>
                    <li class="flex justify-between border-t border-gray-900 pt-3 text-base font-bold text-gray-900">
                        <span>Total</span>
                        <span>{{ formatCurrency(record.total) }}</span>
                    </li>
                    <li v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0" class="flex justify-between text-green-700">
                        <span>Amount paid</span>
                        <span>-{{ formatCurrency(record.amount_paid) }}</span>
                    </li>
                    <li v-if="record.amount_due != null" class="flex justify-between border-t border-gray-900 pt-2 text-base font-bold text-gray-900">
                        <span>Amount due</span>
                        <span>{{ formatCurrency(record.amount_due) }}</span>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</template>