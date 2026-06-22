<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import GoogleSheetPushConfirmModal from '@/Components/Tenant/GoogleSheetPushConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
    const items = [
        { label: 'Export Excel', action: 'exportXlsx' },
        { label: 'Export CSV', action: 'exportCsv' },
        { label: 'Import updates', action: 'importUpdates' },
    ];

    if (props.googleConnected) {
        items.push(
            { label: 'Sync to Google Sheet', action: 'googlePush' },
            { label: 'Import from Google Sheet', action: 'googlePull' },
        );
    } else {
        items.push({ label: 'Connect Google Sheets…', action: 'googleConnect' });
    }

    return items;
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

function handleBulkAction(item) {
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
                            class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
                            @click.stop
                        >
                            <button
                                v-for="item in bulkActions"
                                :key="item.action"
                                type="button"
                                class="flex w-full items-center px-4 py-2.5 text-left text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:text-gray-100 dark:hover:bg-gray-700/80"
                                :disabled="sheetActionLoading"
                                @click="handleBulkAction(item)"
                            >
                                {{ item.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <Table
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            record-title="Asset Units"
            plural-title="Units"
        />

        <GoogleSheetPushConfirmModal
            :show="showPushConfirm"
            @close="showPushConfirm = false"
            @confirm="confirmGooglePush"
        />
    </TenantLayout>
</template>
