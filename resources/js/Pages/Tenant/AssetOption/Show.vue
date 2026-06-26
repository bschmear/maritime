<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import AssetOptionAssignmentsPanel from '@/Components/Tenant/AssetOptionAssignmentsPanel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: '' },
    recordTitle: { type: String, default: 'Asset Option' },
    domainName: { type: String, default: 'AssetOption' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const isDeleting = ref(false);
const isDeactivating = ref(false);
const showDeleteModal = ref(false);
const showInactiveOfferModal = ref(false);

const INPUT_TYPE_ENUM_KEY = 'App\\Enums\\AssetOption\\AssetOptionInputType';

const label = computed(() => props.record?.name || `Option #${props.record?.id}`);
const activeLabel = computed(() => (props.record?.active ? 'Active' : 'Inactive'));

const activeBadgeHeader = computed(() =>
    props.record?.active
        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
);

const activeBadgeOnBlue = computed(() =>
    props.record?.active
        ? 'bg-white/20 text-white border border-white/35'
        : 'bg-white/15 text-primary-100 border border-white/25',
);

const isGlobalOption = computed(() => !!props.record?.is_global);

const inputTypeLabel = computed(() => {
    const raw = props.record?.input_type;
    const opts = props.enumOptions?.[INPUT_TYPE_ENUM_KEY];
    if (Array.isArray(opts)) {
        const hit = opts.find((o) => o.id === raw);
        if (hit?.name) return hit.name;
    }
    const fallback = { select: 'Single select', color: 'Color', multi_select: 'Multi select', toggle: 'Toggle' };
    return fallback[raw] ?? raw ?? '—';
});

const optionInputType = computed(() => props.record?.input_type ?? '');
const isToggleInput = computed(() => optionInputType.value === 'toggle');

const toggleValue = computed(() => {
    if (!isToggleInput.value) return null;
    const vs = values.value;
    return vs.find((v) => v.value === 'on') ?? vs[0] ?? null;
});

const showsOptionValuesSection = computed(() =>
    ['select', 'multi_select', 'color'].includes(optionInputType.value),
);

function normalizeHex(hex) {
    if (!hex || typeof hex !== 'string') return '#000000';
    const h = hex.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(h)) return h.toLowerCase();
    if (/^[0-9a-fA-F]{6}$/.test(h)) return `#${h.toLowerCase()}`;
    return '#000000';
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
}

function yesNo(v) { return v ? 'Yes' : 'No'; }

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset options', href: route('asset-options.index') },
    { label: label.value },
]);

const values = computed(() => props.record?.all_values ?? props.record?.allValues ?? []);

const valueModalOpen = ref(false);
const editingValue = ref(null);
const valueForm = ref({
    label: '', value: '', color_hex: '', cost: null, price: null,
    sort_order: 0, is_default: false, active: true,
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const openNewValue = () => {
    editingValue.value = null;
    valueForm.value = {
        label: '', value: '', color_hex: '#2563eb', cost: null, price: null,
        sort_order: (values.value?.length || 0) * 10, is_default: false, active: true,
    };
    valueModalOpen.value = true;
};

function toNullableAmount(v) {
    if (v === '' || v === null || v === undefined) return null;
    const n = typeof v === 'number' ? v : Number(v);
    return Number.isFinite(n) ? n : null;
}

const openEditValue = (v) => {
    editingValue.value = v;
    valueForm.value = {
        label: v.label || '', value: v.value || '', color_hex: v.color_hex || '#2563eb',
        cost: toNullableAmount(v.cost), price: toNullableAmount(v.price),
        sort_order: v.sort_order ?? 0, is_default: !!v.is_default, active: !!v.active,
    };
    valueModalOpen.value = true;
};

const saveValue = async () => {
    const payload = { ...valueForm.value };
    payload.sort_order = editingValue.value?.sort_order ?? valueForm.value.sort_order ?? (values.value?.length || 0) * 10;
    if (optionInputType.value === 'color') {
        payload.color_hex = normalizeHex(payload.color_hex);
        payload.value = payload.color_hex || payload.label?.trim() || null;
    } else {
        payload.value = payload.value?.trim() || payload.label?.trim() || null;
    }
    const url = editingValue.value
        ? route('asset-options.values.update', { assetOption: props.record.id, value: editingValue.value.id })
        : route('asset-options.values.store', { assetOption: props.record.id });
    if (editingValue.value) {
        await axios.put(url, payload, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    } else {
        await axios.post(url, payload, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    }
    valueModalOpen.value = false;
    router.reload({ only: ['record'] });
};

const deleteValue = async (v) => {
    if (!confirm(`Delete value "${v.label}"?`)) return;
    await axios.delete(route('asset-options.values.destroy', { assetOption: props.record.id, value: v.id }), {
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    router.reload({ only: ['record'] });
};

const confirmDelete = async () => {
    isDeleting.value = true;
    try {
        await axios.delete(route('asset-options.destroy', { assetOption: props.record.id }), {
            headers: {
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        showDeleteModal.value = false;
        router.visit(route('asset-options.index'));
    } catch (error) {
        if (error?.response?.status === 422 && error?.response?.data?.offer_inactive) {
            showDeleteModal.value = false;
            showInactiveOfferModal.value = true;
            return;
        }

        const message = error?.response?.data?.message ?? 'Could not delete this asset option.';
        window.alert(message);
    } finally {
        isDeleting.value = false;
    }
};

const setInactive = async () => {
    isDeactivating.value = true;
    try {
        await axios.put(
            route('asset-options.update', { assetOption: props.record.id }),
            { active: false },
            {
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        showInactiveOfferModal.value = false;
        router.reload({ only: ['record'] });
    } catch (error) {
        const message = error?.response?.data?.message ?? 'Could not set this option to inactive.';
        window.alert(message);
    } finally {
        isDeactivating.value = false;
    }
};
</script>

<template>
    <Head :title="label" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ label }}</h2>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="activeBadgeHeader">
                            {{ activeLabel }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            href="#catalog-assignments"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-200 dark:hover:bg-primary-900/50"
                        >
                            <span class="material-icons text-[15px]">category</span>
                            Catalog assignments
                        </a>
                        <Link
                            :href="route('asset-options.edit', { assetOption: record.id })"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            <span class="material-icons text-[15px]">edit</span>
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[15px]">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex w-full flex-col space-y-4 p-4">

            <!-- ── Hero card ─────────────────────────────────────────── -->
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">

                <!-- Gradient header -->
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:from-primary-700 dark:to-primary-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-white">{{ record.name }}</h1>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="activeBadgeOnBlue">
                                {{ activeLabel }}
                            </span>
                            <!-- Global option badge — surfaced here -->
                            <span
                                v-if="isGlobalOption"
                                class="inline-flex items-center gap-1 rounded-full border border-white/30 bg-white/15 px-2.5 py-0.5 text-xs font-semibold text-white"
                            >
                                <span class="material-icons text-[13px]">public</span>
                                Global
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-wide text-primary-200/80">Input type</div>
                            <div class="text-base font-semibold text-white">{{ inputTypeLabel }}</div>
                        </div>
                    </div>
                </div>

                <!-- Definition fields — tight grid -->
                <div class="px-6 py-5">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.category?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Required</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ yesNo(record.is_required) }}</dd>
                        </div>
                        <div v-if="!isToggleInput">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Allow multiple</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ yesNo(record.allow_multiple) }}</dd>
                        </div>
                        <div v-if="!isToggleInput">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Min selections</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.min_select ?? '—' }}</dd>
                        </div>
                        <div v-if="!isToggleInput">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Max selections</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.max_select ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(record.updated_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Global option notice — inline below fields if global -->
                <div
                    v-if="isGlobalOption"
                    class="mx-6 mb-5 flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-800 dark:bg-sky-900/20"
                >
                    <span class="material-icons mt-0.5 shrink-0 text-[18px] text-sky-600 dark:text-sky-400">public</span>
                    <p class="text-sm text-sky-800 dark:text-sky-300">
                        This option is available on any transaction line via <strong>Add global option</strong> — no catalog assignment needed.
                    </p>
                </div>
            </div>

            <!-- ── Toggle pricing ────────────────────────────────────── -->
            <div
                v-if="isToggleInput"
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-3.5 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Pricing</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Applied when the customer includes this option.</p>
                </div>
                <dl class="grid grid-cols-2 gap-6 px-6 py-4">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cost <span class="font-normal normal-case">(internal)</span></dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ toggleValue?.cost ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Price <span class="font-normal normal-case">(add-on)</span></dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ toggleValue?.price ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- ── Values table ──────────────────────────────────────── -->
            <div
                v-if="showsOptionValuesSection"
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-6 py-3.5 dark:border-gray-700">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Values</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ values.length }} {{ values.length === 1 ? 'value' : 'values' }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                        @click="openNewValue"
                    >
                        <span class="material-icons text-[15px]">add</span>
                        Add value
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <th class="px-6 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Label</th>
                                <th class="px-6 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cost</th>
                                <th class="px-6 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Price</th>
                                <th class="px-6 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Active</th>
                                <th class="px-6 py-2.5"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                            <tr
                                v-for="v in values"
                                :key="v.id"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-6 py-3 text-gray-900 dark:text-white">
                                    <div class="flex items-center gap-2.5">
                                        <!-- Color swatch for color-type options -->
                                        <span
                                            v-if="optionInputType === 'color' && v.color_hex"
                                            class="inline-block h-4 w-4 shrink-0 rounded-full border border-gray-200 dark:border-gray-600"
                                            :style="{ background: normalizeHex(v.color_hex) }"
                                        />
                                        {{ v.label }}
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">{{ v.cost ?? '—' }}</td>
                                <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">{{ v.price ?? '—' }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="v.active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                    >
                                        {{ v.active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="openEditValue(v)">Edit</button>
                                    <button type="button" class="ml-3 text-xs font-medium text-red-600 hover:underline dark:text-red-400" @click="deleteValue(v)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!values.length" class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                        No values yet — click "Add value" to get started.
                    </p>
                </div>
            </div>

            <!-- ── Catalog assignments ───────────────────────────────── -->
            <AssetOptionAssignmentsPanel v-if="!isGlobalOption" :option-id="record.id" :record="record" />

        </div>

        <!-- ── Value modal ───────────────────────────────────────────── -->
        <Modal :show="valueModalOpen" max-width="lg" @close="valueModalOpen = false">
            <div class="flex max-h-[90vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ editingValue ? 'Edit value' : 'New value' }}
                    </h3>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="valueModalOpen = false"
                    >
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close</span>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-value-label">Label</label>
                        <input id="asset-option-value-label" v-model="valueForm.label" type="text" class="input-style w-full" autocomplete="off" />
                    </div>
                    <div v-if="optionInputType === 'color'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                        <div class="flex items-center gap-3">
                            <input
                                :value="normalizeHex(valueForm.color_hex)"
                                type="color"
                                class="h-10 w-12 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-gray-600 dark:bg-gray-900"
                                @input="(e) => { valueForm.color_hex = e.target.value }"
                            />
                            <span class="font-mono text-sm text-gray-600 dark:text-gray-300">{{ normalizeHex(valueForm.color_hex) }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-value-cost">Cost</label>
                            <p class="mb-1.5 text-xs text-gray-500 dark:text-gray-400">Internal — not shown to customers</p>
                            <CurrencyInput id="asset-option-value-cost" v-model="valueForm.cost" icon-position="right" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-value-price">Price</label>
                            <p class="mb-1.5 text-xs text-gray-500 dark:text-gray-400">Additional amount the customer pays</p>
                            <CurrencyInput id="asset-option-value-price" v-model="valueForm.price" icon-position="right" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="valueForm.is_default" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800" />
                            Default
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="valueForm.active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800" />
                            Active
                        </label>
                    </div>
                </div>

                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="valueModalOpen = false"
                    >Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                        @click="saveValue"
                    >Save</button>
                </div>
            </div>
        </Modal>

        <!-- ── Delete modal ──────────────────────────────────────────── -->
        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6">
                <p class="text-sm text-gray-800 dark:text-gray-200">Delete <strong>{{ label }}</strong>? This cannot be undone.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-300" @click="showDeleteModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >{{ isDeleting ? 'Deleting…' : 'Delete' }}</button>
                </div>
            </div>
        </Modal>

        <Modal :show="showInactiveOfferModal" max-width="md" @close="showInactiveOfferModal = false">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <span class="material-icons shrink-0 text-2xl text-amber-500 dark:text-amber-400">info</span>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Cannot delete option</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                            This option has been used on deals, estimates, or opportunities and cannot be deleted.
                            Would you like to set it to inactive instead? Inactive options remain on existing line items but won't be offered for new selections.
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300"
                        :disabled="isDeactivating"
                        @click="showInactiveOfferModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isDeactivating"
                        @click="setInactive"
                    >
                        {{ isDeactivating ? 'Saving…' : 'Set to inactive' }}
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>