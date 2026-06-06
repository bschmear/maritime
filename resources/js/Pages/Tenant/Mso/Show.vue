<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
                            <dd class="text-gray-900 dark:text-white">{{ assetUnit.display_name }}</dd>
                        </div>
                        <div v-if="lineItem">
                            <dt class="text-gray-500 dark:text-gray-400">Line item</dt>
                            <dd class="text-gray-900 dark:text-white">{{ lineItem.name }}</dd>
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
    </TenantLayout>
</template>
