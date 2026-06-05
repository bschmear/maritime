<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Mailchimp from '@/Components/Tenant/Mailchimp.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import LicenseScanner from '@/Components/Tenant/LicenseScanner.vue';
import BulkActionsGearModal from '@/Components/Tenant/BulkActionsGearModal.vue';
import PeopleIndexRoleLinks from '@/Components/Tenant/PeopleIndexRoleLinks.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

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

const mailchimpRef = ref(null);
const quickBooksImportRef = ref(null);
const selectedTableIds = ref([]);

/** @type {import('vue').Ref<'scan' | 'parsing' | 'preview' | null>} */
const licenseFlow = ref(null);
const extractedRows = ref([]);
const licenseDraft = ref(/** @type {Record<string, unknown>} */ ({}));
const licenseCreateBusy = ref(false);

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

const handleBulkAction = (item) => {
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
        if (!selectedTableIds.value.length) {
            window.alert('Select at least one contact first.');
            return;
        }
        if (!window.confirm(`Delete ${selectedTableIds.value.length} selected contact(s)? This cannot be undone.`)) {
            return;
        }
        router.post(route('contacts.bulk-destroy'), { ids: selectedTableIds.value }, {
            preserveScroll: true,
        });
        return;
    }
};

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const openLicenseScan = () => {
    licenseFlow.value = 'scan';
    extractedRows.value = [];
    licenseDraft.value = {};
};

const closeLicenseScan = () => {
    licenseFlow.value = null;
    extractedRows.value = [];
    licenseDraft.value = {};
};

const onLicenseDecoded = async (raw) => {
    licenseFlow.value = 'parsing';
    try {
        const { data } = await axios.post(route('contacts.parse-license-barcode'), {
            barcode: raw,
        });
        if (!data.success) {
            throw new Error(data.message || 'Could not read this barcode.');
        }
        extractedRows.value = data.extracted_rows ?? [];
        const draft = structuredClone(data.contact ?? {});
        for (const k of ['first_name', 'last_name', 'notes']) {
            if (draft[k] == null) {
                draft[k] = '';
            }
        }
        licenseDraft.value = draft;
        licenseFlow.value = 'preview';
    } catch (e) {
        const msg =
            e?.response?.data?.message ?? e?.message ?? 'Could not read this barcode. Try again or add the contact manually.';
        window.alert(msg);
        licenseFlow.value = 'scan';
    }
};

const submitLicenseContact = async () => {
    licenseCreateBusy.value = true;
    try {
        const { data } = await axios.post(route('contacts.store'), licenseDraft.value);
        if (!data.success) {
            const errText =
                data.errors && typeof data.errors === 'object'
                    ? Object.values(data.errors)
                          .flat()
                          .join(' ')
                    : data.message || 'Could not create contact.';
            window.alert(errText);
            return;
        }
        closeLicenseScan();
        if (data.recordId) {
            router.visit(route('contacts.show', data.recordId));
        }
    } catch (e) {
        const payload = e?.response?.data;
        const msg =
            payload?.errors && typeof payload.errors === 'object'
                ? Object.values(payload.errors)
                      .flat()
                      .join(' ')
                : payload?.message ?? e?.message ?? 'Could not create contact.';
        window.alert(msg);
    } finally {
        licenseCreateBusy.value = false;
    }
};
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <PeopleIndexRoleLinks active-page="contacts" />
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500/40 bg-white px-3 py-1.5 text-sm font-medium text-primary-700 shadow-sm transition-colors hover:bg-primary-50 dark:border-primary-400/40 dark:bg-gray-800 dark:text-primary-300 dark:hover:bg-gray-700"
                            @click="openLicenseScan"
                        >
                            <span class="material-icons text-[20px]">qr_code_scanner</span>
                            Scan license
                        </button>
                    </div>
                    <BulkActionsGearModal :actions="bulkActions" @action="handleBulkAction" />
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

        <QuickBooksImport ref="quickBooksImportRef" :record-type="mailchimpEntityType" />

        <div
            v-if="licenseFlow"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="license-scan-title"
            @click.self="closeLicenseScan"
        >
            <div
                class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-900 dark:ring-1 dark:ring-gray-700"
            >
                <template v-if="licenseFlow === 'scan'">
                    <h2 id="license-scan-title" class="sr-only">Scan driver license barcode</h2>
                    <div class="h-[min(72vh,560px)] min-h-[360px]">
                        <LicenseScanner @close="closeLicenseScan" @decoded="onLicenseDecoded" />
                    </div>
                </template>

                <div v-else-if="licenseFlow === 'parsing'" class="flex flex-col items-center gap-4 px-8 py-16 text-center">
                    <h2 id="license-scan-title" class="text-lg font-semibold text-gray-900 dark:text-white">Reading barcode…</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Extracting name and address from the license data.</p>
                </div>

                <div v-else-if="licenseFlow === 'preview'" class="flex max-h-[92vh] flex-col">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <h2 id="license-scan-title" class="text-lg font-semibold text-gray-900 dark:text-white">Review & create</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Confirm the fields below, then create the contact. You can edit names before saving.
                        </p>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <dl v-if="extractedRows.length" class="space-y-2 text-sm">
                            <div
                                v-for="(row, idx) in extractedRows"
                                :key="idx"
                                class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] gap-x-3 gap-y-1 border-b border-gray-100 py-2 last:border-0 dark:border-gray-800"
                            >
                                <dt class="text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                                <dd class="break-words text-gray-900 dark:text-gray-100">{{ row.value }}</dd>
                            </div>
                        </dl>
                        <p v-else class="text-sm text-gray-600 dark:text-gray-400">No labeled rows were extracted; fill in names manually.</p>

                        <div class="mt-6 space-y-4">
                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >First name</label
                                >
                                <input
                                    v-model="licenseDraft.first_name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >Last name</label
                                >
                                <input
                                    v-model="licenseDraft.last_name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >Notes (from scan)</label
                                >
                                <textarea
                                    v-model="licenseDraft.notes"
                                    rows="3"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/80">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="licenseFlow = 'scan'"
                        >
                            Scan again
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 dark:bg-primary-500 dark:hover:bg-primary-600"
                            :disabled="licenseCreateBusy"
                            @click="submitLicenseContact"
                        >
                            {{ licenseCreateBusy ? 'Creating…' : 'Create contact' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
