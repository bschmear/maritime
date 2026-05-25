<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    addonLinePreTax,
    addonLineTax,
    assetLineCatalogTotal,
    assetOptionRowPreTax,
    assetOptionRowTax,
    lineAssetSelectedOptions,
    lineEffectiveUnitPrice,
    lineItemAssetCatalogId,
    lineItemCoreTotalWithTax,
    lineItemPreTaxTotal,
    lineItemRowKey,
    lineItemTaxOnBase,
    lineTotalWithAddons,
    lineUnitDisplay,
    lineUnitId,
    lineVariantDisplay,
    lineVariantId,
    partitionLineItemsByCatalogType,
    selectedOptionLabel,
} from '@/Utils/lineItemsFromEstimate';

const props = defineProps({
    /** Resolved deal or estimate line rows (same shape as Estimate primary lines). */
    items: { type: Array, default: () => [] },
    /** `formatMoney(amount)` — caller controls currency / locale. */
    formatMoney: { type: Function, required: true },
    /**
     * `tenant`: full light/dark classes for app surfaces (Contract show, Transaction show).
     * `paper`: light palette only for print / white document previews (avoids dark-mode text inversion).
     */
    variant: { type: String, default: 'tenant' },
    /** When true, section cards omit heavy shadow (nested inside another card). */
    embedded: { type: Boolean, default: false },
    /** Optional footer: tax rate label + grand total (e.g. contract line subtotal incl. tax). */
    showSummary: { type: Boolean, default: false },
    summaryTaxRatePercent: { type: Number, default: 0 },
    summaryGrandTotal: { type: [Number, String], default: 0 },
    emptyMessage: { type: String, default: 'No line items to show.' },
    /** When true, show Pre-tax / Tax / Total columns aligned with TransactionForm (deal view). */
    showPerLineDealTax: { type: Boolean, default: false },
    /** Deal tax rate percent (e.g. 8.25). Used when showPerLineDealTax is true. */
    dealTaxRatePercent: { type: Number, default: 0 },
});

const tenant = computed(() => props.variant === 'tenant');

/** Tailwind class bundles: `tenant` appends dark-mode pairs. */
const cx = (light, dark) => (tenant.value ? `${light} ${dark}` : light);

const cardShell = computed(() =>
    props.embedded
        ? cx(
            'rounded-lg border border-gray-200 bg-white overflow-hidden',
            'dark:border-gray-700 dark:bg-gray-800',
        )
        : cx(
            'bg-white shadow-lg sm:rounded-lg overflow-hidden border border-gray-200',
            'dark:bg-gray-800 dark:border-gray-700',
        ),
);

const headerBar = computed(() =>
    cx(
        'px-6 py-4 border-b border-gray-200',
        'dark:border-gray-700',
    ),
);

const sectionTitle = computed(() =>
    cx('text-base font-semibold text-gray-900', 'dark:text-white'),
);

const divide = computed(() =>
    cx('divide-y divide-gray-200', 'dark:divide-gray-700'),
);

const mobileCard = computed(() => 'p-4 space-y-3');

const labelMuted = computed(() =>
    cx('text-sm font-medium text-gray-500 uppercase tracking-wide', 'dark:text-gray-400'),
);

const textBody = computed(() =>
    cx('text-base text-gray-900', 'dark:text-white'),
);

const textMuted = computed(() =>
    cx('text-sm text-gray-500', 'dark:text-gray-400'),
);

const linkAsset = computed(() =>
    cx(
        'font-semibold text-base text-primary-600 hover:underline',
        'dark:text-primary-400',
    ),
);

const assetNameNoLink = computed(() =>
    cx('font-semibold text-base text-gray-900', 'dark:text-white'),
);

const subtotalBar = computed(() =>
    cx(
        'border-t-2 border-gray-200 bg-gray-50 p-4',
        'dark:border-gray-600 dark:bg-gray-700/50',
    ),
);

const tableWrap = computed(() => 'hidden md:block overflow-x-auto');

const thead = computed(() =>
    cx('bg-gray-50', 'dark:bg-gray-700/50'),
);

const th = computed(() =>
    cx(
        'px-4 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wide',
        'dark:text-gray-400',
    ),
);

const thRight = computed(() =>
    cx(
        'px-4 py-3 text-right text-sm font-semibold text-gray-500 uppercase tracking-wide',
        'dark:text-gray-400',
    ),
);

const tbodyDivide = computed(() =>
    cx('divide-y divide-gray-100', 'dark:divide-gray-700'),
);

const trHover = computed(() =>
    cx('hover:bg-gray-50 transition-colors', 'dark:hover:bg-gray-700/30'),
);

const emptyIconWrap = computed(() =>
    cx('flex flex-col items-center justify-center py-12 text-center px-6', ''),
);

const emptyIcon = computed(() =>
    cx('w-10 h-10 text-gray-300 mb-3', 'dark:text-gray-600'),
);

const emptyText = computed(() =>
    cx('text-sm text-gray-400', 'dark:text-gray-500'),
);

const partitioned = computed(() => partitionLineItemsByCatalogType(props.items));

const assetLines = computed(() => partitioned.value.assetLines);
const inventoryLines = computed(() => partitioned.value.inventoryLines);
const otherLines = computed(() => partitioned.value.otherLines);

const assetSubtotal = computed(() =>
    assetLines.value.reduce((sum, item) => sum + lineTotalWithAddons(item), 0),
);

const inventorySubtotal = computed(() =>
    inventoryLines.value.reduce((sum, item) => sum + lineTotalWithAddons(item), 0),
);

const otherSubtotal = computed(() =>
    otherLines.value.reduce((sum, item) => sum + lineTotalWithAddons(item), 0),
);

const hasAnyLines = computed(
    () =>
        assetLines.value.length > 0 ||
        inventoryLines.value.length > 0 ||
        otherLines.value.length > 0,
);

const dealTaxMode = computed(() => props.showPerLineDealTax);
const dealTaxR = computed(() => Number(props.dealTaxRatePercent) || 0);

const assetMainRowTotal = (item) =>
    dealTaxMode.value ? lineItemCoreTotalWithTax(item, dealTaxR.value) : assetLineCatalogTotal(item);

const inventoryMainRowTotal = (item) =>
    dealTaxMode.value ? lineItemCoreTotalWithTax(item, dealTaxR.value) : lineItemPreTaxTotal(item);

const assetDealRollup = computed(() => {
    if (!dealTaxMode.value) {
        return null;
    }
    const r = dealTaxR.value;
    let pre = 0;
    let tax = 0;
    for (const item of assetLines.value) {
        pre += lineItemPreTaxTotal(item);
        tax += lineItemTaxOnBase(item, r);
        for (const opt of lineAssetSelectedOptions(item)) {
            pre += assetOptionRowPreTax(opt);
            tax += assetOptionRowTax(opt, r);
        }
        for (const addon of item.addons || []) {
            pre += addonLinePreTax(addon);
            tax += addonLineTax(addon, r);
        }
    }
    return { pre, tax, total: pre + tax };
});

const inventoryDealRollup = computed(() => {
    if (!dealTaxMode.value) {
        return null;
    }
    const r = dealTaxR.value;
    let pre = 0;
    let tax = 0;
    for (const item of inventoryLines.value) {
        pre += lineItemPreTaxTotal(item);
        tax += lineItemTaxOnBase(item, r);
        for (const addon of item.addons || []) {
            pre += addonLinePreTax(addon);
            tax += addonLineTax(addon, r);
        }
    }
    return { pre, tax, total: pre + tax };
});
</script>

<template>
    <div v-if="!hasAnyLines" :class="cx('rounded-lg border border-dashed border-gray-300 py-10 text-center text-md text-gray-500', 'dark:border-gray-600 dark:text-gray-400')">
        {{ emptyMessage }}
    </div>

    <div v-else class="space-y-6">
        <!-- Assets -->
        <div :class="cardShell">
            <div :class="headerBar">
                <h2 :class="sectionTitle">Assets</h2>
            </div>

            <!-- Mobile: asset cards -->
            <div v-if="assetLines.length > 0" :class="['block md:hidden', divide]">
                <div
                    v-for="(item, index) in assetLines"
                    :key="`asset-m-${lineItemRowKey(item) ?? index}-${index}`"
                    :class="mobileCard"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <Link
                                v-if="lineItemAssetCatalogId(item)"
                                :href="route('assets.show', lineItemAssetCatalogId(item))"
                                :class="linkAsset"
                            >
                                {{ item.name }}
                            </Link>
                            <div v-else :class="assetNameNoLink">{{ item.name }}</div>
                            <div v-if="item.itemable?.make?.display_name" :class="[textMuted, 'mt-1']">
                                {{ item.itemable.make.display_name }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div :class="[textBody, 'font-semibold tabular-nums']">
                                {{ formatMoney(assetMainRowTotal(item)) }}
                            </div>
                            <div :class="textMuted">{{ dealTaxMode ? 'Line total (incl. tax on base)' : 'Line total' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <div :class="labelMuted">Variant</div>
                            <div :class="textBody">
                                <span v-if="lineVariantId(item)">{{ lineVariantDisplay(item) }}</span>
                                <span v-else :class="cx('text-gray-400', 'dark:text-gray-500')">—</span>
                            </div>
                        </div>
                        <div>
                            <div :class="labelMuted">Unit</div>
                            <div :class="textBody">
                                <span v-if="lineUnitId(item)">{{ lineUnitDisplay(item) }}</span>
                                <span v-else :class="cx('text-gray-400', 'dark:text-gray-500')">—</span>
                            </div>
                        </div>
                        <div>
                            <div :class="labelMuted">Year</div>
                            <div :class="textBody">{{ item.itemable?.year || '—' }}</div>
                        </div>
                        <div>
                            <div :class="labelMuted">Unit price</div>
                            <div :class="[textBody, 'tabular-nums']">{{ formatMoney(lineEffectiveUnitPrice(item)) }}</div>
                        </div>
                        <div>
                            <div :class="labelMuted">Discount</div>
                            <div
                                class="text-base tabular-nums"
                                :class="item.discount > 0 ? 'text-red-600 dark:text-red-400' : textBody"
                            >
                                {{ item.discount > 0 ? `-${formatMoney(item.discount)}` : '—' }}
                            </div>
                        </div>
                        <div>
                            <div :class="labelMuted">Qty</div>
                            <div :class="textBody">{{ item.quantity }}</div>
                        </div>
                        <template v-if="dealTaxMode">
                            <div>
                                <div :class="labelMuted">Pre-tax</div>
                                <div :class="[textBody, 'tabular-nums']">{{ formatMoney(lineItemPreTaxTotal(item)) }}</div>
                            </div>
                            <div>
                                <div :class="labelMuted">Tax</div>
                                <div :class="[textBody, 'tabular-nums']">{{ formatMoney(lineItemTaxOnBase(item, dealTaxR)) }}</div>
                            </div>
                        </template>
                    </div>
                    <div
                        v-if="lineAssetSelectedOptions(item).length > 0"
                        class="pl-3 space-y-2 border-l-2 border-sky-200 dark:border-sky-700"
                    >
                        <div
                            v-for="(opt, optIdx) in lineAssetSelectedOptions(item)"
                            :key="`asset-m-opt-${lineItemRowKey(item) ?? optIdx}-${optIdx}`"
                            :class="cx('flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700', 'dark:text-gray-300')"
                        >
                            <span>
                                <span class="text-sky-600/90 dark:text-sky-400 mr-1">↳</span>{{ selectedOptionLabel(opt) }}
                            </span>
                            <span :class="cx('font-medium text-gray-900 tabular-nums shrink-0', 'dark:text-white')">
                                {{ formatMoney(opt.price) }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-if="item.addons && item.addons.length > 0"
                        class="pl-3 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                    >
                        <div
                            v-for="(addon, addonIdx) in item.addons"
                            :key="`asset-m-addon-${lineItemRowKey(item) ?? addonIdx}-${addonIdx}`"
                            class="flex flex-wrap items-center justify-between gap-2 text-sm"
                        >
                            <div :class="cx('text-gray-600 italic min-w-0', 'dark:text-gray-400')">
                                ↳ {{ addon.name }} (× {{ addon.quantity }})
                            </div>
                            <span :class="cx('font-medium text-gray-900 tabular-nums shrink-0', 'dark:text-white')">
                                {{ formatMoney(Number(addon.price) * Number(addon.quantity)) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div :class="subtotalBar">
                    <template v-if="dealTaxMode && assetDealRollup">
                        <div class="flex justify-between text-sm">
                            <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Pre-tax</span>
                            <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(assetDealRollup.pre) }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Tax</span>
                            <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(assetDealRollup.tax) }}</span>
                        </div>
                        <div class="flex justify-between text-base mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                            <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Total</span>
                            <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(assetDealRollup.total) }}</span>
                        </div>
                    </template>
                    <div v-else class="flex justify-between text-base">
                        <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Assets Subtotal</span>
                        <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(assetSubtotal) }}</span>
                    </div>
                </div>
            </div>

            <!-- Desktop: assets table -->
            <div v-if="assetLines.length > 0" :class="tableWrap">
                <table class="w-full text-sm">
                    <thead :class="thead">
                        <tr>
                            <th :class="th">Asset</th>
                            <th :class="[th, 'min-w-[7rem]']">Variant</th>
                            <th :class="[th, 'min-w-[7rem]']">Unit</th>
                            <th :class="[th, 'w-24']">Year</th>
                            <th :class="[thRight, 'w-28']">Unit Price</th>
                            <th :class="[thRight, 'w-24']">Discount</th>
                            <th :class="[thRight, 'w-20']">Qty</th>
                            <th v-if="dealTaxMode" :class="[thRight, 'w-28']">Pre-tax</th>
                            <th v-if="dealTaxMode" :class="[thRight, 'w-24']">Tax</th>
                            <th :class="[thRight, 'w-28']">Total</th>
                        </tr>
                    </thead>
                    <tbody :class="tbodyDivide">
                        <template v-for="(item, index) in assetLines" :key="`asset-d-${lineItemRowKey(item) ?? index}`">
                            <tr :class="trHover">
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="lineItemAssetCatalogId(item)"
                                        :href="route('assets.show', lineItemAssetCatalogId(item))"
                                        :class="cx('font-medium text-primary-600 hover:underline', 'dark:text-primary-400')"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <div v-else :class="cx('font-medium text-gray-900', 'dark:text-white')">{{ item.name }}</div>
                                    <div v-if="item.itemable?.make?.display_name" :class="cx('text-sm text-gray-400', 'dark:text-gray-500')">
                                        {{ item.itemable.make.display_name }}
                                    </div>
                                </td>
                                <td :class="cx('px-4 py-3 text-sm text-gray-600', 'dark:text-gray-300')">
                                    <span v-if="lineVariantId(item)" :class="cx('font-medium text-gray-800', 'dark:text-gray-200')">
                                        {{ lineVariantDisplay(item) }}
                                    </span>
                                    <span v-else :class="cx('text-gray-400', 'dark:text-gray-500')">—</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span v-if="lineUnitId(item)" :class="cx('font-medium text-gray-800', 'dark:text-gray-200')">
                                        {{ lineUnitDisplay(item) }}
                                    </span>
                                    <span v-else :class="cx('text-gray-400', 'dark:text-gray-500')">—</span>
                                </td>
                                <td :class="cx('px-4 py-3 text-gray-500', 'dark:text-gray-400')">{{ item.itemable?.year || '—' }}</td>
                                <td :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')">{{ formatMoney(lineEffectiveUnitPrice(item)) }}</td>
                                <td class="px-4 py-3 text-right text-red-500 dark:text-red-400">
                                    {{ item.discount > 0 ? `-${formatMoney(item.discount)}` : '—' }}
                                </td>
                                <td :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')">{{ item.quantity }}</td>
                                <td
                                    v-if="dealTaxMode"
                                    :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')"
                                >
                                    {{ formatMoney(lineItemPreTaxTotal(item)) }}
                                </td>
                                <td
                                    v-if="dealTaxMode"
                                    :class="cx('px-4 py-3 text-right text-gray-600', 'dark:text-gray-400')"
                                >
                                    {{ formatMoney(lineItemTaxOnBase(item, dealTaxR)) }}
                                </td>
                                <td :class="cx('px-4 py-3 text-right font-semibold text-gray-900', 'dark:text-white')">
                                    {{ formatMoney(assetMainRowTotal(item)) }}
                                </td>
                            </tr>
                            <tr
                                v-for="(opt, optIdx) in lineAssetSelectedOptions(item)"
                                :key="`asset-opt-${lineItemRowKey(item) ?? optIdx}-${optIdx}`"
                                class="bg-sky-50/70 dark:bg-sky-900/20"
                            >
                                <td
                                    class="pl-10 pr-4 py-2 text-sm text-gray-700 dark:text-gray-300"
                                    :colspan="dealTaxMode ? 7 : 4"
                                >
                                    <span class="text-sky-600/90 dark:text-sky-400 mr-1">↳</span>{{ selectedOptionLabel(opt) }}
                                </td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                <td
                                    v-if="dealTaxMode"
                                    class="px-4 py-2 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ formatMoney(assetOptionRowPreTax(opt)) }}
                                </td>
                                <td
                                    v-if="dealTaxMode"
                                    class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ formatMoney(assetOptionRowTax(opt, dealTaxR)) }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{
                                        formatMoney(
                                            dealTaxMode
                                                ? assetOptionRowPreTax(opt) + assetOptionRowTax(opt, dealTaxR)
                                                : Number(opt.price || 0),
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="(addon, addonIdx) in (item.addons || [])"
                                :key="`asset-addon-${lineItemRowKey(item) ?? index}-${addonIdx}`"
                                class="bg-primary-50/40 dark:bg-primary-900/10"
                            >
                                <td
                                    class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic"
                                    :colspan="dealTaxMode ? 7 : 4"
                                >
                                    ↳ {{ addon.name }}
                                </td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                <td
                                    v-if="dealTaxMode"
                                    class="px-4 py-2 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ formatMoney(addonLinePreTax(addon)) }}
                                </td>
                                <td
                                    v-if="dealTaxMode"
                                    class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ formatMoney(addonLineTax(addon, dealTaxR)) }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{
                                        formatMoney(
                                            dealTaxMode
                                                ? addonLinePreTax(addon) + addonLineTax(addon, dealTaxR)
                                                : Number(addon.price) * Number(addon.quantity),
                                        )
                                    }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot :class="cx('bg-gray-50 border-t-2 border-gray-200', 'dark:bg-gray-700/50 dark:border-gray-600')">
                        <tr v-if="dealTaxMode && assetDealRollup">
                            <td colspan="7" :class="cx('px-4 py-3 text-right text-sm font-semibold text-gray-700', 'dark:text-gray-300')">
                                Assets subtotal (pre-tax)
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(assetDealRollup.pre) }}
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(assetDealRollup.tax) }}
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(assetDealRollup.total) }}
                            </td>
                        </tr>
                        <tr v-else>
                            <td colspan="7" :class="cx('px-4 py-3 text-right text-sm font-semibold text-gray-700', 'dark:text-gray-300')">
                                Assets Subtotal
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(assetSubtotal) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-else :class="emptyIconWrap">
                <svg :class="emptyIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <p :class="emptyText">No assets on this order</p>
            </div>
        </div>

        <!-- Parts & Accessories -->
        <div :class="cardShell">
            <div :class="headerBar">
                <h2 :class="sectionTitle">Parts &amp; Accessories</h2>
            </div>

            <div v-if="inventoryLines.length > 0" :class="['block md:hidden', divide]">
                <div
                    v-for="(item, index) in inventoryLines"
                    :key="`inv-m-${lineItemRowKey(item) ?? index}-${index}`"
                    :class="mobileCard"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div :class="assetNameNoLink">{{ item.name }}</div>
                            <div v-if="item.itemable?.sku" :class="[textMuted, 'mt-1 font-mono']">
                                SKU {{ item.itemable.sku }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div :class="[textBody, 'font-semibold tabular-nums']">
                                {{ formatMoney(inventoryMainRowTotal(item)) }}
                            </div>
                            <div :class="textMuted">{{ dealTaxMode ? 'Line total (incl. tax on base)' : 'Line total' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <div :class="labelMuted">Unit price</div>
                            <div :class="[textBody, 'tabular-nums']">{{ formatMoney(lineEffectiveUnitPrice(item)) }}</div>
                        </div>
                        <div>
                            <div :class="labelMuted">Discount</div>
                            <div
                                class="text-base tabular-nums"
                                :class="item.discount > 0 ? 'text-red-600 dark:text-red-400' : textBody"
                            >
                                {{ item.discount > 0 ? `-${formatMoney(item.discount)}` : '—' }}
                            </div>
                        </div>
                        <div>
                            <div :class="labelMuted">Qty</div>
                            <div :class="textBody">{{ item.quantity }}</div>
                        </div>
                        <template v-if="dealTaxMode">
                            <div>
                                <div :class="labelMuted">Pre-tax</div>
                                <div :class="[textBody, 'tabular-nums']">{{ formatMoney(lineItemPreTaxTotal(item)) }}</div>
                            </div>
                            <div>
                                <div :class="labelMuted">Tax</div>
                                <div :class="[textBody, 'tabular-nums']">{{ formatMoney(lineItemTaxOnBase(item, dealTaxR)) }}</div>
                            </div>
                        </template>
                    </div>
                    <div
                        v-if="item.addons && item.addons.length > 0"
                        class="pl-3 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                    >
                        <div
                            v-for="(addon, addonIdx) in item.addons"
                            :key="`inv-m-addon-${lineItemRowKey(item) ?? addonIdx}-${addonIdx}`"
                            class="flex flex-wrap items-start justify-between gap-2 text-sm"
                        >
                            <div :class="cx('text-gray-600 italic min-w-0', 'dark:text-gray-400')">
                                ↳ {{ addon.name }} (× {{ addon.quantity }})
                            </div>
                            <span :class="cx('font-medium text-gray-900 tabular-nums shrink-0', 'dark:text-white')">
                                {{ formatMoney(Number(addon.price) * Number(addon.quantity)) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div :class="subtotalBar">
                    <template v-if="dealTaxMode && inventoryDealRollup">
                        <div class="flex justify-between text-sm">
                            <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Pre-tax</span>
                            <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(inventoryDealRollup.pre) }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Tax</span>
                            <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(inventoryDealRollup.tax) }}</span>
                        </div>
                        <div class="flex justify-between text-base mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                            <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Total</span>
                            <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(inventoryDealRollup.total) }}</span>
                        </div>
                    </template>
                    <div v-else class="flex justify-between text-base">
                        <span :class="cx('font-semibold text-gray-700', 'dark:text-gray-300')">Parts &amp; Accessories Subtotal</span>
                        <span :class="cx('font-bold text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(inventorySubtotal) }}</span>
                    </div>
                </div>
            </div>

            <div v-if="inventoryLines.length > 0" :class="tableWrap">
                <table class="w-full text-sm">
                    <thead :class="thead">
                        <tr>
                            <th :class="th">Item</th>
                            <th :class="[th, 'w-24']">SKU</th>
                            <th :class="[thRight, 'w-24']">Unit Price</th>
                            <th :class="[thRight, 'w-24']">Discount</th>
                            <th :class="[thRight, 'w-20']">Qty</th>
                            <th v-if="dealTaxMode" :class="[thRight, 'w-28']">Pre-tax</th>
                            <th v-if="dealTaxMode" :class="[thRight, 'w-24']">Tax</th>
                            <th :class="[thRight, 'w-28']">Total</th>
                        </tr>
                    </thead>
                    <tbody :class="tbodyDivide">
                        <template v-for="(item, index) in inventoryLines" :key="`inv-d-${lineItemRowKey(item) ?? index}`">
                            <tr :class="trHover">
                                <td :class="cx('px-4 py-3 font-medium text-gray-900', 'dark:text-white')">{{ item.name }}</td>
                                <td :class="cx('px-4 py-3 text-gray-500 font-mono text-sm', 'dark:text-gray-400')">{{ item.itemable?.sku || '—' }}</td>
                                <td :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')">{{ formatMoney(lineEffectiveUnitPrice(item)) }}</td>
                                <td class="px-4 py-3 text-right text-red-500 dark:text-red-400">
                                    {{ item.discount > 0 ? `-${formatMoney(item.discount)}` : '—' }}
                                </td>
                                <td :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')">{{ item.quantity }}</td>
                                <td
                                    v-if="dealTaxMode"
                                    :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')"
                                >
                                    {{ formatMoney(lineItemPreTaxTotal(item)) }}
                                </td>
                                <td
                                    v-if="dealTaxMode"
                                    :class="cx('px-4 py-3 text-right text-gray-600', 'dark:text-gray-400')"
                                >
                                    {{ formatMoney(lineItemTaxOnBase(item, dealTaxR)) }}
                                </td>
                                <td :class="cx('px-4 py-3 text-right font-semibold text-gray-900', 'dark:text-white')">
                                    {{ formatMoney(inventoryMainRowTotal(item)) }}
                                </td>
                            </tr>
                            <tr
                                v-for="(addon, addonIdx) in (item.addons || [])"
                                :key="`inv-addon-${lineItemRowKey(item) ?? index}-${addonIdx}`"
                                class="bg-primary-50/40 dark:bg-primary-900/10"
                            >
                                <td
                                    class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic"
                                    :colspan="dealTaxMode ? 5 : 2"
                                >
                                    ↳ {{ addon.name }}
                                </td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                <td v-if="!dealTaxMode" class="px-4 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                <td
                                    v-if="dealTaxMode"
                                    class="px-4 py-2 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ formatMoney(addonLinePreTax(addon)) }}
                                </td>
                                <td
                                    v-if="dealTaxMode"
                                    class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ formatMoney(addonLineTax(addon, dealTaxR)) }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{
                                        formatMoney(
                                            dealTaxMode
                                                ? addonLinePreTax(addon) + addonLineTax(addon, dealTaxR)
                                                : Number(addon.price) * Number(addon.quantity),
                                        )
                                    }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot :class="cx('bg-gray-50 border-t-2 border-gray-200', 'dark:bg-gray-700/50 dark:border-gray-600')">
                        <tr v-if="dealTaxMode && inventoryDealRollup">
                            <td colspan="5" :class="cx('px-4 py-3 text-right text-sm font-semibold text-gray-700', 'dark:text-gray-300')">
                                Parts &amp; accessories (pre-tax)
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(inventoryDealRollup.pre) }}
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(inventoryDealRollup.tax) }}
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(inventoryDealRollup.total) }}
                            </td>
                        </tr>
                        <tr v-else>
                            <td colspan="5" :class="cx('px-4 py-3 text-right text-sm font-semibold text-gray-700', 'dark:text-gray-300')">
                                Parts &amp; Accessories Subtotal
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(inventorySubtotal) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-else :class="emptyIconWrap">
                <svg :class="emptyIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <p :class="emptyText">No parts or accessories on this order</p>
            </div>
        </div>

        <!-- Other line types (fees, labor, etc.) -->
        <div v-if="otherLines.length > 0" :class="cardShell">
            <div :class="headerBar">
                <h2 :class="sectionTitle">Additional line items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead :class="thead">
                        <tr>
                            <th :class="th">Item</th>
                            <th :class="[thRight, 'w-20']">Qty</th>
                            <th :class="[thRight, 'w-28']">Unit price</th>
                            <th :class="[thRight, 'w-28']">Total</th>
                        </tr>
                    </thead>
                    <tbody :class="tbodyDivide">
                        <tr v-for="(item, index) in otherLines" :key="`other-${lineItemRowKey(item) ?? index}`" :class="trHover">
                            <td :class="cx('px-4 py-3 font-medium text-gray-900', 'dark:text-white')">{{ item.name }}</td>
                            <td :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')">{{ item.quantity }}</td>
                            <td :class="cx('px-4 py-3 text-right text-gray-700', 'dark:text-gray-300')">{{ formatMoney(lineEffectiveUnitPrice(item)) }}</td>
                            <td :class="cx('px-4 py-3 text-right font-semibold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(lineItemPreTaxTotal(item)) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot :class="cx('bg-gray-50 border-t-2 border-gray-200', 'dark:bg-gray-700/50 dark:border-gray-600')">
                        <tr>
                            <td colspan="3" :class="cx('px-4 py-3 text-right text-sm font-semibold text-gray-700', 'dark:text-gray-300')">
                                Subtotal
                            </td>
                            <td :class="cx('px-4 py-3 text-right text-base font-bold text-gray-900', 'dark:text-white')">
                                {{ formatMoney(otherSubtotal) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div
            v-if="showSummary"
            :class="cx('rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm space-y-1', 'dark:border-gray-700 dark:bg-gray-900/30')"
        >
            <div v-if="summaryTaxRatePercent > 0" :class="cx('text-right text-gray-600', 'dark:text-gray-400')">
                Tax rate: {{ summaryTaxRatePercent }}%
            </div>
            <div class="flex flex-wrap justify-end gap-3 text-base font-semibold">
                <span :class="cx('text-gray-700', 'dark:text-gray-300')">Subtotal (lines)</span>
                <span :class="cx('text-gray-900 tabular-nums', 'dark:text-white')">{{ formatMoney(summaryGrandTotal) }}</span>
            </div>
        </div>
    </div>
</template>
