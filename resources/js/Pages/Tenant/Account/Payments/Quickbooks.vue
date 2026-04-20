<script setup>
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    quickbooks: {
        type: Object,
        required: true,
    },
    can_connect_quickbooks: {
        type: Boolean,
        default: true,
    },
    oauthNotice: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const breadcrumbItems = computed(() => [
    { label: 'Account', href: route('account.index') },
    { label: 'Payments', href: route('account.payments') },
    { label: 'QuickBooks Online' },
]);

function formatDate(value) {
    if (!value) return null;
    try {
        return new Date(value).toLocaleString();
    } catch (_e) {
        return value;
    }
}

const qbConnectedAt = computed(() => formatDate(props.quickbooks?.connected_at));
const qbTokenExpiresAt = computed(() => formatDate(props.quickbooks?.token_expires_at));
const qbRefreshExpiresAt = computed(() => formatDate(props.quickbooks?.refresh_token_expires_at));

const disconnectingQb = ref(false);
const quickBooksImportRef = ref(null);

function openQuickBooksImport() {
    quickBooksImportRef.value?.openImportModal?.();
}

function disconnectQuickbooks() {
    if (!props.quickbooks?.connected || disconnectingQb.value) return;
    if (!confirm('Disconnect QuickBooks Online from this workspace?')) return;
    disconnectingQb.value = true;
    router.delete(route('quickbooks.destroy'), {
        preserveScroll: true,
        onFinish: () => {
            disconnectingQb.value = false;
        },
    });
}
</script>

<template>
    <Head title="QuickBooks Online payments" />

    <TenantLayout>
        <template #header>
            <div class="w-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">
                    QuickBooks Online
                </h2>
                <p class="mt-2 text-md text-gray-600 dark:text-gray-400">
                    QuickBooks Online is managed under Integrations. This page is legacy; use Integrations → QuickBooks for connect, disconnect, and customer import.
                </p>
                <p class="mt-2">
                    <Link
                        :href="route('integrations')"
                        class="text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        Open Integrations
                    </Link>
                    <span class="mx-2 text-gray-400">·</span>
                    <Link
                        :href="route('quickbooks')"
                        class="text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        QuickBooks settings
                    </Link>
                </p>
                <Link
                    :href="route('account.payments')"
                    class="mt-3 inline-flex items-center gap-1 text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                >
                    <span class="material-icons text-lg">arrow_back</span>
                    All payments
                </Link>
            </div>
        </template>

        <div class="w-full space-y-6">
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ flashError }}
            </div>
            <div
                v-if="oauthNotice"
                :class="oauthNotice.type === 'success'
                    ? 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200'
                    : 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200'"
            >
                {{ oauthNotice.message }}
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    What this is
                </h3>
                <div class="mt-3 space-y-3 text-md leading-relaxed text-gray-600 dark:text-gray-300">
                    <p>
                        <strong class="text-gray-900 dark:text-gray-100">QuickBooks Online</strong> (Intuit) holds your accounting company, customers, and invoices.
                        Connecting here lets Maritime use OAuth to talk to that company on your behalf (starting with payment configuration; more sync features can build on this connection).
                    </p>
                    <p>
                        You can only have <strong class="text-gray-900 dark:text-gray-100">one</strong> active invoice payment processor per workspace. If Stripe Connect is already linked, disconnect it on the Stripe page before connecting QuickBooks.
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Who it is for
                </h3>
                <p class="mt-3 text-md leading-relaxed text-gray-600 dark:text-gray-300">
                    Authorized dealership users who manage <strong class="text-gray-900 dark:text-gray-100">Account → Payments</strong> can connect or disconnect Intuit.
                    Use the same QBO company you bill from so realm ID and company metadata stay accurate.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    How it works
                </h3>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-md leading-relaxed text-gray-600 dark:text-gray-300">
                    <li>Click <strong class="text-gray-900 dark:text-gray-100">Connect with QuickBooks</strong> — you are sent to Intuit to approve access.</li>
                    <li>After approval, Intuit redirects back through Maritime’s central callback; you land here with the connection saved.</li>
                    <li>Access tokens expire regularly; Maritime can refresh them using the encrypted refresh token until Intuit’s refresh window ends.</li>
                    <li>Use <strong class="text-gray-900 dark:text-gray-100">Disconnect</strong> to revoke and clear local tokens when switching to Stripe or another company.</li>
                </ol>
            </section>

            <div
                v-if="!can_connect_quickbooks"
                class="flex gap-3 rounded-lg border border-primary-200/90 bg-primary-50 px-4 py-3 text-md text-primary-950 dark:border-primary-800 dark:bg-primary-950/85 dark:text-primary-50"
                role="status"
            >
                <span class="material-icons shrink-0 text-[20px] text-primary-700 dark:text-primary-200" aria-hidden="true">info</span>
                <p class="leading-relaxed">
                    Stripe is connected for this workspace. Disconnect Stripe on the Stripe page to connect QuickBooks Online here.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                        <span class="material-icons text-[18px] text-emerald-600 dark:text-emerald-400">receipt_long</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900 dark:text-white">Connection</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Intuit OAuth
                            <span
                                v-if="quickbooks?.environment"
                                class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-gray-500 dark:bg-gray-700 dark:text-gray-300"
                            >{{ quickbooks.environment }}</span>
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium"
                        :class="quickbooks?.connected
                            ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700/80 dark:text-gray-200'"
                    >
                        {{ quickbooks?.connected ? 'Connected' : 'Not connected' }}
                    </span>
                </div>

                <div
                    class="mt-5 divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-700 dark:border-gray-700"
                >
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Realm ID</span>
                        <code
                            v-if="quickbooks?.realm_id"
                            class="rounded bg-gray-50 px-2 py-0.5 font-mono text-sm text-gray-700 dark:bg-gray-900/80 dark:text-gray-200"
                        >{{ quickbooks.realm_id }}</code>
                        <span v-else class="text-md text-gray-400 dark:text-gray-500">—</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Environment</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ quickbooks?.environment || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Company name</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ quickbooks?.company_name || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Legal name</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ quickbooks?.legal_name || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Country</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ quickbooks?.country || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Account email</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ quickbooks?.email || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Connected at</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ qbConnectedAt || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Access token expires</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ qbTokenExpiresAt || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Refresh token expires</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ qbRefreshExpiresAt || '—' }}</span>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 dark:border-gray-700 sm:flex-row">
                    <a
                        v-if="can_connect_quickbooks"
                        :href="route('quickbooks.connect')"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-md font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                    >
                        <span class="material-icons text-lg">{{ quickbooks?.connected ? 'sync' : 'link' }}</span>
                        {{ quickbooks?.connected ? 'Reconnect QuickBooks' : 'Connect with QuickBooks' }}
                    </a>
                    <button
                        v-else
                        type="button"
                        disabled
                        class="inline-flex flex-1 cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-emerald-200/80 bg-emerald-100 px-4 py-2.5 text-md font-medium text-emerald-900/70 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-100/70"
                    >
                        <span class="material-icons text-lg" aria-hidden="true">block</span>
                        Unavailable while Stripe is active
                    </button>
                    <button
                        v-if="quickbooks?.connected"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-md font-medium text-gray-800 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                        :disabled="disconnectingQb"
                        @click="disconnectQuickbooks"
                    >
                        <span
                            class="material-icons text-lg"
                            :class="{ 'animate-spin': disconnectingQb }"
                        >{{ disconnectingQb ? 'sync' : 'link_off' }}</span>
                        {{ disconnectingQb ? 'Disconnecting…' : 'Disconnect' }}
                    </button>
                    <button
                        v-if="quickbooks?.connected"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-md font-medium text-emerald-900 shadow-sm transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-100 dark:hover:bg-emerald-900/60"
                        @click="openQuickBooksImport"
                    >
                        <span class="material-icons text-lg" aria-hidden="true">download</span>
                        Import customers
                    </button>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-gray-400 dark:text-gray-500">
                    OAuth tokens are encrypted at rest. Use the same Intuit company you bill from so invoices and customers stay in sync.
                </p>
            </div>

            <QuickBooksImport
                v-if="quickbooks?.connected"
                ref="quickBooksImportRef"
                :allow-type-choice="true"
            />
        </div>
    </TenantLayout>
</template>
