<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    breadcrumbs: {
        type: Object,
        default: () => ({}),
    },
    integrations: {
        type: Array,
        default: () => [],
    },
    activeIntegrations: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];
    if (props.breadcrumbs?.current) {
        items.push({ label: props.breadcrumbs.current });
    }
    return items;
});

function isConnected(integrationId) {
    return (props.activeIntegrations ?? []).some((row) => {
        const t = row.integration_type;
        return Number(t) === Number(integrationId) || t === integrationId;
    });
}

function configureHref(slug) {
    if (slug === 'mailchimp') {
        return route('mailchimp');
    }
    return null;
}
</script>

<template>
    <Head title="Integrations" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    Integrations
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Connect third-party services to your workspace.
                </p>
            </div>
        </template>

        <div class="mx-auto w-full px-4 py-6">
            <ul
                class="grid list-none grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                role="list"
            >
                <li
                    v-for="item in integrations"
                    :key="item.id"
                    role="listitem"
                    class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ item.name }}
                        </p>
                        <span
                            v-if="isConnected(item.id)"
                            class="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
                        >
                            Connected
                        </span>
                    </div>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        {{ item.description }}
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-700/80">
                        <Link
                            v-if="configureHref(item.slug)"
                            :href="configureHref(item.slug)"
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-primary-700 sm:flex-none"
                        >
                            {{ isConnected(item.id) ? 'Manage' : 'Connect' }}
                        </Link>
                        <span
                            v-else
                            class="inline-flex flex-1 items-center justify-center rounded-lg border border-dashed border-gray-200 px-3 py-2 text-center text-xs text-gray-400 dark:border-gray-600 dark:text-gray-500 sm:flex-none"
                        >
                            Coming soon
                        </span>
                    </div>
                </li>
            </ul>
        </div>
    </TenantLayout>
</template>
