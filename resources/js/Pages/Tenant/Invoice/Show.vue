<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import InvoiceDocumentBody from '@/Components/Tenant/InvoiceDocumentBody.vue';
import InvoicePreview from '@/Components/Tenant/InvoicePreview.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref, watch } from 'vue';

const page = usePage();
const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            showToast('success', msg);
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (msg) => {
        if (msg) {
            showToast('error', msg);
        }
    },
    { immediate: true },
);

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
});

const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';

const previewOpen = ref(false);

const statusOptions = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentTermOptions = computed(() => props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? []);

const statusInfo = computed(() => {
    const s = props.record?.status;
    return (
        statusOptions.value.find(o => o.id == s || o.value === s) ?? {
            id: 0,
            value: s,
            name: s ?? 'Unknown',
            color: 'gray',
            bgClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        }
    );
});

const paymentTermLabel = computed(() => {
    const raw = props.record?.payment_term;
    const opt = paymentTermOptions.value.find(
        o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw),
    );
    return opt?.name ?? raw ?? '—';
});

const STATUS_TEXT = {
    gray: 'text-gray-700 dark:text-gray-300',
    blue: 'text-blue-700 dark:text-blue-300',
    yellow: 'text-yellow-700 dark:text-yellow-300',
    green: 'text-green-700 dark:text-green-300',
    red: 'text-red-700 dark:text-red-300',
    orange: 'text-orange-700 dark:text-orange-300',
    purple: 'text-purple-700 dark:text-purple-300',
    slate: 'text-slate-700 dark:text-slate-300',
};

const statusTextClass = computed(
    () => STATUS_TEXT[statusInfo.value?.color] ?? 'text-gray-700 dark:text-gray-300',
);

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : null;

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Invoices', href: route('invoices.index') },
    { label: props.record.display_name || `Invoice #${props.record.sequence ?? props.record.id}` },
]);

const logoUrl = computed(() => props.account?.logo_url ?? null);

const sendToCustomer = () => {
    if (!confirm('Email the customer a link to view this invoice?')) {
        return;
    }
    router.post(route('invoices.send-to-customer', props.record.id));
};

const contactShowHref = computed(() =>
    props.record.contact_id ? route('contacts.show', props.record.contact_id) : null,
);
const transactionShowHref = computed(() =>
    props.record.transaction_id ? route('transactions.show', props.record.transaction_id) : null,
);
const contractShowHref = computed(() =>
    props.record.contract_id ? route('contracts.show', props.record.contract_id) : null,
);
</script>

<template>
    <Head :title="record.display_name || `Invoice #${record.sequence ?? record.id}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ record.display_name || `Invoice #${record.sequence ?? record.id}` }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            @click="previewOpen = true"
                        >
                            <span class="material-icons text-[16px]">visibility</span>
                            Customer preview
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            @click="sendToCustomer"
                        >
                            <span class="material-icons text-[16px]">send</span>
                            Send to customer
                        </button>
                        <a
                            :href="route('invoices.edit', record.id)"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </a>
                    </div>
                </div>
            </div>
        </template>

        <InvoicePreview
            v-if="previewOpen"
            :record="record"
            :account="account"
            :enum-options="enumOptions"
            :logo-url="logoUrl"
            @close="previewOpen = false"
        />

        <div class="w-full">
            <div class="grid grid-cols-12 gap-4 p-4">
                <div class="col-span-12 xl:col-span-9">
                    <InvoiceDocumentBody
                        :record="record"
                        :account="account"
                        :enum-options="enumOptions"
                        :logo-url="logoUrl"
                    />
                </div>

                <div class="col-span-12 xl:col-span-3">
                    <div class="space-y-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:space-y-6 md:p-6">
                        <div
                            v-if="record.paid_at"
                            class="flex items-center rounded-lg bg-green-50 p-4 text-base font-medium text-green-800 dark:bg-gray-700 dark:text-green-300"
                            role="alert"
                        >
                            <span class="material-icons me-2 shrink-0 text-[18px]">info</span>
                            <div>Invoice paid</div>
                        </div>

                        <div
                            v-else-if="record.due_at && new Date(record.due_at) < new Date() && record.amount_due > 0"
                            class="flex items-center rounded-lg bg-red-50 p-4 text-base font-medium text-red-800 dark:bg-red-900/20 dark:text-red-300"
                            role="alert"
                        >
                            <span class="material-icons me-2 shrink-0 text-[18px]">warning</span>
                            <div>Invoice overdue</div>
                        </div>

                        <h2 class="text-lg text-gray-500 dark:text-gray-400">Details</h2>

                        <div class="flex items-center justify-between border-b border-t border-gray-100 py-4 dark:border-gray-700">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ formatCurrency(record.total) }}
                            </h3>
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    statusInfo.bgClass || 'bg-gray-100 text-gray-700',
                                ]"
                            >
                                {{ statusInfo.name }}
                            </span>
                        </div>

                        <ul class="max-w-md space-y-3 border-b border-gray-100 pb-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <li v-if="contactShowHref">
                                <Link
                                    :href="contactShowHref"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    View contact
                                </Link>
                            </li>
                            <li v-if="transactionShowHref">
                                <Link
                                    :href="transactionShowHref"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    View deal
                                </Link>
                            </li>
                            <li v-if="contractShowHref">
                                <Link
                                    :href="contractShowHref"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    View contract
                                </Link>
                            </li>
                            <li v-if="record.customer_name" class="flex items-start gap-2">
                                <span class="material-icons shrink-0 text-[20px]">person</span>
                                <span>
                                    <span class="font-medium text-gray-900 dark:text-white">Customer:</span>
                                    {{ record.customer_name }}
                                </span>
                            </li>
                            <li v-if="record.due_at" class="flex items-start gap-2">
                                <span class="material-icons shrink-0 text-[20px]">event</span>
                                <span>
                                    <span class="font-medium text-gray-900 dark:text-white">Due:</span>
                                    {{ formatDate(record.due_at) }}
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-icons shrink-0 text-[20px]">credit_card</span>
                                <span>
                                    <span class="font-medium text-gray-900 dark:text-white">Terms:</span>
                                    {{ paymentTermLabel }}
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-icons shrink-0 text-[20px]">payments</span>
                                <span>
                                    <span class="font-medium text-gray-900 dark:text-white">Currency:</span>
                                    {{ record.currency || 'USD' }}
                                </span>
                            </li>
                            <li v-if="record.amount_due != null" class="flex items-start gap-2">
                                <span class="material-icons shrink-0 text-[20px]">account_balance_wallet</span>
                                <span>
                                    <span class="font-medium text-gray-900 dark:text-white">Amount due:</span>
                                    <span :class="['font-bold', statusTextClass]">{{ formatCurrency(record.amount_due) }}</span>
                                </span>
                            </li>
                        </ul>

                        <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700">
                            <li class="mb-4 ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span class="material-icons text-[20px] text-green-500">check_circle</span>
                                </span>
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Created</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(record.created_at) }}</time>
                                </div>
                            </li>
                            <li class="mb-4 ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span
                                        :class="[
                                            'material-icons text-[20px]',
                                            record.sent_at ? 'text-green-500' : 'text-gray-300 dark:text-gray-600',
                                        ]"
                                    >check_circle</span>
                                </span>
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Sent</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ record.sent_at ? formatDate(record.sent_at) : 'Pending' }}
                                    </time>
                                </div>
                            </li>
                            <li class="mb-4 ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span
                                        :class="[
                                            'material-icons text-[20px]',
                                            record.viewed_at ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600',
                                        ]"
                                    >check_circle</span>
                                </span>
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Viewed</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ record.viewed_at ? formatDate(record.viewed_at) : 'Not yet' }}
                                    </time>
                                </div>
                            </li>
                            <li class="ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span
                                        :class="[
                                            'material-icons text-[20px]',
                                            record.paid_at ? 'text-green-600' : 'text-gray-300 dark:text-gray-600',
                                        ]"
                                    >check_circle</span>
                                </span>
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Paid</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ record.paid_at ? formatDate(record.paid_at) : 'Pending' }}
                                    </time>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
