<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-icons text-gray-600 dark:text-gray-400">assignment</span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Document requests</h3>
            </div>
            <button
                v-if="hasCustomer"
                type="button"
                class="inline-flex items-center gap-2 px-4 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
                @click="showModal = true"
            >
                <span class="material-icons text-lg">send</span>
                Request document
            </button>
        </div>

        <p v-if="!hasCustomer" class="text-sm text-gray-500 dark:text-gray-400">
            Create a customer profile for this contact before sending document requests.
        </p>

        <div v-else-if="loading" class="text-sm text-gray-500">Loading…</div>

        <div v-else-if="requests.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center border border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
            No document requests yet.
        </div>

        <div v-else class="divide-y divide-gray-100 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div
                v-for="req in requests"
                :key="req.id"
                class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 bg-white dark:bg-gray-800"
            >
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ req.title }}</p>
                    <p v-if="req.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ req.description }}</p>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ statusLabel(req.status) }}
                        <span v-if="req.sent_at"> · Sent {{ formatDate(req.sent_at) }}</span>
                    </p>
                </div>
                <button
                    v-if="req.status === 'pending'"
                    type="button"
                    class="text-xs text-red-600 hover:underline shrink-0"
                    :disabled="cancellingId === req.id"
                    @click="cancelRequest(req)"
                >
                    Cancel
                </button>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="closeModal">
            <div class="fixed inset-0 bg-black/50" @click="closeModal" />
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6" @click.stop>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Request document</h3>
                    <form class="space-y-4" @submit.prevent="sendRequest">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                            <input
                                v-model="form.title"
                                type="text"
                                required
                                maxlength="255"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-3 py-2 text-sm"
                                placeholder="e.g. Driver's License"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                maxlength="5000"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-3 py-2 text-sm"
                                placeholder="Please upload your DL…"
                            />
                        </div>
                        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="px-4 py-2 text-sm text-gray-600" @click="closeModal">Cancel</button>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
                                :disabled="sending"
                            >
                                {{ sending ? 'Sending…' : 'Send request' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { getCurrentInstance, onMounted, ref } from 'vue';

const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
        return;
    }
    const toast = inertiaApp?.appContext?.config?.globalProperties?.$toast;
    if (typeof toast === 'function') {
        toast(type, String(message));
    }
}

const props = defineProps({
    contactId: { type: Number, required: true },
    hasCustomer: { type: Boolean, default: true },
    parentType: { type: String, required: true },
    parentId: { type: Number, required: true },
});

const requests = ref([]);
const loading = ref(false);
const showModal = ref(false);
const sending = ref(false);
const formError = ref('');
const cancellingId = ref(null);
const form = ref({ title: '', description: '' });

const fetchRequests = async () => {
    if (!props.hasCustomer) {
        return;
    }
    loading.value = true;
    try {
        const { data } = await axios.get(route('contacts.document-requests.index', props.contactId));
        requests.value = data.document_requests ?? [];
    } catch {
        requests.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRequests);

const closeModal = () => {
    showModal.value = false;
    form.value = { title: '', description: '' };
    formError.value = '';
};

const sendRequest = async () => {
    sending.value = true;
    formError.value = '';
    try {
        await axios.post(route('contacts.document-requests.store', props.contactId), {
            title: form.value.title,
            description: form.value.description || null,
            source_type: props.parentType,
            source_id: props.parentId,
        });
        closeModal();
        showToast('success', 'Successfully sent request.');
        await fetchRequests();
    } catch (error) {
        const msg = error.response?.data?.message
            ?? error.response?.data?.errors?.customer?.[0]
            ?? error.response?.data?.errors?.email?.[0]
            ?? 'Failed to send request.';
        formError.value = msg;
    } finally {
        sending.value = false;
    }
};

const cancelRequest = async (req) => {
    if (!confirm('Cancel this document request?')) {
        return;
    }
    cancellingId.value = req.id;
    try {
        await axios.post(route('document-requests.cancel', req.id));
        await fetchRequests();
    } finally {
        cancellingId.value = null;
    }
};

const statusLabel = (status) => {
    const map = { pending: 'Pending', fulfilled: 'Fulfilled', cancelled: 'Cancelled' };
    return map[status] ?? status;
};

const formatDate = (val) => {
    if (!val) return '';
    return new Date(val).toLocaleDateString();
};
</script>
