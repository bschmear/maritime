<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    msoRecord: { type: Object, required: true },
    transaction: { type: Object, default: null },
    assetUnit: { type: Object, default: null },
    lineItem: { type: Object, default: null },
    sourceDocument: { type: Object, default: null },
    outputDocument: { type: Object, default: null },
    builderUrl: { type: String, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const showDeleteModal = ref(false);
const deleting = ref(false);
const resetTransaction = ref(true);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'MSO', href: route('mso.index', { tab: 'existing' }) },
    { label: props.msoRecord.display_name },
]);

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function openDeleteModal() {
    resetTransaction.value = true;
    showDeleteModal.value = true;
}

function closeDeleteModal() {
    if (!deleting.value) {
        showDeleteModal.value = false;
    }
}

function confirmDelete() {
    deleting.value = true;
    router.delete(route('mso.records.destroy', props.msoRecord.id), {
        data: {
            reset_transaction: resetTransaction.value,
        },
        onFinish: () => {
            deleting.value = false;
            showDeleteModal.value = false;
        },
    });
}
</script>

<template>
    <Head :title="msoRecord.display_name" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ msoRecord.display_name }}</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                            Status: {{ msoRecord.status_label }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="builderUrl && msoRecord.status === 'draft'"
                            :href="builderUrl"
                            class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Continue editing
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-950/40"
                            @click="openDeleteModal"
                        >
                            Delete
                        </button>
                        <Link
                            :href="route('mso.index', { tab: 'existing' })"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        >
                            Back to MSO
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="flash.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ flash.error }}
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filled MSO</h3>
                <div v-if="outputDocument" class="mt-4 space-y-3">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ outputDocument.display_name }}</p>
                    <div class="flex flex-wrap gap-3">
                        <a
                            :href="outputDocument.preview_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Preview PDF
                        </a>
                        <a
                            :href="outputDocument.download_url"
                            class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Download PDF
                        </a>
                    </div>
                    <iframe
                        v-if="outputDocument.preview_url"
                        :src="outputDocument.preview_url"
                        class="h-[640px] w-full rounded-lg border border-gray-200 dark:border-gray-700"
                        title="MSO output preview"
                    />
                </div>
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">No output PDF has been generated yet.</p>
            </section>

            <section class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Details</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Submitted</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(msoRecord.submitted_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(msoRecord.created_at) }}</dd>
                        </div>
                        <div v-if="transaction">
                            <dt class="text-gray-500 dark:text-gray-400">Deal</dt>
                            <dd>
                                <Link :href="route('transactions.show', transaction.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ transaction.display_name }}
                                </Link>
                            </dd>
                        </div>
                        <div v-if="assetUnit">
                            <dt class="text-gray-500 dark:text-gray-400">Asset unit</dt>
                            <dd>
                                <Link :href="route('assetunits.show', assetUnit.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ assetUnit.display_name }}
                                </Link>
                            </dd>
                        </div>
                        <div v-if="lineItem">
                            <dt class="text-gray-500 dark:text-gray-400">Line item</dt>
                            <dd>
                                <Link
                                    v-if="lineItem.transaction_id"
                                    :href="route('transactions.show', lineItem.transaction_id)"
                                    class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ lineItem.name }}
                                </Link>
                                <span v-else class="text-gray-900 dark:text-white">{{ lineItem.name }}</span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="sourceDocument" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Original MSO</h3>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ sourceDocument.display_name }}</p>
                    <a
                        :href="sourceDocument.download_url"
                        class="mt-3 inline-flex text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        Download original
                    </a>
                </div>
            </section>
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete MSO</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Are you sure you want to delete this MSO? This cannot be undone.
                </p>

                <label
                    v-if="transaction"
                    class="mt-4 flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/50"
                >
                    <input
                        v-model="resetTransaction"
                        type="checkbox"
                        class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-200">
                        Reset the deal so an MSO is required again for this unit.
                    </span>
                </label>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        :disabled="deleting"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="deleting"
                        @click="confirmDelete"
                    >
                        {{ deleting ? 'Deleting…' : 'Delete MSO' }}
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
