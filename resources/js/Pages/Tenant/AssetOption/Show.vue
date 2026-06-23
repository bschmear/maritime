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
    /** Passed by RecordController; unused on this page but kept for Inertia compatibility. */
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
const showDeleteModal = ref(false);

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

const inputTypeLabel = computed(() => {
    const raw = props.record?.input_type;
    const opts = props.enumOptions?.[INPUT_TYPE_ENUM_KEY];
    if (Array.isArray(opts)) {
        const hit = opts.find((o) => o.id === raw);
        if (hit?.name) {
            return hit.name;
        }
    }
    const fallback = {
        select: 'Single select',
        color: 'Color',
        multi_select: 'Multi select',
        toggle: 'Toggle',
    };

    return fallback[raw] ?? raw ?? '—';
});

const optionInputType = computed(() => props.record?.input_type ?? '');

const isToggleInput = computed(() => optionInputType.value === 'toggle');

/** Preset choices / colors only — not used for toggle. */
const showsOptionValuesSection = computed(() =>
    ['select', 'multi_select', 'color'].includes(optionInputType.value),
);

function normalizeHex(hex) {
    if (!hex || typeof hex !== 'string') {
        return '#000000';
    }
    const h = hex.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(h)) {
        return h.toLowerCase();
    }
    if (/^[0-9a-fA-F]{6}$/.test(h)) {
        return `#${h.toLowerCase()}`;
    }

    return '#000000';
}

function formatDate(value) {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function yesNo(v) {
    return v ? 'Yes' : 'No';
}

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset options', href: route('asset-options.index') },
    { label: label.value },
]);

const values = computed(() => props.record?.all_values ?? props.record?.allValues ?? []);

const valueModalOpen = ref(false);
const editingValue = ref(null);
const valueForm = ref({
    label: '',
    value: '',
    color_hex: '',
    cost: null,
    price: null,
    sort_order: 0,
    is_default: false,
    active: true,
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const openNewValue = () => {
    editingValue.value = null;
    valueForm.value = {
        label: '',
        value: '',
        color_hex: '#2563eb',
        cost: null,
        price: null,
        sort_order: (values.value?.length || 0) * 10,
        is_default: false,
        active: true,
    };
    valueModalOpen.value = true;
};

function toNullableAmount(v) {
    if (v === '' || v === null || v === undefined) {
        return null;
    }
    const n = typeof v === 'number' ? v : Number(v);

    return Number.isFinite(n) ? n : null;
}

const openEditValue = (v) => {
    editingValue.value = v;
    valueForm.value = {
        label: v.label || '',
        value: v.value || '',
        color_hex: v.color_hex || '#2563eb',
        cost: toNullableAmount(v.cost),
        price: toNullableAmount(v.price),
        sort_order: v.sort_order ?? 0,
        is_default: !!v.is_default,
        active: !!v.active,
    };
    valueModalOpen.value = true;
};

const saveValue = async () => {
    const payload = { ...valueForm.value };
    payload.sort_order =
        editingValue.value?.sort_order ??
        valueForm.value.sort_order ??
        (values.value?.length || 0) * 10;
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
        await axios.put(url, payload, {
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
    } else {
        await axios.post(url, payload, {
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
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
        await router.delete(route('asset-options.destroy', { assetOption: props.record.id }));
    } finally {
        isDeleting.value = false;
        showDeleteModal.value = false;
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
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ label }}</h2>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold"
                            :class="activeBadgeHeader"
                        >
                            {{ activeLabel }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            href="#catalog-assignments"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-100 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-200 dark:hover:bg-primary-900/50"
                        >
                            <span class="material-icons text-[16px]">category</span>
                            Catalog assignments
                        </a>
                        <Link
                            :href="route('asset-options.edit', { assetOption: record.id })"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit definition
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex w-full flex-col space-y-6 p-4">
            <div
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 dark:from-primary-700 dark:to-primary-800"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="mb-1 text-sm font-semibold uppercase tracking-wider text-primary-200/90">Asset option</p>
                            <div class="flex flex-wrap items-center gap-3">
                                <h1 class="text-2xl font-bold tracking-tight text-white">
                                    {{ record.name }}
                                </h1>
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-sm font-semibold"
                                    :class="activeBadgeOnBlue"
                                >
                                    {{ activeLabel }}
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <div class="text-sm font-semibold uppercase tracking-wide text-primary-200/90">Input type</div>
                            <div class="text-lg font-semibold leading-snug text-white">
                                {{ inputTypeLabel }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <h3
                                class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                Definition
                            </h3>
                            <div>
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Name
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.name ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Required
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ yesNo(record.is_required) }}
                                </div>
                            </div>
                            <div v-if="!isToggleInput">
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Allow multiple values
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ yesNo(record.allow_multiple) }}
                                </div>
                            </div>
                            <div v-if="!isToggleInput">
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Minimum selections
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.min_select ?? '—' }}
                                </div>
                            </div>
                            <div v-if="!isToggleInput">
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Maximum selections
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.max_select ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3
                                class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                Details
                            </h3>
                           
                            <div>
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Created
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatDate(record.created_at) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Updated
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatDate(record.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="showsOptionValuesSection"
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Values</h3>
                        <button
                            type="button"
                            class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                            @click="openNewValue"
                        >
                            Add value
                        </button>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                                <th class="py-2 pr-4 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Label</th>
                                <th class="py-2 pr-4 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Price</th>
                                <th class="py-2 pr-4 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Cost</th>
                                <th class="py-2 pr-4 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Active</th>
                                <th class="py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="v in values"
                                :key="v.id"
                                class="border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-700/80 dark:hover:bg-gray-700/30"
                            >
                                <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ v.label }}</td>
                                <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ v.price ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ v.cost ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ v.active ? 'Yes' : 'No' }}</td>
                                <td class="py-2 text-right">
                                    <button
                                        type="button"
                                        class="text-primary-600 hover:underline dark:text-primary-400"
                                        @click="openEditValue(v)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="ml-3 text-red-600 hover:underline dark:text-red-400"
                                        @click="deleteValue(v)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!values.length" class="py-6 text-center text-gray-500 dark:text-gray-400">No values yet.</p>
                    </div>
                </div>
            </div>

            <AssetOptionAssignmentsPanel :option-id="record.id" :record="record" />
        </div>

        <Modal :show="valueModalOpen" max-width="lg" @close="valueModalOpen = false">
            <div class="flex max-h-[90vh] flex-col">
                <div class="flex shrink-0 items-start justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ editingValue ? 'Edit value' : 'New value' }}
                    </h3>
                    <button
                        type="button"
                        class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="valueModalOpen = false"
                    >
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close</span>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-value-label">Label</label>
                        <input
                            id="asset-option-value-label"
                            v-model="valueForm.label"
                            type="text"
                            class="mt-1 input-style"
                            autocomplete="off"
                        />
                    </div>
                    <div v-if="optionInputType === 'color'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <input
                                :value="normalizeHex(valueForm.color_hex)"
                                type="color"
                                class="h-11 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 shadow-sm dark:border-gray-600 dark:bg-gray-900"
                                @input="(e) => { valueForm.color_hex = e.target.value }"
                            />
                            <span class="font-mono text-sm text-gray-600 dark:text-gray-300">{{ normalizeHex(valueForm.color_hex) }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-value-cost">Cost</label>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Internal — not shown to customers</p>
                            <div class="mt-1">
                                <CurrencyInput
                                    id="asset-option-value-cost"
                                    v-model="valueForm.cost"
                                    icon-position="right"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-value-price">Price</label>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Additional amount the customer pays</p>
                            <div class="mt-1">
                                <CurrencyInput
                                    id="asset-option-value-price"
                                    v-model="valueForm.price"
                                    icon-position="right"
                                />
                            </div>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            v-model="valueForm.is_default"
                            type="checkbox"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                        Default
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            v-model="valueForm.active"
                            type="checkbox"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                        Active
                    </label>
                </div>

                <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="valueModalOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                        @click="saveValue"
                    >
                        Save
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6">
                <p class="text-gray-800 dark:text-gray-200">Delete this asset option? This cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showDeleteModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
