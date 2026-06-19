<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { computed, onUnmounted, ref } from 'vue';

const props = defineProps({
    /** Import destination: `customer` (default), `lead`, or `serviceitem`. */
    recordType: {
        type: String,
        default: 'customer',
    },
});

const importing = defineModel('importing', { type: Boolean, default: false });

const showModal = ref(false);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const submitting = ref(false);

/** QuickBooks integration sync_status: Syncing */
const SYNCING_STATUS = 2;
const FAILED_STATUS = 4;

let pollTimer = null;
let sawSyncing = false;
let pollInitialStatus = null;

/** Default billing type for service imports: 1 = hourly, 2 = flat rate. */
const serviceBillingType = ref(2);

/** Vendor import: `update` (default) or `skip` existing matches. */
const vendorExistingMode = ref('update');

const importDateFrom = ref('');
const importDateTo = ref('');

const requiresDateRange = computed(() => isBillImport.value || isBillPaymentImport.value);

const effectiveType = computed(() => {
    const chosen = props.recordType;
    return chosen === 'contact' ? 'customer' : chosen;
});

const isServiceImport = computed(() => effectiveType.value === 'serviceitem');
const isBillImport = computed(() => effectiveType.value === 'bill');
const isBillPaymentImport = computed(() => effectiveType.value === 'billpayment');
const isChartOfAccountsImport = computed(() => effectiveType.value === 'chartofaccounts');
const isVendorImport = computed(() => effectiveType.value === 'vendor');

const targetLabel = computed(() => {
    if (isServiceImport.value) {
        return 'service items';
    }
    if (isBillImport.value) {
        return 'bills';
    }
    if (isBillPaymentImport.value) {
        return 'bill payments';
    }
    if (isChartOfAccountsImport.value) {
        return 'chart of accounts';
    }
    if (isVendorImport.value) {
        return 'vendors';
    }

    return effectiveType.value === 'lead' ? 'leads' : 'customers';
});

const importRoute = computed(() => {
    if (isServiceImport.value) {
        return route('quickbooks.import-service-items');
    }
    if (isBillImport.value) {
        return route('quickbooks.import-bills');
    }
    if (isBillPaymentImport.value) {
        return route('quickbooks.import-bill-payments');
    }
    if (isChartOfAccountsImport.value) {
        return route('quickbooks.import-chart-of-accounts');
    }
    if (isVendorImport.value) {
        return route('quickbooks.import-vendors');
    }

    return route('quickbooks.import-customers');
});

const importPayload = computed(() => {
    if (isServiceImport.value) {
        return { billing_type: serviceBillingType.value };
    }
    if (isBillImport.value || isBillPaymentImport.value) {
        return {
            txn_date_from: importDateFrom.value,
            txn_date_to: importDateTo.value,
        };
    }
    if (isChartOfAccountsImport.value || isVendorImport.value) {
        if (isVendorImport.value) {
            return { existing_mode: vendorExistingMode.value };
        }

        return {};
    }

    return { type: effectiveType.value };
});

function formatInputDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function resetImportDateRange() {
    const to = new Date();
    const from = new Date();
    from.setFullYear(from.getFullYear() - 1);

    importDateTo.value = formatInputDate(to);
    importDateFrom.value = formatInputDate(from);
}

const dateRangeError = computed(() => {
    if (!requiresDateRange.value) {
        return '';
    }

    if (!importDateFrom.value || !importDateTo.value) {
        return 'Select a start and end date.';
    }

    const from = new Date(`${importDateFrom.value}T00:00:00`);
    const to = new Date(`${importDateTo.value}T00:00:00`);

    if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) {
        return 'Enter valid dates.';
    }

    if (to < from) {
        return 'End date must be on or after the start date.';
    }

    const maxTo = new Date(from);
    maxTo.setFullYear(maxTo.getFullYear() + 1);

    if (to > maxTo) {
        return 'Date range cannot exceed one year.';
    }

    return '';
});

const canSubmitImport = computed(() => !submitting.value && (!requiresDateRange.value || dateRangeError.value === ''));

const modalDescription = computed(() => {
    if (isServiceImport.value) {
        return 'We will read active Service items from your connected QuickBooks Online company and create or update matching service items here. Each record stores the QuickBooks item id for invoice sync.';
    }
    if (isChartOfAccountsImport.value) {
        return 'We will read accounts from your QuickBooks Online chart of accounts and create or update matching records here. These accounts are used when categorizing bills.';
    }
    if (isVendorImport.value) {
        return 'We will read vendors from QuickBooks Online and import them here. ACH bank account and routing numbers are imported when QuickBooks returns them (Intuit often withholds these from the API). When a matching contact already exists, it will be linked to the vendor.';
    }
    if (isBillImport.value) {
        return 'We will read bills from QuickBooks Online for the date range you choose and create or update matching bill records here, including line items with expense accounts from your chart of accounts.';
    }
    if (isBillPaymentImport.value) {
        return 'We will read bill payments from QuickBooks Online for the date range you choose and create or update matching payment records here, linking them to imported bills.';
    }

    return `We will read active customers from your connected QuickBooks Online company and create new ${targetLabel.value} here. Each record is a contact with the matching profile. Billing and shipping addresses from QuickBooks are imported when present. Existing matches are skipped (same email when present, or the same QuickBooks customer id).`;
});

function stopImportPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    sawSyncing = false;
    pollInitialStatus = null;
}

function finishImportPolling() {
    importing.value = false;
    stopImportPolling();
}

async function checkImportStatus() {
    try {
        const { data } = await axios.get(route('quickbooks.import-status'));

        if (data.sync_status === FAILED_STATUS) {
            finishImportPolling();
            errorMessage.value = data.sync_error_message || 'QuickBooks import failed.';
            showErrorModal.value = true;

            return;
        }

        if (data.sync_status === SYNCING_STATUS) {
            sawSyncing = true;
        }

        if (sawSyncing && data.sync_status !== SYNCING_STATUS) {
            finishImportPolling();
            return;
        }

        if (
            !sawSyncing
            && pollInitialStatus !== null
            && data.sync_status !== pollInitialStatus
            && data.sync_status !== SYNCING_STATUS
        ) {
            finishImportPolling();
        }
    } catch {
        // Keep polling; transient network errors should not clear the spinner early.
    }
}

function startImportPolling(initialSyncStatus = null) {
    stopImportPolling();
    importing.value = true;
    pollInitialStatus = initialSyncStatus;
    sawSyncing = initialSyncStatus === SYNCING_STATUS;

    void checkImportStatus();
    pollTimer = setInterval(checkImportStatus, 2000);
}

onUnmounted(stopImportPolling);

function openImportModal() {
    if (importing.value) {
        return;
    }

    showSuccessModal.value = false;
    showErrorModal.value = false;
    if (isServiceImport.value) {
        serviceBillingType.value = 2;
    }
    if (isVendorImport.value) {
        vendorExistingMode.value = 'update';
    }
    if (requiresDateRange.value) {
        resetImportDateRange();
    }
    showModal.value = true;
}

function closeImportModal() {
    if (submitting.value) {
        return;
    }
    showModal.value = false;
}

function closeSuccessModal() {
    showSuccessModal.value = false;
}

function closeErrorModal() {
    showErrorModal.value = false;
}

function submitImport() {
    if (!canSubmitImport.value) {
        return;
    }

    submitting.value = true;
    importing.value = true;
    axios
        .post(importRoute.value, importPayload.value)
        .then((res) => {
            successMessage.value = res.data.message || 'Import queued. Records may take a few minutes to appear.';
            showModal.value = false;
            showSuccessModal.value = true;

            startImportPolling(res.data.sync_status ?? null);
        })
        .catch((err) => {
            importing.value = false;
            stopImportPolling();
            const validationErrors = err.response?.data?.errors;
            if (validationErrors && typeof validationErrors === 'object') {
                const flat = Object.values(validationErrors).flat().filter(Boolean);
                errorMessage.value = flat.length ? flat.join(' ') : 'Import request failed.';
            } else {
                errorMessage.value = err.response?.data?.error || err.response?.data?.message || 'Import request failed.';
            }
            showModal.value = false;
            showErrorModal.value = true;
        })
        .finally(() => {
            submitting.value = false;
        });
}

defineExpose({ openImportModal, closeImportModal });
</script>

<template>
    <!-- Import form modal -->
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="qbo-import-title"
    >
        <div
            class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70"
            aria-hidden="true"
            @click="closeImportModal"
        />
        <div class="relative z-10 w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-600">
                <h3 id="qbo-import-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                    Import from QuickBooks
                </h3>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Close"
                    :disabled="submitting"
                    @click="closeImportModal"
                >
                    <span class="material-icons text-[22px]">close</span>
                </button>
            </div>
            <div class="space-y-4 px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                <p>{{ modalDescription }}</p>

                <div
                    v-if="isServiceImport"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                >
                    <p class="mb-3 font-medium text-gray-900 dark:text-white">
                        Default billing type for imported services
                    </p>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="serviceBillingType"
                                type="radio"
                                class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                :value="2"
                            >
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Flat rate</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Flat price per service (uses the QuickBooks sales price as the default rate).
                                </span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="serviceBillingType"
                                type="radio"
                                class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                :value="1"
                            >
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Hourly</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Price per hour (uses the QuickBooks sales price as the hourly rate).
                                </span>
                            </span>
                        </label>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        This applies as the default for every imported item. You can change billing type on each service item later.
                    </p>
                </div>

                <div
                    v-if="isVendorImport"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                >
                    <p class="mb-3 font-medium text-gray-900 dark:text-white">
                        When a vendor already exists here
                    </p>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="vendorExistingMode"
                                type="radio"
                                class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                value="update"
                            >
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Update existing</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Match by QuickBooks vendor id or company name (when not yet linked) and refresh balances, contact info, and bank details from QuickBooks.
                                </span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="vendorExistingMode"
                                type="radio"
                                class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                value="skip"
                            >
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Skip existing</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Leave matched vendors unchanged and only create records that are not already in Maritime.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    v-if="requiresDateRange"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                >
                    <p class="mb-3 font-medium text-gray-900 dark:text-white">
                        Transaction date range
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                From
                            </label>
                            <input
                                v-model="importDateFrom"
                                type="date"
                                class="input-style w-full text-sm"
                                :disabled="submitting"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                To
                            </label>
                            <input
                                v-model="importDateTo"
                                type="date"
                                class="input-style w-full text-sm"
                                :disabled="submitting"
                            >
                        </div>
                    </div>
                    <p v-if="dateRangeError" class="mt-3 text-xs text-red-600 dark:text-red-400">
                        {{ dateRangeError }}
                    </p>
                    <p v-else class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Only bills or payments with a transaction date in this range are imported. Maximum range is one year.
                    </p>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Connect QuickBooks under Integrations if you have not already. Large companies may take a few minutes.
                </p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-600">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="submitting"
                    @click="closeImportModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-600"
                    :disabled="!canSubmitImport"
                    @click="submitImport"
                >
                    <span v-if="submitting" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                    {{ submitting ? 'Starting…' : 'Start import' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Success notification -->
    <Modal :show="showSuccessModal" max-width="sm" @close="closeSuccessModal">
        <div class="p-8 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <span class="material-icons text-2xl text-green-600 dark:text-green-400">check_circle</span>
            </div>
            <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                Import started
            </h3>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                {{ successMessage }}
            </p>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600"
                @click="closeSuccessModal"
            >
                Close
            </button>
        </div>
    </Modal>

    <!-- Error notification -->
    <Modal :show="showErrorModal" max-width="sm" @close="closeErrorModal">
        <div class="p-8 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <span class="material-icons text-2xl text-red-600 dark:text-red-400">error_outline</span>
            </div>
            <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                Import failed
            </h3>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                {{ errorMessage }}
            </p>
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="closeErrorModal"
            >
                Close
            </button>
        </div>
    </Modal>
</template>
