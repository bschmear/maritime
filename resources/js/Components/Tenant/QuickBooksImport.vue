<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Import destination when `allowTypeChoice` is false (e.g. Contacts index tab). */
    recordType: {
        type: String,
        default: 'contact',
    },
    /** When true, user picks Contact vs Lead in the modal (e.g. Payments → QuickBooks page). */
    allowTypeChoice: {
        type: Boolean,
        default: false,
    },
});

const showModal = ref(false);
const submitting = ref(false);
const importAs = ref('contact');

const effectiveType = computed(() => (props.allowTypeChoice ? importAs.value : props.recordType));

const targetLabel = computed(() => (effectiveType.value === 'lead' ? 'leads' : 'contacts'));

function openImportModal() {
    if (props.allowTypeChoice) {
        importAs.value = 'contact';
    }
    showModal.value = true;
}

function closeImportModal() {
    if (submitting.value) {
        return;
    }
    showModal.value = false;
}

function submitImport() {
    submitting.value = true;
    axios
        .post(route('account.payments.quickbooks.import-customers'), { type: effectiveType.value })
        .then((res) => {
            closeImportModal();
            window.alert(res.data.message || 'Import queued.');
        })
        .catch((err) => {
            const msg = err.response?.data?.error || err.response?.data?.message || 'Import request failed.';
            window.alert(msg);
        })
        .finally(() => {
            submitting.value = false;
        });
}

defineExpose({ openImportModal, closeImportModal });
</script>

<template>
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
                <p>
                    We will read <strong class="text-gray-900 dark:text-gray-100">active customers</strong> from your connected QuickBooks Online company and create new
                    <strong class="text-gray-900 dark:text-gray-100">{{ targetLabel }}</strong>
                    here. Existing matches are skipped (same email when present, or the same QuickBooks customer id).
                </p>
                <div v-if="allowTypeChoice" class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Import as
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-gray-800 dark:text-gray-200">
                            <input v-model="importAs" type="radio" value="contact" class="text-primary-600" :disabled="submitting">
                            <span>Contacts</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-gray-800 dark:text-gray-200">
                            <input v-model="importAs" type="radio" value="lead" class="text-primary-600" :disabled="submitting">
                            <span>Leads</span>
                        </label>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Connect QuickBooks under Account → Payments if you have not already. Large companies may take a few minutes.
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
</template>
