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
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Google Sheets inventory and model sync</p>
                    </div>
                    <Link
                        :href="route('integrations')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                        <span class="material-icons text-[15px]">arrow_back</span>
                        All integrations
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-3xl space-y-5 px-4 py-6">

            <!-- OAuth notice -->
            <div
                v-if="oauthNotice"
                class="flex items-start gap-3 rounded-lg border px-4 py-3 text-md"
                :class="oauthNotice.type === 'success'
                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-200'
                    : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'"
            >
                <span
                    class="material-icons mt-0.5 shrink-0 text-[16px]"
                    :class="oauthNotice.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                >{{ oauthNotice.type === 'success' ? 'check_circle' : 'error' }}</span>
                {{ oauthNotice.message }}
            </div>

            <!-- Action feedback -->
            <div v-if="actionMessage" class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-200">
                <span class="material-icons mt-0.5 shrink-0 text-[16px] text-green-600 dark:text-green-400">check_circle</span>
                {{ actionMessage }}
            </div>
            <div v-if="actionError" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                <span class="material-icons mt-0.5 shrink-0 text-[16px] text-red-600 dark:text-red-400">error</span>
                {{ actionError }}
            </div>

            <!-- ── What is Google Sheets ───────────────────────────── -->
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">

                <!-- Header strip -->
                <div class="flex items-center gap-4 border-b border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-700">
                        <span class="material-icons text-[22px] text-green-600 dark:text-green-400">table_chart</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Google Sheets</h3>
                            <span
                                v-if="isConnected"
                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-md font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                                Connected{{ googleEmail ? ` as ${googleEmail}` : '' }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-md font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Not connected
                            </span>
                        </div>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Spreadsheet sync via Google Workspace</p>
                    </div>
                    <a
                        href="https://sheets.google.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-md font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        sheets.google.com
                        <span class="material-icons text-[13px]">open_in_new</span>
                    </a>
                </div>

                <!-- Body -->
                <div class="px-6 py-5">
                    <p class="text-md leading-relaxed text-gray-700 dark:text-gray-300">
                        Connect a Google account to sync Helmful inventory and model data with Google Sheets. Helmful creates and manages two dedicated spreadsheets: one for unit-level inventory and one for your make and model catalog. You can push data out for editing in Sheets and import changes back into Helmful.
                    </p>

                    <!-- Benefits -->
                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">inventory_2</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Inventory sheet</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Sync unit-level inventory including make, model, status, cost, price, HID, and serial number.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">directions_boat</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Models sheet</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Sync your make and model catalog with hull type, specs, and variants, one row per variant.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">upload_file</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Two-way sync</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Push from Helmful to Sheets for bulk editing, then import changes back when ready.</p>
                        </div>
                    </div>

                    <!-- Server config warning -->
                    <div
                        v-if="!isConnected && !canConnect"
                        class="mt-5 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3.5 dark:border-amber-900 dark:bg-amber-900/20"
                    >
                        <span class="material-icons mt-0.5 shrink-0 text-[16px] text-amber-600 dark:text-amber-400">warning</span>
                        <p class="text-md text-amber-800 dark:text-amber-300">
                            Google OAuth is not fully configured on this server. Check that <code class="rounded bg-amber-100 px-1 font-mono dark:bg-amber-900/40">GOOGLE_CLIENT_ID</code>, <code class="rounded bg-amber-100 px-1 font-mono dark:bg-amber-900/40">GOOGLE_CLIENT_SECRET</code>, and <code class="rounded bg-amber-100 px-1 font-mono dark:bg-amber-900/40">GOOGLE_REDIRECT_URI</code> are set.
                        </p>
                    </div>

                    <!-- Connect CTA (not connected, server ready) -->
                    <div v-if="!isConnected && canConnect" class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <p class="text-md text-gray-600 dark:text-gray-300">
                            Connect any Google account for this workspace. The Google account does not need to match your Helmful login email.
                        </p>
                        <a
                            :href="route('google.connect')"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-md font-semibold text-white hover:bg-primary-700"
                        >
                            <span class="material-icons text-[18px]">link</span>
                            Connect Google
                        </a>
                    </div>
                </div>
            </section>

            <!-- ── Inventory sheet (connected) ────────────────────── -->
            <section
                v-if="isConnected"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Helmful Inventory</h3>
                            <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Unit-level inventory: make, model, variant, status, condition, HID, serial number, unit year, cost, price, location, and subsidiary.</p>
                        </div>
                        <a
                            v-if="sheetSettings.spreadsheet_url"
                            :href="sheetSettings.spreadsheet_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-md font-medium text-primary-600 hover:bg-gray-50 dark:border-gray-600 dark:text-primary-400 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-[15px]">open_in_new</span>
                            Open sheet
                        </a>
                    </div>
                </div>
                <div class="px-6 py-4">
                    <p class="text-md text-gray-600 dark:text-gray-300">
                        Import matches rows to units by <strong class="font-semibold text-gray-900 dark:text-white">HID</strong> first, then <strong class="font-semibold text-gray-900 dark:text-white">Serial ID</strong>.
                    </p>

                    <!-- Last sync metadata -->
                    <dl v-if="sheetSettings.last_pushed_at || sheetSettings.last_pulled_at" class="mt-3 flex flex-wrap gap-x-6 gap-y-1">
                        <div v-if="sheetSettings.last_pushed_at" class="flex items-center gap-2">
                            <dt class="text-md text-gray-500 dark:text-gray-400">Last push</dt>
                            <dd class="text-md text-gray-900 dark:text-white">{{ sheetSettings.last_pushed_at }}</dd>
                        </div>
                        <div v-if="sheetSettings.last_pulled_at" class="flex items-center gap-2">
                            <dt class="text-md text-gray-500 dark:text-gray-400">Last import</dt>
                            <dd class="text-md text-gray-900 dark:text-white">{{ sheetSettings.last_pulled_at }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="pushingInventory"
                            @click="requestPushSheet('inventory')"
                        >
                            <span v-if="pushingInventory" class="material-icons animate-spin text-[16px]">sync</span>
                            {{ pushingInventory ? 'Syncing...' : 'Sync inventory' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="pullingInventory"
                            @click="pullInventorySheet"
                        >
                            <span v-if="pullingInventory" class="material-icons animate-spin text-[16px]">sync</span>
                            {{ pullingInventory ? 'Importing...' : 'Import inventory' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="recreatingInventory"
                            @click="recreateInventorySheet"
                        >
                            <span v-if="recreatingInventory" class="material-icons animate-spin text-[16px]">sync</span>
                            {{ recreatingInventory ? 'Recreating...' : 'Recreate sheet' }}
                        </button>
                    </div>
                    <p class="mt-3 text-md text-gray-500 dark:text-gray-400">
                        You can also sync or import inventory from the Asset Units page gear menu.
                    </p>
                </div>
            </section>

            <!-- ── Models sheet (connected) ───────────────────────── -->
            <section
                v-if="isConnected"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Helmful Models</h3>
                            <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Make, model, and variant catalog with hull type, hull material, boat type, length, width, and all visible asset specs.</p>
                        </div>
                        <a
                            v-if="sheetSettings.models_spreadsheet_url"
                            :href="sheetSettings.models_spreadsheet_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-md font-medium text-primary-600 hover:bg-gray-50 dark:border-gray-600 dark:text-primary-400 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-[15px]">open_in_new</span>
                            Open sheet
                        </a>
                    </div>
                </div>
                <div class="px-6 py-4">
                    <p class="text-md text-gray-600 dark:text-gray-300">
                        One row per variant (or per model when no variants exist). Import matches rows by <strong class="font-semibold text-gray-900 dark:text-white">Make + Model + Variant</strong> and updates model-level attributes and specs in Helmful.
                    </p>

                    <dl v-if="sheetSettings.last_models_pushed_at || sheetSettings.last_models_pulled_at" class="mt-3 flex flex-wrap gap-x-6 gap-y-1">
                        <div v-if="sheetSettings.last_models_pushed_at" class="flex items-center gap-2">
                            <dt class="text-md text-gray-500 dark:text-gray-400">Last push</dt>
                            <dd class="text-md text-gray-900 dark:text-white">{{ sheetSettings.last_models_pushed_at }}</dd>
                        </div>
                        <div v-if="sheetSettings.last_models_pulled_at" class="flex items-center gap-2">
                            <dt class="text-md text-gray-500 dark:text-gray-400">Last import</dt>
                            <dd class="text-md text-gray-900 dark:text-white">{{ sheetSettings.last_models_pulled_at }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="pushingModels"
                            @click="requestPushSheet('models')"
                        >
                            <span v-if="pushingModels" class="material-icons animate-spin text-[16px]">sync</span>
                            {{ pushingModels ? 'Syncing...' : 'Sync models' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="pullingModels"
                            @click="pullModelsSheet"
                        >
                            <span v-if="pullingModels" class="material-icons animate-spin text-[16px]">sync</span>
                            {{ pullingModels ? 'Importing...' : 'Import models' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="recreatingModels"
                            @click="recreateModelsSheet"
                        >
                            <span v-if="recreatingModels" class="material-icons animate-spin text-[16px]">sync</span>
                            {{ recreatingModels ? 'Recreating...' : 'Recreate sheet' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- ── Disconnect ─────────────────────────────────────── -->
            <section
                v-if="isConnected"
                class="rounded-xl border border-red-100 bg-white shadow-sm dark:border-red-900/40 dark:bg-gray-800"
            >
                <div class="border-b border-red-100 px-6 py-4 dark:border-red-900/40">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Disconnect</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Removing the Google connection stops all sheet syncing. Your inventory and model data in Helmful is not affected.</p>
                </div>
                <div class="px-6 py-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-md font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/30"
                        @click="disconnect"
                    >
                        <span class="material-icons text-[15px]">link_off</span>
                        Disconnect Google
                    </button>
                </div>
            </section>

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