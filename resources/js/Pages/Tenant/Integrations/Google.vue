<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import GoogleSheetPushConfirmModal from '@/Components/Tenant/GoogleSheetPushConfirmModal.vue';
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
    canConnect: { type: Boolean, default: true },
});

const pushingInventory = ref(false);
const pullingInventory = ref(false);
const recreatingInventory = ref(false);
const pushingModels = ref(false);
const pullingModels = ref(false);
const recreatingModels = ref(false);
const pushConfirmTarget = ref(null);
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

const inventoryPushConfirm = {
    title: 'Sync inventory to Google Sheet?',
    description: 'The linked Helmful Inventory sheet will be overwritten with your current units from Helmful. Any spreadsheet edits that have not been imported back will be lost.',
};

const modelsPushConfirm = {
    title: 'Sync models to Google Sheet?',
    description: 'The linked Helmful Models sheet will be overwritten with your current makes, models, variants, and specs from Helmful. Any spreadsheet edits that have not been imported back will be lost.',
};

const activePushConfirm = computed(() => (
    pushConfirmTarget.value === 'models' ? modelsPushConfirm : inventoryPushConfirm
));

function requestPushSheet(target) {
    pushConfirmTarget.value = target;
}

function closePushConfirm() {
    pushConfirmTarget.value = null;
}

async function confirmPushSheet() {
    const target = pushConfirmTarget.value;
    closePushConfirm();
    if (target === 'models') {
        await pushModelsSheet();
    } else {
        await pushInventorySheet();
    }
}

async function pushInventorySheet() {
    pushingInventory.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.push'));
        actionMessage.value = data.message
            ?? `Synced ${data.row_count ?? 0} units to Google Sheets.`;
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Inventory push failed.';
    } finally {
        pushingInventory.value = false;
    }
}

async function pullInventorySheet() {
    pullingInventory.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.pull'));
        actionMessage.value = data.message
            ?? `Updated ${data.updated ?? 0} units from Google Sheets.`;
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Inventory import failed.';
    } finally {
        pullingInventory.value = false;
    }
}

async function recreateInventorySheet() {
    recreatingInventory.value = true;
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
        actionError.value = e.response?.data?.message ?? 'Inventory recreate failed.';
    } finally {
        recreatingInventory.value = false;
    }
}

async function pushModelsSheet() {
    pushingModels.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.models.push'));
        actionMessage.value = data.message
            ?? `Synced ${data.row_count ?? 0} models to Google Sheets.`;
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Models push failed.';
    } finally {
        pushingModels.value = false;
    }
}

async function pullModelsSheet() {
    pullingModels.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.models.pull'));
        actionMessage.value = data.message
            ?? `Updated ${data.updated ?? 0} models from Google Sheets.`;
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Models import failed.';
    } finally {
        pullingModels.value = false;
    }
}

async function recreateModelsSheet() {
    recreatingModels.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('google.sheet.models.recreate'));
        actionMessage.value = 'Models sheet recreated.';
        if (data.spreadsheet_url) {
            actionMessage.value += ` ${data.spreadsheet_url}`;
        }
        router.reload({ only: ['sheetSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Models recreate failed.';
    } finally {
        recreatingModels.value = false;
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
                    Connect any Google account for this workspace. The Google account does not need to match your Helmful login email.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        v-if="!isConnected && canConnect"
                        :href="route('google.connect')"
                        class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Connect Google
                    </a>
                    <p
                        v-else-if="!isConnected && !canConnect"
                        class="text-sm text-amber-700 dark:text-amber-300"
                    >
                        Google OAuth is not fully configured on the server (check GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI).
                    </p>
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

            <template v-if="isConnected">
                <p v-if="actionMessage" class="text-sm text-green-700 dark:text-green-300">{{ actionMessage }}</p>
                <p v-if="actionError" class="text-sm text-red-700 dark:text-red-300">{{ actionError }}</p>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Helmful Inventory</h3>

                    <div class="mt-3 space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        <p>
                            Unit-level inventory management: make, model, variant, status, condition, HID, serial number,
                            unit year, cost, asking price, location, and subsidiary.
                        </p>
                        <p>
                            Import matches rows to units by
                            <strong class="font-medium text-gray-900 dark:text-white">HID</strong>
                            first, then
                            <strong class="font-medium text-gray-900 dark:text-white">Serial ID</strong>.
                        </p>
                    </div>

                    <dl class="mt-5 space-y-2 border-t border-gray-100 pt-5 text-sm dark:border-gray-700/80">
                        <div v-if="sheetSettings.spreadsheet_url" class="flex flex-wrap gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Sheet</dt>
                            <dd>
                                <a
                                    :href="sheetSettings.spreadsheet_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    Open Helmful Inventory
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

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="pushingInventory"
                            @click="requestPushSheet('inventory')"
                        >
                            {{ pushingInventory ? 'Syncing…' : 'Sync inventory' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                            :disabled="pullingInventory"
                            @click="pullInventorySheet"
                        >
                            {{ pullingInventory ? 'Importing…' : 'Import inventory' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                            :disabled="recreatingInventory"
                            @click="recreateInventorySheet"
                        >
                            {{ recreatingInventory ? 'Recreating…' : 'Recreate inventory sheet' }}
                        </button>
                    </div>

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        You can also sync or import inventory from the Asset Units page gear menu.
                    </p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Helmful Models</h3>

                    <div class="mt-3 space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        <p>
                            Make, model, and variant catalog with hull type, hull material, boat type, length, width,
                            and all visible asset specs. One row per variant (or per model when no variants exist).
                        </p>
                        <p>
                            Import matches rows by
                            <strong class="font-medium text-gray-900 dark:text-white">Make + Model + Variant</strong>
                            and updates model-level attributes and specs in Helmful.
                        </p>
                    </div>

                    <dl class="mt-5 space-y-2 border-t border-gray-100 pt-5 text-sm dark:border-gray-700/80">
                        <div v-if="sheetSettings.models_spreadsheet_url" class="flex flex-wrap gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Sheet</dt>
                            <dd>
                                <a
                                    :href="sheetSettings.models_spreadsheet_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    Open Helmful Models
                                </a>
                            </dd>
                        </div>
                        <div v-if="sheetSettings.last_models_pushed_at" class="flex flex-wrap gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Last push</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ sheetSettings.last_models_pushed_at }}</dd>
                        </div>
                        <div v-if="sheetSettings.last_models_pulled_at" class="flex flex-wrap gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Last import</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ sheetSettings.last_models_pulled_at }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="pushingModels"
                            @click="requestPushSheet('models')"
                        >
                            {{ pushingModels ? 'Syncing…' : 'Sync models' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                            :disabled="pullingModels"
                            @click="pullModelsSheet"
                        >
                            {{ pullingModels ? 'Importing…' : 'Import models' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                            :disabled="recreatingModels"
                            @click="recreateModelsSheet"
                        >
                            {{ recreatingModels ? 'Recreating…' : 'Recreate models sheet' }}
                        </button>
                    </div>
                </section>
            </template>
        </div>

        <GoogleSheetPushConfirmModal
            :show="pushConfirmTarget !== null"
            :title="activePushConfirm.title"
            :description="activePushConfirm.description"
            @close="closePushConfirm"
            @confirm="confirmPushSheet"
        />
    </TenantLayout>
</template>
