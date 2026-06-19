<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useQuickBooksApSyncOverlay } from '@/composables/useQuickBooksApSyncOverlay';

const page = usePage();
const { handleCreateFlash } = useQuickBooksApSyncOverlay();

watch(
    () => page.props.flash,
    (flash) => handleCreateFlash(flash),
    { immediate: true, deep: true },
);

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'bill-payments' },
    quickbooks: { type: Object, default: () => ({}) },
});

const pushing = ref(false);
const pulling = ref(false);

const label = computed(() => props.record.display_name || `Payment #${props.record.id}`);
const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const canPush = computed(() =>
    props.quickbooks?.connected && !props.record.quickbooks_bill_payment_id,
);
const canPull = computed(() =>
    props.quickbooks?.connected
    && props.quickbooks?.sync_bill_payments_enabled
    && !!props.record.quickbooks_bill_payment_id,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bill payments', href: indexHref.value },
    { label: label.value },
]);

function pushToQuickbooks() {
    pushing.value = true;
    router.post(route('bill-payments.push-to-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { pushing.value = false; },
    });
}

function pullFromQuickbooks() {
    pulling.value = true;
    router.post(route('bill-payments.pull-from-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { pulling.value = false; },
    });
}
</script>

<template>
    <Head :title="label" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ label }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canPush"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        :disabled="pushing"
                        @click="pushToQuickbooks"
                    >
                        {{ pushing ? 'Syncing…' : 'Push to QuickBooks' }}
                    </button>
                    <button
                        v-if="canPull"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        :disabled="pulling"
                        @click="pullFromQuickbooks"
                    >
                        {{ pulling ? 'Refreshing…' : 'Refresh from QuickBooks' }}
                    </button>
                    <Link
                        :href="editHref"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Edit
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase text-gray-500">Vendor</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.vendor?.display_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-500">Amount</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.total_amt }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-500">Pay type</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.pay_type || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-500">QuickBooks ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.quickbooks_bill_payment_id || '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </TenantLayout>
</template>
