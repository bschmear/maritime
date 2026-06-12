<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetOptionForm from '@/Components/Tenant/AssetOptionForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormValidationToast } from '@/composables/useFormValidationToast';

const INPUT_TYPE_ENUM = 'App\\Enums\\AssetOption\\AssetOptionInputType';

const FALLBACK_INPUT_TYPES = [
    { id: 'select', name: 'Single select' },
    { id: 'color', name: 'Color' },
    { id: 'multi_select', name: 'Multi select' },
    { id: 'toggle', name: 'Toggle' },
];

const props = defineProps({
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Asset Option' },
    domainName: { type: String, default: 'AssetOption' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset options', href: route('asset-options.index') },
    { label: 'New' },
]);

const inputTypeOptions = computed(() => {
    const list = props.enumOptions?.[INPUT_TYPE_ENUM];
    return Array.isArray(list) && list.length ? list : FALLBACK_INPUT_TYPES;
});

function boolVal(v) {
    return v === true || v === 1 || v === '1';
}

function normalizeHex(hex) {
    if (!hex || typeof hex !== 'string') {
        return '';
    }
    const h = hex.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(h)) {
        return h.toLowerCase();
    }
    if (/^[0-9a-fA-F]{6}$/.test(h)) {
        return `#${h.toLowerCase()}`;
    }

    return '';
}

function numOrNull(v) {
    if (v === '' || v === null || v === undefined) {
        return null;
    }
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
}

function buildValuesPayload(inputType, rows, mode) {
    const raw = Array.isArray(rows) ? rows : [];

    if (inputType === 'toggle') {
        return [];
    }

    if (inputType === 'color') {
        return raw
            .map((row, i) => {
                const hex = normalizeHex(row.color_hex) || '#000000';
                const label = (row.label && String(row.label).trim()) || `Color ${i + 1}`;

                const base = {
                    label,
                    value: (row.value && String(row.value).trim()) || hex,
                    color_hex: hex,
                    sort_order: row.sort_order !== undefined ? Number(row.sort_order) : i * 10,
                    cost: numOrNull(row.cost),
                    price: numOrNull(row.price),
                };
                if (mode === 'edit' && row.id) {
                    return { id: row.id, ...base };
                }

                return base;
            })
            .filter((row) => row.color_hex);
    }

    return raw
        .filter((row) => row.label && String(row.label).trim())
        .map((row, i) => {
            const base = {
                label: String(row.label).trim(),
                value: row.value && String(row.value).trim() ? String(row.value).trim() : null,
                color_hex: null,
                sort_order: row.sort_order !== undefined ? Number(row.sort_order) : i * 10,
                cost: numOrNull(row.cost),
                price: numOrNull(row.price),
            };
            if (mode === 'edit' && row.id) {
                return { id: row.id, ...base };
            }

            return base;
        });
}

const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const form = useForm({
    name: '',
    input_type: 'select',
    is_required: false,
    allow_multiple: false,
    min_select: 0,
    max_select: 1,
    active: true,
    values: [],
});

const fieldError = (key) => {
    const err = form.errors[key];
    return Array.isArray(err) ? err[0] : err;
};

const submit = () => {
    form
        .transform((data) => {
            const out = { ...data };
            out.is_required = boolVal(out.is_required);
            out.allow_multiple = boolVal(out.allow_multiple);
            out.active = boolVal(out.active);
            out.min_select = Number(out.min_select) || 0;
            out.max_select = Number(out.max_select) || 0;
            delete out.slug;
            out.values = buildValuesPayload(out.input_type, out.values, 'create');

            return out;
        })
        .post(route('asset-options.store'), validationSubmitOptions());
};

const cancel = () => router.visit(route('asset-options.index'));
</script>

<template>
    <Head title="New asset option" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">New asset option</h2>
            </div>
        </template>

        <div class="mx-auto w-full max-w-4xl px-4 py-6">
            <form class="space-y-6" @submit.prevent="submit">
                <AssetOptionForm :form="form" :field-error="fieldError" :input-type-options="inputTypeOptions" />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        :disabled="form.processing"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating…' : 'Create option' }}
                    </button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
