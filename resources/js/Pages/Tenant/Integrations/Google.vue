<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    breadcrumbs: { type: Object, default: () => ({}) },
    integration: { type: Object, required: true },
    isConnected: { type: Boolean, default: false },
    googleEmail: { type: String, default: null },
    sheetSettings: { type: Object, default: () => ({}) },
    oauthNotice: { type: Object, default: null },
});

const pushing = ref(false);
const pulling = ref(false);
const recreating = ref(false);
const actionMessage = ref('');
const actionError = ref('');

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];
    if (props.breadcrumbs?.links) {
        props.breadcrumbs.links.forEach((link) => items.push({ label: link.name, href: link.url }));
    }
    if (props.breadcrumbs?.current) {
        items.push({ label: props.breadcrumbs.current });
    }
    return items;
});

async function pushSheet() {
    pushing.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.push'));
        actionMessage.value = data.message
            ?? `Synced ${data.row_count ?? 0} units to Google Sheets.`;
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Push failed.';
    } finally {
        pushing.value = false;
    }
}

async function pullSheet() {
    pulling.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.pull'));
        actionMessage.value = data.message
            ?? `Updated ${data.updated ?? 0} units from Google Sheets.`;
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Import failed.';
    } finally {
        pulling.value = false;
    }
}

async function recreateSheet() {
    recreating.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.recreate'));
        actionMessage.value = 'Inventory sheet recreated.';
        if (data.spreadsheet_url) {
            actionMessage.value += ` ${data.spreadsheet_url}`;
        }
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Recreate failed.';
    } finally {
        recreating.value = false;
    }
}

function disconnect() {
    if (! confirm('Disconnect Google from this workspace?')) {
        return;
    }
    router.delete(route('google.destroy'));
}
</script>

<template>
    <Head :title="integration.name" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ integration.name }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ integration.description }}</p>
                    </div>
                    <Link
                        :href="route('integrations')"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                        All integrations
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
            <p
                v-if="oauthNotice"
                class="rounded-lg px-4 py-3 text-sm"
                :class="oauthNotice.type === 'success'
                    ? 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                    : 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200'"
            >
                {{ oauthNotice.message }}
            </p>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Connection</h3>
                <p v-if="isConnected" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Connected{{ googleEmail ? ` as ${googleEmail}` : '' }}.
                </p>
                <p v-else class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Connect a Google account to sync inventory to a shared spreadsheet.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        v-if="!isConnected"
                        :href="route('google.connect')"
                        class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Connect Google
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        @click="disconnect"
                    >
                        Disconnect
                    </button>
                </div>
            </section>

            <section
                v-if="isConnected"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Inventory sheet</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    One designated Google Sheet per workspace. Status, Condition, Make, and Variant columns use dropdowns.
                </p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div v-if="sheetSettings.spreadsheet_url" class="flex flex-wrap gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">Sheet</dt>
                        <dd>
                            <a
                                :href="sheetSettings.spreadsheet_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Open in Google Sheets
                            </a>
                        </dd>
                    </div>
                    <div v-if="sheetSettings.last_pushed_at" class="flex flex-wrap gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">Last push</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ sheetSettings.last_pushed_at }}</dd>
                    </div>
                    <div v-if="sheetSettings.last_pulled_at" class="flex flex-wrap gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">Last import</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ sheetSettings.last_pulled_at }}</dd>
                    </div>
                </dl>

                <p v-if="actionMessage" class="mt-4 text-sm text-green-700 dark:text-green-300">{{ actionMessage }}</p>
                <p v-if="actionError" class="mt-4 text-sm text-red-700 dark:text-red-300">{{ actionError }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="pushing"
                        @click="pushSheet"
                    >
                        {{ pushing ? 'Syncing…' : 'Sync to Google Sheet' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                        :disabled="pulling"
                        @click="pullSheet"
                    >
                        {{ pulling ? 'Importing…' : 'Import from Google Sheet' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                        :disabled="recreating"
                        @click="recreateSheet"
                    >
                        {{ recreating ? 'Recreating…' : 'Recreate sheet' }}
                    </button>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
