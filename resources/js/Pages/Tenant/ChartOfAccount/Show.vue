<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { buildResourceRouteParams } from '@/Utils/resourceRoutes.js';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'chart-of-accounts' },
    recordTitle: { type: String, default: 'Chart of account' },
    apBills: { type: Array, default: () => [] },
    quickbooks: { type: Object, default: () => ({}) },
});

const accountLabel = computed(() =>
    props.record.fully_qualified_name
    || props.record.display_name
    || props.record.name
    || `Account #${props.record.id}`,
);

const shortName = computed(() => props.record.name || accountLabel.value);

const indexHref = computed(() => route(`${props.recordType}.index`));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bills', href: route('bills.index') },
    { label: 'Chart of accounts', href: indexHref.value },
    { label: accountLabel.value },
]);

const parentAccount = computed(() => props.record.parent ?? null);

const subAccounts = computed(() => props.record.children ?? []);

const primaryBills = computed(() => props.record.bills ?? []);

const lineItemUsage = computed(() => props.record.bill_items ?? props.record.billItems ?? []);

const apAccountBills = computed(() => props.apBills ?? []);

const isActive = computed(() => {
    const value = props.record.active;
    return value === true || value === 1 || value === '1';
});

const quickbooksAccountUrl = computed(() => {
    const id = props.record.quickbooks_account_id;
    if (!id) {
        return null;
    }

    const host = import.meta.env.VITE_QUICKBOOKS_ENV === 'production'
        ? 'https://qbo.intuit.com'
        : 'https://sandbox.qbo.intuit.com';

    return `${host}/app/register?accountId=${id}`;
});

const accountTypeBadgeClass = computed(() => {
    const type = String(props.record.account_type || '').toLowerCase();

    if (type.includes('payable') || type.includes('receivable')) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200';
    }
    if (type.includes('expense') || type.includes('cost')) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }
    if (type.includes('bank') || type.includes('cash')) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    }
    if (type.includes('income') || type.includes('revenue')) {
        return 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200';
    }
    if (type.includes('equity')) {
        return 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
});

const usageSummary = computed(() => ({
    subAccounts: subAccounts.value.length,
    primaryBills: primaryBills.value.length,
    lineItems: lineItemUsage.value.length,
    apBills: apAccountBills.value.length,
}));

function chartOfAccountHref(id) {
    if (!id) {
        return null;
    }

    try {
        return route('chart-of-accounts.show', id);
    } catch {
        return null;
    }
}

function billShowHref(billId) {
    if (!billId) {
        return null;
    }

    try {
        return route('bills.show', buildResourceRouteParams('bills', billId));
    } catch {
        return null;
    }
}

function billLabel(bill) {
    return bill?.display_name || (bill?.sequence != null ? `BILL-${bill.sequence}` : `Bill #${bill?.id}`);
}

function accountRowLabel(account) {
    return account?.fully_qualified_name || account?.name || account?.display_name || `Account #${account?.id}`;
}

function formatBoolean(value) {
    if (value === true || value === 1 || value === '1') {
        return 'Yes';
    }
    if (value === false || value === 0 || value === '0') {
        return 'No';
    }

    return '—';
}

function formatCurrency(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `$${parseFloat(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    try {
        const raw = typeof value === 'string' ? value.split('T')[0] : value;
        return new Date(`${raw}T00:00:00`).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return value;
    }
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}
</script>

<template>
    <Head :title="`${accountLabel} — Chart of account`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ accountLabel }}
                    </h2>
                    <div class="flex shrink-0 items-center gap-2">
                        <Link
                            :href="indexHref"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-md font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Chart of accounts
                        </Link>
                        <a
                            v-if="quickbooksAccountUrl"
                            :href="quickbooksAccountUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-[16px]">open_in_new</span>
                            QuickBooks
                        </a>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Main column -->
                <div class="space-y-6 lg:col-span-8">
                    <!-- Hero card -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="bg-gradient-to-r from-slate-700 via-slate-800 to-slate-900 px-6 py-5 dark:from-slate-800 dark:via-slate-900 dark:to-black">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                                            Chart of account
                                        </span>
                                        <span
                                            v-if="record.account_type"
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="accountTypeBadgeClass"
                                        >
                                            {{ record.account_type }}
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="isActive
                                                ? 'bg-emerald-400/20 text-emerald-100'
                                                : 'bg-white/10 text-slate-300'"
                                        >
                                            {{ isActive ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <h1 class="text-2xl font-bold text-white sm:text-3xl">{{ shortName }}</h1>
                                    <p v-if="record.fully_qualified_name && record.fully_qualified_name !== shortName" class="mt-1 text-sm text-slate-300">
                                        {{ record.fully_qualified_name }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-400">QuickBooks ID</div>
                                    <div class="mt-1 font-mono text-lg text-white">
                                        {{ record.quickbooks_account_id || '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6 p-6">
                            <section class="space-y-4">
                                <h3 class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Account details
                                </h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Account name
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.name || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Full name
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.fully_qualified_name || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Detail type
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.detail_type || '—' }}</div>
                                    </div>
                                    <div v-if="parentAccount || record.parent_id">
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Parent account
                                        </div>
                                        <Link
                                            v-if="chartOfAccountHref(parentAccount?.id ?? record.parent_id)"
                                            :href="chartOfAccountHref(parentAccount?.id ?? record.parent_id)"
                                            class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                        >
                                            {{ parentAccount ? accountRowLabel(parentAccount) : `Account #${record.parent_id}` }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-900 dark:text-white">
                                            {{ record.parent_id ? `Account #${record.parent_id}` : '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Status
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ formatBoolean(record.active) }}</div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- Sub-accounts -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Sub-accounts</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Child accounts nested under this account in QuickBooks.
                            </p>
                        </div>

                        <div v-if="subAccounts.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Account
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Type
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Detail type
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            QBO ID
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Active
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="child in subAccounts" :key="child.id">
                                        <td class="px-5 py-3 text-sm">
                                            <Link
                                                v-if="chartOfAccountHref(child.id)"
                                                :href="chartOfAccountHref(child.id)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ accountRowLabel(child) }}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ child.account_type || '—' }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ child.detail_type || '—' }}</td>
                                        <td class="px-5 py-3 font-mono text-sm text-gray-700 dark:text-gray-300">{{ child.quickbooks_account_id || '—' }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatBoolean(child.active) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-6 py-12 text-center">
                            <span class="material-icons mb-3 text-[40px] text-gray-300 dark:text-gray-600">account_tree</span>
                            <p class="text-md text-gray-400 dark:text-gray-500">No sub-accounts under this account</p>
                        </div>
                    </div>

                    <!-- Bills using this as primary account -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Bills — primary account</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Bills where this account is set as the bill-level primary account.
                            </p>
                        </div>

                        <div v-if="primaryBills.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Bill
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Vendor
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Date
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Total
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="bill in primaryBills" :key="bill.id">
                                        <td class="px-5 py-3 text-sm">
                                            <Link
                                                v-if="billShowHref(bill.id)"
                                                :href="billShowHref(bill.id)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ billLabel(bill) }}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ bill.vendor?.display_name || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ formatDate(bill.txn_date) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-900 dark:text-white">
                                            {{ formatCurrency(bill.total_amt) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(bill.balance) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-6 py-12 text-center">
                            <span class="material-icons mb-3 text-[40px] text-gray-300 dark:text-gray-600">receipt_long</span>
                            <p class="text-md text-gray-400 dark:text-gray-500">No bills linked as primary account</p>
                        </div>
                    </div>

                    <!-- AP bills (when this is the AP account) -->
                    <div
                        v-if="apAccountBills.length > 0"
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Bills — AP account</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Bills using this account as the accounts payable account in QuickBooks.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Bill
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Vendor
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Date
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Total
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="bill in apAccountBills" :key="`ap-${bill.id}`">
                                        <td class="px-5 py-3 text-sm">
                                            <Link
                                                v-if="billShowHref(bill.id)"
                                                :href="billShowHref(bill.id)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ billLabel(bill) }}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ bill.vendor?.display_name || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ formatDate(bill.txn_date) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-900 dark:text-white">
                                            {{ formatCurrency(bill.total_amt) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(bill.balance) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Line item usage -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Bill line items</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Expense lines coded to this account on bills.
                            </p>
                        </div>

                        <div v-if="lineItemUsage.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Bill
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Description
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Vendor
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="item in lineItemUsage" :key="item.id">
                                        <td class="px-5 py-3 text-sm">
                                            <Link
                                                v-if="billShowHref(item.bill?.id ?? item.bill_id)"
                                                :href="billShowHref(item.bill?.id ?? item.bill_id)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ billLabel(item.bill) || `Bill #${item.bill_id}` }}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ item.description || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ item.bill?.vendor?.display_name || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(item.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-6 py-12 text-center">
                            <span class="material-icons mb-3 text-[40px] text-gray-300 dark:text-gray-600">list_alt</span>
                            <p class="text-md text-gray-400 dark:text-gray-500">No bill line items coded to this account</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4 lg:col-span-4">
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Usage
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Sub-accounts</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ usageSummary.subAccounts }}</span>
                            </li>
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Primary bills</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ usageSummary.primaryBills }}</span>
                            </li>
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">AP bills</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ usageSummary.apBills }}</span>
                            </li>
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Line items</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ usageSummary.lineItems }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                QuickBooks
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Connection</span>
                                <span class="text-right text-gray-900 dark:text-white">
                                    {{ quickbooks?.connected ? 'Connected' : 'Not connected' }}
                                </span>
                            </li>
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Account ID</span>
                                <span class="text-right font-mono text-gray-900 dark:text-white">
                                    {{ record.quickbooks_account_id || '—' }}
                                </span>
                            </li>
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Account type</span>
                                <span class="text-right text-gray-900 dark:text-white">
                                    {{ record.account_type || '—' }}
                                </span>
                            </li>
                        </ul>
                        <div v-if="quickbooksAccountUrl" class="border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                            <a
                                :href="quickbooksAccountUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Open in QuickBooks
                                <span class="material-icons text-base leading-none">open_in_new</span>
                            </a>
                        </div>
                    </div>

                    <div
                        v-if="parentAccount"
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Parent account
                            </span>
                        </div>
                        <div class="p-5">
                            <Link
                                v-if="chartOfAccountHref(parentAccount.id)"
                                :href="chartOfAccountHref(parentAccount.id)"
                                class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                <span class="material-icons text-base leading-none">account_balance</span>
                                {{ accountRowLabel(parentAccount) }}
                            </Link>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Record
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Helmful ID</span>
                                <span class="font-mono text-gray-900 dark:text-white">{{ record.id }}</span>
                            </li>
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.created_at) }}</span>
                            </li>
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Updated</span>
                                <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.updated_at) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
