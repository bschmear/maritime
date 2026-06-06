<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Mailchimp from '@/Components/Tenant/Mailchimp.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import BulkActionsGearModal from '@/Components/Tenant/BulkActionsGearModal.vue';
import PeopleIndexRoleLinks from '@/Components/Tenant/PeopleIndexRoleLinks.vue';
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
        type: String,
        default: 'contact',
    },
    recordTitle: {
        type: String,
        default: 'contact',
    },
    pluralTitle: {
        type: String,
        default: 'contacts',
    },
});

const tableRef = ref(null);
const mailchimpRef = ref(null);
const quickBooksImportRef = ref(null);
const selectedTableIds = ref([]);
const bulkDeleteIds = ref([]);
const showBulkDeleteModal = ref(false);
const showNoSelectionModal = ref(false);
const bulkDeleting = ref(false);

const ENUM_CONTACT_TYPE = 'App\\Enums\\Entity\\ContactType';
const ENUM_CONTACT_STATUS = 'App\\Enums\\Entity\\ContactStatus';
const ENUM_CONTACT_STAGE = 'App\\Enums\\Entity\\ContactStage';

const mailchimpEntityType = 'contact';

const mailchimpRecordtype = computed(() => props.enumOptions[ENUM_CONTACT_TYPE] ?? {});
const mailchimpStatuses = computed(() => props.enumOptions[ENUM_CONTACT_STATUS] ?? {});
const mailchimpPriorities = computed(() => props.enumOptions[ENUM_CONTACT_STAGE] ?? {});

/** From `table.json` → `settings.bulk_actions`, same shape as schema. */
const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

const onTableSelectionChange = (ids) => {
    selectedTableIds.value = ids;
};

const resolveSelectedContactIds = () => {
    const fromTable = tableRef.value?.getSelectedRecordIds?.() ?? [];
    if (fromTable.length) {
        return fromTable;
    }
    return selectedTableIds.value;
};

const handleBulkAction = async (item) => {
    const action = item.action;

    if (action === 'syncWithMailchimp') {
        mailchimpRef.value?.openSyncModal?.();
        return;
    }

    if (action === 'importFromQuickbooks') {
        quickBooksImportRef.value?.openImportModal?.();
        return;
    }

    if (action === 'bulkDelete') {
        const ids = resolveSelectedContactIds();
        if (!ids.length) {
            showNoSelectionModal.value = true;
            return;
        }
        bulkDeleteIds.value = ids;
        selectedTableIds.value = ids;
        await nextTick();
        showBulkDeleteModal.value = true;
        return;
    }
};

const confirmBulkDelete = () => {
    const ids = bulkDeleteIds.value.length ? bulkDeleteIds.value : resolveSelectedContactIds();
    if (!ids.length || bulkDeleting.value) {
        return;
    }

    bulkDeleting.value = true;
    router.post(
        route('contacts.bulk-destroy'),
        { ids },
        {
            preserveScroll: true,
            onFinish: () => {
                bulkDeleting.value = false;
                showBulkDeleteModal.value = false;
                bulkDeleteIds.value = [];
            },
        },
    );
};

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <PeopleIndexRoleLinks active-page="contacts" />
                    <BulkActionsGearModal :actions="bulkActions" @action="handleBulkAction" />
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
            @selection-change="onTableSelectionChange"
        />

        <Mailchimp
            ref="mailchimpRef"
            :type="mailchimpEntityType"
            :table-selected-ids="selectedTableIds"
            :statuses="mailchimpStatuses"
            :priorities="mailchimpPriorities"
            :recordtype="mailchimpRecordtype"
        />

        <QuickBooksImport ref="quickBooksImportRef" record-type="customer" />

        <Modal :show="showNoSelectionModal" max-width="sm" @close="showNoSelectionModal = false">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">No contacts selected</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Select one or more contacts using the checkboxes in the table, then choose Delete Selected again.
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
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete selected contacts</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    You are about to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ bulkDeleteIds.length }}</span>
                    selected contact{{ bulkDeleteIds.length === 1 ? '' : 's' }}.
                </p>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    This permanently removes the selected contacts and their addresses. Contacts linked to
                    deals, estimates, invoices, contracts, or other records cannot be deleted.
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
