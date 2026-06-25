<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetOptionForm from '@/Components/Tenant/AssetOptionForm.vue';
import AssetOptionAssignmentsPanel from '@/Components/Tenant/AssetOptionAssignmentsPanel.vue';
import FormFixedActionBar from '@/Components/Tenant/FormComponents/FormFixedActionBar.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance } from 'vue';

const INPUT_TYPE_ENUM = 'App\\Enums\\AssetOption\\AssetOptionInputType';

const FALLBACK_INPUT_TYPES = [
    { id: 'select', name: 'Single select' },
    { id: 'color', name: 'Color' },
    { id: 'multi_select', name: 'Multi select' },
    { id: 'toggle', name: 'Toggle' },
];

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Asset Option' },
    domainName: { type: String, default: 'AssetOption' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const label = computed(() => props.record?.name || `Option #${props.record?.id}`);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset options', href: route('asset-options.index') },
    { label: label.value, href: route('asset-options.show', { assetOption: props.record.id }) },
    { label: 'Edit' },
]);

const inputTypeOptions = computed(() => {
    const list = props.enumOptions?.[INPUT_TYPE_ENUM];
    return Array.isArray(list) && list.length ? list : FALLBACK_INPUT_TYPES;
});

function numOrNull(v) {
    if (v === '' || v === null || v === undefined) {
        return null;
    }
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
}

function mapRecordValues(record) {
    const vs = record?.all_values ?? record?.allValues ?? [];

    return vs.map((v) => ({
        id: v.id,
        label: v.label ?? '',
        value: v.value ?? '',
        color_hex: v.color_hex || '#2563eb',
        sort_order: v.sort_order ?? 0,
        cost: v.cost ?? '',
        price: v.price ?? '',
    }));
}

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

function buildValuesPayload(inputType, rows, mode) {
    const raw = Array.isArray(rows) ? rows : [];

    if (inputType === 'toggle') {
        const row = raw[0] || {};
        const base = {
            label: 'On',
            value: 'on',
            color_hex: null,
            sort_order: 0,
            cost: numOrNull(row.cost),
            price: numOrNull(row.price),
        };
        if (mode === 'edit' && row.id) {
            return [{ id: row.id, ...base }];
        }

        return [base];
    }

    if (inputType === 'color') {
        return raw
            .map((row, i) => {
                const hex = normalizeHex(row.color_hex) || '#000000';
                const lbl = (row.label && String(row.label).trim()) || `Color ${i + 1}`;

                const base = {
                    label: lbl,
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

const form = useForm({
    name: props.record.name ?? '',
    category_id: props.record.category_id ?? null,
    input_type: props.record.input_type ?? 'select',
    is_required: !!props.record.is_required,
    allow_multiple: !!props.record.allow_multiple,
    min_select: props.record.min_select ?? 0,
    max_select: props.record.max_select ?? 1,
    active: props.record.active !== false,
    is_global: !!props.record.is_global,
    values: mapRecordValues(props.record),
});

const fieldError = (key) => {
    const err = form.errors[key];
    return Array.isArray(err) ? err[0] : err;
};

const appInstance = getCurrentInstance();

function showToast(type, message) {
    appInstance?.appContext.config.globalProperties.$toast?.(type, message);
}

const submit = () => {
    form
        .transform((data) => {
            const out = { ...data };
            out.is_required = boolVal(out.is_required);
            out.allow_multiple = boolVal(out.allow_multiple);
            out.active = boolVal(out.active);
            out.is_global = boolVal(out.is_global);
            out.category_id = out.category_id ? Number(out.category_id) : null;
            out.min_select = Number(out.min_select) || 0;
            out.max_select = Number(out.max_select) || 0;
            delete out.slug;
            out.values = buildValuesPayload(out.input_type, out.values, 'edit');

            return out;
        })
        .put(route('asset-options.update', { assetOption: props.record.id }), {
            onSuccess: (page) => {
                const message = page.props.flash?.success || 'Asset option saved successfully.';
                showToast('success', message);
            },
        });
};

const cancel = () => router.visit(route('asset-options.show', { assetOption: props.record.id }));

const isGlobal = computed(() => form.is_global || props.record.is_global);
</script>

<template>
    <Head :title="`Edit ${label}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ label }}
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6 px-4 py-6 pb-28">
            <div
                v-if="isGlobal"
                class="overflow-hidden rounded-xl border border-sky-200 bg-sky-50/80 p-5 shadow-sm dark:border-sky-800 dark:bg-sky-900/20"
            >
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Global option</h3>
                <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">
                    Global options do not use catalog assignments. Save changes, then staff can add this option on transaction lines via
                    <strong>Add global option</strong>.
                </p>
            </div>

            <form id="asset-option-form" class="space-y-6" @submit.prevent="submit">
                <AssetOptionForm
                    :form="form"
                    :field-error="fieldError"
                    :input-type-options="inputTypeOptions"
                    :category-record="record"
                />
            </form>

            <AssetOptionAssignmentsPanel v-if="!isGlobal" :option-id="record.id" :record="record" />
        </div>

        <FormFixedActionBar
            form-id="asset-option-form"
            :processing="form.processing"
            submit-label="Save changes"
            @cancel="cancel"
        />
    </TenantLayout>
</template>
