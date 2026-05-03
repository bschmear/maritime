<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Asset Option' },
    domainName: { type: String, default: 'AssetOption' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const isDeleting = ref(false);

const label = computed(() => props.record?.name || `Option #${props.record?.id}`);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset options', href: route('asset-options.index') },
    { label: label.value },
]);

const values = computed(() => props.record?.all_values ?? props.record?.allValues ?? []);

const makers = ref([]);
const lookupAssets = ref([]);
const selectedMakeId = ref('');
const applyAllModels = ref(false);
const selectedAssetRows = ref([]);

const valueModalOpen = ref(false);
const editingValue = ref(null);
const valueForm = ref({
    label: '',
    value: '',
    color_hex: '',
    cost: '',
    price: '',
    sort_order: 0,
    is_default: false,
    active: true,
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const fetchAssignmentLookup = async (makeId = null) => {
    const params = makeId ? { make_id: makeId } : {};
    const { data } = await axios.get(route('asset-options.assignment-lookup'), {
        params,
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (data.makers) {
        makers.value = data.makers;
    }
    if (makeId) {
        lookupAssets.value = data.assets || [];
    } else {
        lookupAssets.value = [];
    }
};

watch(selectedMakeId, (id) => {
    if (!id) {
        lookupAssets.value = [];
        return;
    }
    void fetchAssignmentLookup(id);
});

onMounted(async () => {
    await fetchAssignmentLookup();
    if (selectedMakeId.value) {
        await fetchAssignmentLookup(selectedMakeId.value);
    }
});

const initAssignmentState = () => {
    const mas = props.record?.make_assignments ?? props.record?.makeAssignments ?? [];
    const asg = props.record?.assignments ?? [];
    if (mas.length > 0) {
        selectedMakeId.value = String(mas[0].make_id);
        applyAllModels.value = true;
        selectedAssetRows.value = [];
    } else if (asg.length > 0) {
        const mk = asg[0]?.asset?.make_id;
        selectedMakeId.value = mk ? String(mk) : '';
        applyAllModels.value = false;
        selectedAssetRows.value = asg.map((a) => ({
            asset_id: a.asset_id,
            variant_id: a.variant_id,
        }));
    } else {
        selectedMakeId.value = '';
        applyAllModels.value = false;
        selectedAssetRows.value = [];
    }
};

initAssignmentState();

const openNewValue = () => {
    editingValue.value = null;
    valueForm.value = {
        label: '',
        value: '',
        color_hex: '',
        cost: '',
        price: '',
        sort_order: (values.value?.length || 0) * 10,
        is_default: false,
        active: true,
    };
    valueModalOpen.value = true;
};

const openEditValue = (v) => {
    editingValue.value = v;
    valueForm.value = {
        label: v.label || '',
        value: v.value || '',
        color_hex: v.color_hex || '',
        cost: v.cost ?? '',
        price: v.price ?? '',
        sort_order: v.sort_order ?? 0,
        is_default: !!v.is_default,
        active: !!v.active,
    };
    valueModalOpen.value = true;
};

const saveValue = async () => {
    const payload = { ...valueForm.value };
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

const toggleAssetRow = (assetId, variantId = null) => {
    const exists = selectedAssetRows.value.some(
        (r) => Number(r.asset_id) === Number(assetId) && (r.variant_id == null ? variantId == null : Number(r.variant_id) === Number(variantId)),
    );
    if (exists) {
        selectedAssetRows.value = selectedAssetRows.value.filter(
            (r) => !(Number(r.asset_id) === Number(assetId) && (r.variant_id == null ? variantId == null : Number(r.variant_id) === Number(variantId))),
        );
    } else {
        selectedAssetRows.value = [...selectedAssetRows.value, { asset_id: assetId, variant_id: variantId }];
    }
};

const isRowSelected = (assetId, variantId = null) =>
    selectedAssetRows.value.some(
        (r) => Number(r.asset_id) === Number(assetId) && (r.variant_id == null ? variantId == null : Number(r.variant_id) === Number(variantId)),
    );

const saveAssignments = async () => {
    if (!selectedMakeId.value) {
        alert('Select a brand first.');
        return;
    }
    let rows = [];
    if (!applyAllModels.value) {
        rows = selectedAssetRows.value.map((r) => ({
            asset_id: r.asset_id,
            variant_id: r.variant_id,
        }));
    }
    await axios.post(
        route('asset-options.sync-assignments', { assetOption: props.record.id }),
        {
            make_id: Number(selectedMakeId.value),
            apply_to_all_models: applyAllModels.value,
            rows,
        },
        { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
    );
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
            <div class="col-span-full flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Breadcrumb :items="breadcrumbItems" />
                    <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ label }}</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('asset-options.edit', { assetOption: record.id })"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Edit definition
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/20"
                        @click="showDeleteModal = true"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-6xl space-y-8 px-4 py-6">
            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Summary</h3>
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                    :record="record"
                    :record-type="recordType"
                    :record-title="recordTitle"
                    mode="view"
                />
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Values</h3>
                    <button
                        type="button"
                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                        @click="openNewValue"
                    >
                        Add value
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                                <th class="py-2 pr-4">Label</th>
                                <th class="py-2 pr-4">Price</th>
                                <th class="py-2 pr-4">Cost</th>
                                <th class="py-2 pr-4">Sort</th>
                                <th class="py-2 pr-4">Active</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in values" :key="v.id" class="border-b border-gray-100 dark:border-gray-700/80">
                                <td class="py-2 pr-4">{{ v.label }}</td>
                                <td class="py-2 pr-4">{{ v.price ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ v.cost ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ v.sort_order }}</td>
                                <td class="py-2 pr-4">{{ v.active ? 'Yes' : 'No' }}</td>
                                <td class="py-2 text-right">
                                    <button type="button" class="text-primary-600 hover:underline" @click="openEditValue(v)">Edit</button>
                                    <button type="button" class="ml-3 text-red-600 hover:underline" @click="deleteValue(v)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!values.length" class="py-6 text-center text-gray-500">No values yet.</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Assignments by brand</h3>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Choose a brand, then either apply to all models or pick specific models (and variants).
                </p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Brand</label>
                        <select v-model="selectedMakeId" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900">
                            <option value="">Select brand…</option>
                            <option v-for="m in makers" :key="m.id" :value="String(m.id)">{{ m.display_name }}</option>
                        </select>
                        <button
                            type="button"
                            class="mt-2 text-sm text-primary-600 hover:underline"
                            @click="fetchAssignmentLookup(selectedMakeId || null)"
                        >
                            Refresh models list
                        </button>
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input id="apply_all" v-model="applyAllModels" type="checkbox" class="rounded border-gray-300" />
                        <label for="apply_all" class="text-sm text-gray-800 dark:text-gray-200">Apply to all models under this brand</label>
                    </div>
                </div>

                <div v-if="selectedMakeId && !applyAllModels" class="mt-6 max-h-72 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div v-for="a in lookupAssets" :key="a.id" class="mb-3">
                        <label class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                            <input type="checkbox" :checked="isRowSelected(a.id, null)" @change="toggleAssetRow(a.id, null)" />
                            {{ a.display_name }}
                        </label>
                        <div v-if="a.has_variants && a.variants?.length" class="ml-6 mt-1 space-y-1">
                            <label v-for="vv in a.variants" :key="vv.id" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" :checked="isRowSelected(a.id, vv.id)" @change="toggleAssetRow(a.id, vv.id)" />
                                {{ vv.display_name || vv.name }}
                            </label>
                        </div>
                    </div>
                    <p v-if="!lookupAssets.length" class="text-sm text-gray-500">Select a brand and click refresh.</p>
                </div>

                <div class="mt-6">
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="saveAssignments"
                    >
                        Save assignments
                    </button>
                </div>
            </div>
        </div>

        <Modal :show="valueModalOpen" max-width="lg" @close="valueModalOpen = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingValue ? 'Edit value' : 'New value' }}</h3>
                <div class="mt-4 grid gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Label</label>
                        <input v-model="valueForm.label" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Internal value</label>
                        <input v-model="valueForm.value" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Color hex</label>
                        <input v-model="valueForm.color_hex" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500">Cost</label>
                            <input v-model="valueForm.cost" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Price</label>
                            <input v-model="valueForm.price" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Sort order</label>
                        <input v-model.number="valueForm.sort_order" type="number" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="valueForm.is_default" type="checkbox" /> Default
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="valueForm.active" type="checkbox" /> Active
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="valueModalOpen = false">Cancel</button>
                    <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white" @click="saveValue">Save</button>
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
