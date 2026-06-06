<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    transaction: {
        type: Object,
        required: true,
    },
    lineItem: {
        type: Object,
        required: true,
    },
    assetUnit: {
        type: Object,
        required: true,
    },
    sourceDocument: {
        type: Object,
        default: null,
    },
    msoRecord: {
        type: Object,
        required: true,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Pending MSOs', href: route('mso.pending') },
    { label: `Create MSO — ${props.transaction.display_name}` },
]);

function formatDate(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return value;
    }
}
</script>

<template>
    <Head :title="`Create MSO — ${transaction.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
            <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Create MSO</h2>
                    <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                        Review the source MSO and deal details. Full form fill is coming soon.
                    </p>
                </div>
                <Link
                    :href="route('mso.pending')"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Back to pending MSOs
                </Link>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Source MSO (asset unit)</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ assetUnit.display_name }}</p>

                <div v-if="sourceDocument" class="mt-4 space-y-2">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ sourceDocument.display_name }}</p>
                    <p class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ sourceDocument.file_extension }}</p>
                    <a
                        :href="sourceDocument.download_url"
                        class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span class="material-icons text-base">download</span>
                        Download source MSO
                    </a>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No MSO document is attached to this unit yet. Upload one on the asset unit with document role "mso".
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Deal</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            <Link
                                :href="route('transactions.show', transaction.id)"
                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400"
                            >
                                {{ transaction.display_name }}
                            </Link>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Customer</dt>
                        <dd class="text-gray-900 dark:text-white">{{ transaction.customer_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="text-gray-900 dark:text-white">{{ transaction.customer_email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                        <dd class="text-gray-900 dark:text-white">{{ transaction.customer_phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Completed</dt>
                        <dd class="text-gray-900 dark:text-white">{{ formatDate(transaction.closed_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Line item</dt>
                        <dd class="text-gray-900 dark:text-white">{{ lineItem.name || 'Asset unit line' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="mt-6 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-900/40">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">MSO form</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Placeholder — MSO record #{{ msoRecord.id }} ({{ msoRecord.status }}). Filling out and submitting the updated MSO will be added in a future release.
            </p>
        </section>
    </TenantLayout>
</template>
