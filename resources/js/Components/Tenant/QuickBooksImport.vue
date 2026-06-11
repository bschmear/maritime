<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Import destination: `customer` (default), `lead`, or `serviceitem`. */
    recordType: {
        type: String,
        default: 'customer',
    },
    /** Inertia route name to visit after a successful import (default: QuickBooks integration page). */
    successRedirectRoute: {
        type: String,
        default: 'quickbooks',
    },
});

const showModal = ref(false);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const submitting = ref(false);

/** Default billing type for service imports: 1 = hourly, 2 = flat rate. */
const serviceBillingType = ref(2);

const effectiveType = computed(() => {
    const chosen = props.recordType;
    return chosen === 'contact' ? 'customer' : chosen;
});

const isServiceImport = computed(() => effectiveType.value === 'serviceitem');

const targetLabel = computed(() => {
    if (isServiceImport.value) {
        return 'service items';
    }

    return effectiveType.value === 'lead' ? 'leads' : 'customers';
});

const importRoute = computed(() => {
    if (isServiceImport.value) {
        return route('quickbooks.import-service-items');
    }

    return route('quickbooks.import-customers');
});

const importPayload = computed(() => {
    if (isServiceImport.value) {
        return { billing_type: serviceBillingType.value };
    }

    return { type: effectiveType.value };
});

const modalDescription = computed(() => {
    if (isServiceImport.value) {
        return 'We will read active Service items from your connected QuickBooks Online company and create or update matching service items here. Each record stores the QuickBooks item id for invoice sync.';
    }

    return `We will read active customers from your connected QuickBooks Online company and create new ${targetLabel.value} here. Each record is a contact with the matching profile. Billing and shipping addresses from QuickBooks are imported when present. Existing matches are skipped (same email when present, or the same QuickBooks customer id).`;
});

function openImportModal() {
    showSuccessModal.value = false;
    showErrorModal.value = false;
    if (isServiceImport.value) {
        serviceBillingType.value = 2;
    }
    showModal.value = true;
}

function closeImportModal() {
    if (submitting.value) {
        return;
    }
    showModal.value = false;
}

function goToSuccessPage() {
    showSuccessModal.value = false;
    router.visit(route(props.successRedirectRoute));
}

function closeErrorModal() {
    showErrorModal.value = false;
}

function submitImport() {
    submitting.value = true;
    axios
        .post(importRoute.value, importPayload.value)
        .then((res) => {
            successMessage.value = res.data.message || 'Import queued. Records may take a few minutes to appear.';
            showModal.value = false;
            showSuccessModal.value = true;
        })
        .catch((err) => {
            errorMessage.value = err.response?.data?.error || err.response?.data?.message || 'Import request failed.';
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
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-600"
                    :disabled="submitting"
                    @click="submitImport"
                >
                    {{ submitting ? 'Starting…' : 'Start import' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Success notification -->
    <Modal :show="showSuccessModal" max-width="sm" :closeable="false" @close="goToSuccessPage">
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
                @click="goToSuccessPage"
            >
                Go
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
