<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import BulkActionsGearModal from '@/Components/Tenant/BulkActionsGearModal.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    recordType: {
        type: String
    },
    recordTitle: {
        type: String
    },
    pluralTitle: {
        type: String
    },
});

const ENUM_BILLING_TYPE = 'App\\Enums\\ServiceItem\\BillingType';

const tableRef = ref(null);
const selectedTableIds = ref([]);
const bulkActionIds = ref([]);
const showNoSelectionModal = ref(false);
const showBulkDeleteModal = ref(false);
const showBulkUpdateModal = ref(false);
const bulkDeleting = ref(false);
const bulkUpdating = ref(false);
const updateBillingType = ref(false);
const updateTaxable = ref(false);
const updateRate = ref(false);
const updateCost = ref(false);
const bulkBillingType = ref('');
const bulkTaxable = ref(false);
const bulkRate = ref('');
const bulkCost = ref('');
const quickBooksImportRef = ref(null);

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const recordTypeOptions = [
    { value: 'addons', label: 'Add-Ons', route: 'addons.index' },
    { value: 'assets', label: 'Assets', route: 'assets.index' },
    { value: 'inventoryitems', label: 'Parts & Accessories', route: 'inventoryitems.index' },
    { value: 'serviceitems', label: 'Service Items', route: 'serviceitems.index' },
];

const currentRecordType = ref(props.recordType);

const billingTypeOptions = computed(() => props.enumOptions[ENUM_BILLING_TYPE] ?? []);

const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

const canSubmitBulkUpdate = computed(() => {
    if (updateBillingType.value && bulkBillingType.value !== '') {
        return true;
    }
    if (updateTaxable.value) {
        return true;
    }
    if (updateRate.value && bulkRate.value !== '') {
        return true;
    }
    if (updateCost.value && bulkCost.value !== '') {
        return true;
    }
    return false;
});

const switchRecordType = (newType) => {
    const option = recordTypeOptions.find(opt => opt.value === newType);
    if (option) {
        router.visit(route(option.route));
    }
};

const onTableSelectionChange = (ids) => {
    selectedTableIds.value = ids;
};

const resolveSelectedIds = () => {
    const fromTable = tableRef.value?.getSelectedRecordIds?.() ?? [];
    if (fromTable.length) {
        return fromTable;
    }
    return selectedTableIds.value;
};

const requireSelection = async () => {
    const ids = resolveSelectedIds();
    if (!ids.length) {
        showNoSelectionModal.value = true;
        return null;
    }
    bulkActionIds.value = ids;
    selectedTableIds.value = ids;
    await nextTick();
    return ids;
};

const resetBulkUpdateForm = () => {
    updateBillingType.value = false;
    updateTaxable.value = false;
    updateRate.value = false;
    updateCost.value = false;
    bulkBillingType.value = '';
    bulkTaxable.value = false;
    bulkRate.value = '';
    bulkCost.value = '';
};

const handleBulkAction = async (item) => {
    const action = item.action;

    if (action === 'importFromQuickbooks') {
        quickBooksImportRef.value?.openImportModal?.();
        return;
    }

    if (action === 'bulkUpdate') {
        const ids = await requireSelection();
        if (!ids) {
            return;
        }
        resetBulkUpdateForm();
        showBulkUpdateModal.value = true;
        return;
    }

    if (action === 'bulkDelete') {
        const ids = await requireSelection();
        if (!ids) {
            return;
        }
        showBulkDeleteModal.value = true;
    }
};

const confirmBulkUpdate = () => {
    const ids = bulkActionIds.value.length ? bulkActionIds.value : resolveSelectedIds();
    if (!ids.length || bulkUpdating.value || !canSubmitBulkUpdate.value) {
        return;
    }

    const fields = {};
    if (updateBillingType.value && bulkBillingType.value !== '') {
        fields.billing_type = Number(bulkBillingType.value);
    }
    if (updateTaxable.value) {
        fields.taxable = bulkTaxable.value;
    }
    if (updateRate.value && bulkRate.value !== '') {
        fields.default_rate = Number(bulkRate.value);
    }
    if (updateCost.value && bulkCost.value !== '') {
        fields.default_cost = Number(bulkCost.value);
    }

    if (!Object.keys(fields).length) {
        return;
    }

    bulkUpdating.value = true;
    router.post(
        route('serviceitems.bulk-update'),
        { ids, fields },
        {
            preserveScroll: true,
            onFinish: () => {
                bulkUpdating.value = false;
                showBulkUpdateModal.value = false;
                bulkActionIds.value = [];
                resetBulkUpdateForm();
            },
        },
    );
};

const confirmBulkDelete = () => {
    const ids = bulkActionIds.value.length ? bulkActionIds.value : resolveSelectedIds();
    if (!ids.length || bulkDeleting.value) {
        return;
    }

    bulkDeleting.value = true;
    router.post(
        route('serviceitems.bulk-destroy'),
        { ids },
        {
            preserveScroll: true,
            onFinish: () => {
                bulkDeleting.value = false;
                showBulkDeleteModal.value = false;
                bulkActionIds.value = [];
            },
        },
    );
};
</script>

<template>
    <Head :title="props.pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>
                <div class="flex items-center gap-3">
                    <BulkActionsGearModal
                        v-if="bulkActions.length"
                        :actions="bulkActions"
                        @action="handleBulkAction"
                    />
                    <select
                        id="record-type-selector"
                        v-model="currentRecordType"
                        class="block w-full min-w-[200px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        @change="switchRecordType($event.target.value)"
                    >
                        <option v-for="option in recordTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
        </template>

        <Table
            ref="tableRef"
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
            :create-modal="true"
            @selection-change="onTableSelectionChange"
        />

        <QuickBooksImport
            ref="quickBooksImportRef"
            record-type="serviceitem"
        />

        <Modal :show="showNoSelectionModal" max-width="sm" @close="showNoSelectionModal = false">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">No service items selected</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Select one or more service items using the checkboxes in the table, then try again.
                </p>
                <div class="mt-6 flex justify-center">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="showNoSelectionModal = false"
                    >
                        OK
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="showBulkUpdateModal" max-width="md" @close="showBulkUpdateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Update selected service items</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Updating
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ bulkActionIds.length }}</span>
                    selected item{{ bulkActionIds.length === 1 ? '' : 's' }}. Check the fields you want to change.
                </p>

                <div class="mt-5 space-y-4">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateBillingType"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Type</span>
                                <select
                                    v-model="bulkBillingType"
                                    :disabled="!updateBillingType"
                                    class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="">Select type</option>
                                    <option
                                        v-for="option in billingTypeOptions"
                                        :key="option.value ?? option.id"
                                        :value="option.value ?? option.id"
                                    >
                                        {{ option.name }}
                                    </option>
                                </select>
                            </span>
                        </label>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateTaxable"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Taxable</span>
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input
                                        v-model="bulkTaxable"
                                        type="checkbox"
                                        :disabled="!updateTaxable"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700"
                                    >
                                    Mark as taxable
                                </label>
                            </span>
                        </label>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateRate"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Rate</span>
                                <input
                                    v-model="bulkRate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :disabled="!updateRate"
                                    placeholder="0.00"
                                    class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                >
                            </span>
                        </label>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateCost"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Cost</span>
                                <input
                                    v-model="bulkCost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :disabled="!updateCost"
                                    placeholder="0.00"
                                    class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                >
                            </span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        :disabled="bulkUpdating"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="showBulkUpdateModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="bulkUpdating || !canSubmitBulkUpdate"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-600"
                        @click="confirmBulkUpdate"
                    >
                        {{ bulkUpdating ? 'Updating…' : 'Update selected' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="showBulkDeleteModal" max-width="md" @close="showBulkDeleteModal = false">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete selected service items</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    You are about to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ bulkActionIds.length }}</span>
                    selected item{{ bulkActionIds.length === 1 ? '' : 's' }}.
                </p>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    This removes the selected service items from Helmful only. They will not be deleted or changed in QuickBooks.
                    Items linked to work orders or invoices may be affected.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        :disabled="bulkDeleting"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="confirmBulkDelete"
                    >
                        {{ bulkDeleting ? 'Deleting…' : 'Continue and delete' }}
                    </button>
                    <button
                        type="button"
                        :disabled="bulkDeleting"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="showBulkDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
