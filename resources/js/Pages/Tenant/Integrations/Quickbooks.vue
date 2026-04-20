<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    breadcrumbs: {
        type: Object,
        default: () => ({}),
    },
    integration: {
        type: Object,
        required: true,
    },
    hasQuickbooksToken: {
        type: Boolean,
        default: false,
    },
    currentIntegration: {
        type: Object,
        default: null,
    },
    oauthNotice: {
        type: Object,
        default: null,
    },
    quickbooks: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const quickBooksImportRef = ref(null);

onMounted(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.has('qbo_connected') || url.searchParams.has('qbo_error')) {
        url.searchParams.delete('qbo_connected');
        url.searchParams.delete('qbo_error');
        const next = url.pathname + (url.searchParams.toString() ? `?${url.searchParams.toString()}` : '');
        window.history.replaceState({}, '', next);
    }
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];
    const links = props.breadcrumbs?.links ?? [];
    for (const link of links) {
        if (link?.url && link?.name) {
            items.push({ label: link.name, href: link.url });
        }
    }
    if (props.breadcrumbs?.current) {
        items.push({ label: props.breadcrumbs.current });
    }
    return items;
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => {
    const fromFlash = page.props.flash?.error;
    if (fromFlash) {
        return fromFlash;
    }
    const errs = page.props.errors;
    if (!errs || typeof errs !== 'object') {
        return null;
    }
    const flat = Object.values(errs).flat().filter(Boolean);
    return flat.length ? flat.join(' ') : null;
});

function disconnect() {
    if (!window.confirm('Remove the QuickBooks Online connection for this workspace?')) {
        return;
    }
    router.delete(route('quickbooks.destroy'));
}

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
</script>

<template>
    <Head :title="integration.name" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ integration.name }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ integration.description }}
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-4 px-4 py-6">
            <div
                v-if="oauthNotice?.type === 'success'"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ oauthNotice.message }}
            </div>
            <div
                v-if="oauthNotice?.type === 'error'"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ oauthNotice.message }}
            </div>
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ flashError }}
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <template v-if="hasQuickbooksToken">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        QuickBooks Online is connected. Use the contacts or leads list (gear menu) to import QuickBooks customers, or import here.
                    </p>
                    <p
                        v-if="currentIntegration?.last_synced_at"
                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Last sync: {{ currentIntegration.last_synced_at }}
                    </p>

                    <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                        <div v-if="quickbooks?.realm_id" class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Realm ID</dt>
                            <dd class="font-mono text-xs text-gray-900 dark:text-gray-100">{{ quickbooks.realm_id }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Environment</dt>
                            <dd class="text-gray-900 dark:text-white">{{ quickbooks?.environment || '—' }}</dd>
                        </div>
                        <div v-if="quickbooks?.company_name" class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Company</dt>
                            <dd class="text-right text-gray-900 dark:text-white">{{ quickbooks.company_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Connected</dt>
                            <dd class="text-gray-900 dark:text-white">{{ qbConnectedAt || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Access token expires</dt>
                            <dd class="text-gray-900 dark:text-white">{{ qbTokenExpiresAt || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Refresh token expires</dt>
                            <dd class="text-gray-900 dark:text-white">{{ qbRefreshExpiresAt || '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <Link
                            :href="route('contacts.index')"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Go to contacts
                        </Link>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="quickBooksImportRef?.openImportModal?.()"
                        >
                            Import customers
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="disconnect"
                        >
                            Disconnect
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Connect your QuickBooks Online company to import customers as contacts or leads (export to QBO can build on this connection later).
                    </p>
                    <a
                        :href="route('quickbooks.connect')"
                        class="mt-4 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Connect with QuickBooks
                    </a>
                </template>
            </div>

            <QuickBooksImport
                v-if="hasQuickbooksToken"
                ref="quickBooksImportRef"
                :allow-type-choice="true"
            />
        </div>
    </TenantLayout>
</template>
