<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    breadcrumbs: {
        type: Object,
        default: () => ({}),
    },
    integration: {
        type: Object,
        required: true,
    },
    hasMailchimpToken: {
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
});

const page = usePage();

onMounted(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.has('mailchimp_connected') || url.searchParams.has('mailchimp_error')) {
        url.searchParams.delete('mailchimp_connected');
        url.searchParams.delete('mailchimp_error');
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
    if (!confirm('Remove the Mailchimp connection for this workspace?')) {
        return;
    }
    router.delete(route('mailchimp.destroy'));
}
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
                <template v-if="hasMailchimpToken">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Mailchimp is connected. Use the contacts list to import or export audiences.
                    </p>
                    <p
                        v-if="currentIntegration?.last_synced_at"
                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Last sync: {{ currentIntegration.last_synced_at }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="route('contacts.index')"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Go to contacts
                        </Link>
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
                        Connect your Mailchimp account to sync audiences with contacts and leads.
                    </p>
                    <a
                        :href="route('mailchimp.connect')"
                        class="mt-4 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Connect with Mailchimp
                    </a>
                </template>
            </div>
        </div>
    </TenantLayout>
</template>
