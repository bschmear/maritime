<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import GoogleSheetPushConfirmModal from '@/Components/Tenant/GoogleSheetPushConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const ENUM_UNIT_STATUS = 'App\\Enums\\Inventory\\UnitStatus';
const ENUM_UNIT_CONDITION = 'App\\Enums\\Inventory\\UnitCondition';

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
        type: String,
    },
    recordTitle: {
        type: String,
    },
    pluralTitle: {
        type: String,
    },
    googleConnected: {
        type: Boolean,
        default: false,
    },
    googleSheetSettings: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const tableRef = ref(null);
const selectedTableIds = ref([]);
const bulkActionIds = ref([]);
const showNoSelectionModal = ref(false);
const showBulkUpdateModal = ref(false);
const showBulkActionPicker = ref(false);
const bulkUpdating = ref(false);

const updateStatus = ref(false);
const updateCondition = ref(false);
const updateInactive = ref(false);
const updateSubsidiary = ref(false);
const updateLocation = ref(false);

const bulkStatus = ref('');
const bulkCondition = ref('');
const bulkInactive = ref(false);
const bulkSubsidiaryId = ref(null);
const bulkSubsidiaryLabel = ref(null);
const bulkLocationId = ref(null);
const bulkLocationLabel = ref(null);

const subsidiaryField = {
    label: 'Subsidiary',
    type: 'record',
    typeDomain: 'Subsidiary',
    relationship: 'subsidiary',
    displayField: 'display_name',
};

const locationField = {
    label: 'Location',
    type: 'record',
    typeDomain: 'Location',
    relationship: 'location',
    filterby: 'subsidiary_id',
    record_filter_field: 'subsidiary_id',
    displayField: 'display_name',
};

const tabBtnClass = (tab, active) => [
    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
    active
        ? 'bg-primary-600 text-white shadow-sm dark:bg-primary-500'
        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
];

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: 'Asset Units' },
]);

const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

const bulkActionButtonLabel = computed(() => {
    if (bulkActions.value.length === 1) {
        return bulkActions.value[0].label;
    }
    return 'Bulk actions';
});

const hasTableSelection = computed(() => selectedTableIds.value.length > 0);

const unitStatusOptions = computed(() => props.enumOptions[ENUM_UNIT_STATUS] ?? []);
const unitConditionOptions = computed(() => props.enumOptions[ENUM_UNIT_CONDITION] ?? []);

const hasBulkIntValue = (enabled, raw) => {
    if (!enabled) {
        return false;
    }
    if (raw === '' || raw === null || raw === undefined) {
        return false;
    }
    const n = Number(raw);
    return Number.isFinite(n) && n > 0;
};

const canSubmitBulkUpdate = computed(() => {
    if (hasBulkIntValue(updateStatus.value, bulkStatus.value)) {
        return true;
    }
    if (hasBulkIntValue(updateCondition.value, bulkCondition.value)) {
        return true;
    }
    if (updateInactive.value) {
        return true;
    }
    if (updateSubsidiary.value) {
        return true;
    }
    if (updateLocation.value) {
        return true;
    }
    return false;
});

const actionGroups = computed(() => {
    const googleItems = props.googleConnected
        ? [
            { label: 'Sync to Google Sheet', action: 'googlePush' },
            { label: 'Import from Google Sheet', action: 'googlePull' },
        ]
        : [{ label: 'Connect Google Sheets…', action: 'googleConnect' }];

    return [
        {
            label: 'Export',
            items: [
                { label: 'Excel', action: 'exportXlsx' },
                { label: 'CSV', action: 'exportCsv' },
            ],
        },
        {
            label: 'Import',
            items: [
                { label: 'Import updates', action: 'importUpdates' },
                { label: 'Import from document', action: 'importInvoice' },
            ],
        },
        {
            label: 'Google Integration',
            items: googleItems,
        },
    ];
});

const sheetActionLoading = ref(false);
const showPushConfirm = ref(false);
const showActionsMenu = ref(false);

function toggleActionsMenu() {
    showActionsMenu.value = !showActionsMenu.value;
}

function closeActionsMenu() {
    showActionsMenu.value = false;
}

function bulkPlacementRecordStub() {
    const stub = {};
    if (bulkSubsidiaryId.value) {
        stub.subsidiary_id = bulkSubsidiaryId.value;
        stub.subsidiary = {
            id: bulkSubsidiaryId.value,
            display_name: bulkSubsidiaryLabel.value || '',
        };
    }
    if (bulkLocationId.value) {
        stub.location_id = bulkLocationId.value;
        stub.location = {
            id: bulkLocationId.value,
            display_name: bulkLocationLabel.value || '',
        };
    }
    return stub;
}

function onBulkSubsidiarySelected(record) {
    bulkSubsidiaryLabel.value = record?.display_name ?? null;
    bulkLocationId.value = null;
    bulkLocationLabel.value = null;
}

function onBulkLocationSelected(record) {
    bulkLocationLabel.value = record?.display_name ?? null;
}

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
    updateStatus.value = false;
    updateCondition.value = false;
    updateInactive.value = false;
    updateSubsidiary.value = false;
    updateLocation.value = false;
    bulkStatus.value = '';
    bulkCondition.value = '';
    bulkInactive.value = false;
    bulkSubsidiaryId.value = null;
    bulkSubsidiaryLabel.value = null;
    bulkLocationId.value = null;
    bulkLocationLabel.value = null;
};

const handleBulkAction = async (item) => {
    if (item.action === 'bulkUpdate') {
        const ids = await requireSelection();
        if (!ids) {
            return;
        }
        resetBulkUpdateForm();
        showBulkUpdateModal.value = true;
    }
};

const openBulkActions = async () => {
    if (!bulkActions.value.length) {
        return;
    }
    if (bulkActions.value.length === 1) {
        await handleBulkAction(bulkActions.value[0]);
        return;
    }
    showBulkActionPicker.value = true;
};

const runBulkActionFromPicker = async (item) => {
    showBulkActionPicker.value = false;
    await handleBulkAction(item);
};

function handleSpreadsheetAction(item) {
    closeActionsMenu();

    if (item.action === 'exportXlsx') {
        window.location.href = route('assetunits.export', { format: 'xlsx' });
        return;
    }

    if (item.action === 'exportCsv') {
        window.location.href = route('assetunits.export', { format: 'csv' });
        return;
    }

    if (item.action === 'importUpdates') {
        router.visit(route('assetunits.import'));
        return;
    }

    if (item.action === 'importInvoice') {
        router.visit(route('assetunits.import.invoice'));
        return;
    }

    if (item.action === 'googleConnect') {
        router.visit(route('google'));
        return;
    }

    if (item.action === 'googlePush') {
        showPushConfirm.value = true;
        return;
    }

    if (item.action === 'googlePull') {
        runGoogleSheetAction(item.action);
    }
}

const confirmBulkUpdate = () => {
    const ids = bulkActionIds.value.length ? bulkActionIds.value : resolveSelectedIds();
    if (!ids.length || bulkUpdating.value || !canSubmitBulkUpdate.value) {
        return;
    }

    const fields = {};
    if (hasBulkIntValue(updateStatus.value, bulkStatus.value)) {
        fields.status = Number(bulkStatus.value);
    }
    if (hasBulkIntValue(updateCondition.value, bulkCondition.value)) {
        fields.condition = Number(bulkCondition.value);
    }
    if (updateInactive.value) {
        fields.inactive = bulkInactive.value;
    }
    if (updateSubsidiary.value) {
        fields.subsidiary_id = bulkSubsidiaryId.value;
    }
    if (updateLocation.value) {
        fields.location_id = bulkLocationId.value;
    }

    if (!Object.keys(fields).length) {
        return;
    }

    bulkUpdating.value = true;
    router.post(
        route('assetunits.bulk-update'),
        { ids, fields },
        {
            preserveScroll: true,
            onSuccess: (visit) => {
                const flash = visit.props.flash ?? {};
                if (flash.error) {
                    window.alert(flash.error);
                }
            },
            onError: (errors) => {
                const message = errors && typeof errors === 'object'
                    ? Object.values(errors).flat().filter(Boolean).join('\n')
                    : 'Bulk update failed.';
                window.alert(message || 'Bulk update failed.');
            },
            onFinish: () => {
                bulkUpdating.value = false;
                showBulkUpdateModal.value = false;
                bulkActionIds.value = [];
                selectedTableIds.value = [];
                resetBulkUpdateForm();
            },
        },
    );
};

function confirmGooglePush() {
    showPushConfirm.value = false;
    runGoogleSheetAction('googlePush');
}

async function runGoogleSheetAction(action) {
    sheetActionLoading.value = true;
    try {
        const url = action === 'googlePush'
            ? route('assetunits.google-sheet.push')
            : route('assetunits.google-sheet.pull');
        const { data } = await axios.post(url);
        const message = data.message
            ?? (action === 'googlePush'
                ? `Synced ${data.row_count ?? 0} units to Google Sheets.`
                : `Updated ${data.updated ?? 0} units from Google Sheets.`);
        window.alert(message);
        if (data.spreadsheet_url && action === 'googlePush') {
            window.open(data.spreadsheet_url, '_blank');
        }
    } catch (e) {
        window.alert(e.response?.data?.message ?? 'Google Sheets sync failed.');
    } finally {
        sheetActionLoading.value = false;
    }
}

function onDocumentClick(event) {
    if (!event.target.closest('[data-asset-unit-actions]')) {
        closeActionsMenu();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200"
        >
            {{ page.props.flash.error }}
        </div>

        <template #header>
            <div class="col-span-full flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-600 dark:bg-gray-800"
                        role="tablist"
                        aria-label="List view"
                    >
                        <Link
                            role="tab"
                            :aria-selected="false"
                            :href="route('assets.index')"
                            :class="tabBtnClass('assets', false)"
                        >
                            Assets
                        </Link>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="true"
                            :class="tabBtnClass('units', true)"
                        >
                            Units
                        </button>
                    </div>
                    <div class="relative shrink-0" data-asset-unit-actions>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            :aria-expanded="showActionsMenu"
                            title="Spreadsheet actions"
                            aria-label="Spreadsheet actions"
                            @click.stop="toggleActionsMenu"
                        >
                            <span class="material-icons text-[22px]">settings</span>
                        </button>

                        <div
                            v-show="showActionsMenu"
                            class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800"
                            @click.stop
                        >
                            <div
                                v-for="(group, groupIdx) in actionGroups"
                                :key="group.label"
                                :class="groupIdx > 0 ? 'border-t border-gray-100 dark:border-gray-700' : ''"
                            >
                                <p class="px-4 pt-2.5 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ group.label }}
                                </p>
                                <button
                                    v-for="item in group.items"
                                    :key="item.action"
                                    type="button"
                                    class="flex w-full items-center px-4 py-2 text-left text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:text-gray-100 dark:hover:bg-gray-700/80"
                                    :disabled="sheetActionLoading"
                                    @click="handleSpreadsheetAction(item)"
                                >
                                    {{ item.label }}
                                </button>
                            </div>
                        </div>
                    </div>
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
            record-title="Asset Units"
            plural-title="Units"
            @selection-change="onTableSelectionChange"
        >
            <template #headerActions>
                <button
                    v-if="bulkActions.length && hasTableSelection"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="openBulkActions"
                >
                    <span class="material-icons text-[16px]">checklist</span>
                    {{ bulkActionButtonLabel }}
                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ selectedTableIds.length }})</span>
                </button>
            </template>
        </Table>

        <GoogleSheetPushConfirmModal
            :show="showPushConfirm"
            @close="showPushConfirm = false"
            @confirm="confirmGooglePush"
        />

        <Modal :show="showBulkActionPicker" max-width="sm" @close="showBulkActionPicker = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Bulk actions</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose an action to run on selected units.
                </p>
                <ul class="mt-5 space-y-2">
                    <li v-for="(row, idx) in bulkActions" :key="idx">
                        <button
                            type="button"
                            class="flex w-full items-center rounded-lg border border-gray-200 bg-white px-4 py-3 text-left text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700/80"
                            @click="runBulkActionFromPicker(row)"
                        >
                            {{ row.label }}
                        </button>
                    </li>
                </ul>
            </div>
        </Modal>

        <Modal :show="showNoSelectionModal" max-width="sm" @close="showNoSelectionModal = false">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">No units selected</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Select one or more units using the checkboxes in the table, then try again.
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
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Update selected units</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Updating
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ bulkActionIds.length }}</span>
                    selected unit{{ bulkActionIds.length === 1 ? '' : 's' }}. Check the fields you want to change.
                </p>

                <div class="mt-5 space-y-4">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateStatus"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Status</span>
                                <select
                                    v-model="bulkStatus"
                                    :disabled="!updateStatus"
                                    class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="">Select status</option>
                                    <option
                                        v-for="option in unitStatusOptions"
                                        :key="option.id ?? option.value"
                                        :value="option.id"
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
                                v-model="updateCondition"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Condition</span>
                                <select
                                    v-model="bulkCondition"
                                    :disabled="!updateCondition"
                                    class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="">Select condition</option>
                                    <option
                                        v-for="option in unitConditionOptions"
                                        :key="option.id ?? option.value"
                                        :value="option.id"
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
                                v-model="updateInactive"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Inactive</span>
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input
                                        v-model="bulkInactive"
                                        type="checkbox"
                                        :disabled="!updateInactive"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700"
                                    >
                                    Mark as inactive
                                </label>
                            </span>
                        </label>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateSubsidiary"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Subsidiary</span>
                                <div class="mt-2" :class="{ 'pointer-events-none opacity-50': !updateSubsidiary }">
                                    <RecordSelect
                                        id="bulk-update-subsidiary"
                                        :field="subsidiaryField"
                                        v-model="bulkSubsidiaryId"
                                        field-key="subsidiary_id"
                                        :record="bulkPlacementRecordStub()"
                                        @record-selected="onBulkSubsidiarySelected"
                                    />
                                </div>
                            </span>
                        </label>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="updateLocation"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Location</span>
                                <div class="mt-2" :class="{ 'pointer-events-none opacity-50': !updateLocation }">
                                    <RecordSelect
                                        id="bulk-update-location"
                                        :field="locationField"
                                        v-model="bulkLocationId"
                                        field-key="location_id"
                                        :record="bulkPlacementRecordStub()"
                                        filter-by="subsidiary_id"
                                        :filter-value="bulkSubsidiaryId"
                                        :disabled="!updateLocation || !bulkSubsidiaryId"
                                        @record-selected="onBulkLocationSelected"
                                    />
                                </div>
                                <p v-if="updateLocation && !bulkSubsidiaryId" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                    Select a subsidiary first to choose a location.
                                </p>
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
    </TenantLayout>
</template>
