<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import axios from 'axios';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    breadcrumbs: { type: Object, default: () => ({}) },
    integration: { type: Object, required: true },
    isConnected: { type: Boolean, default: false },
    easypostSettings: { type: Object, default: () => ({}) },
    currentIntegration: { type: Object, default: null },
});

const testing = ref(false);
const actionMessage = ref('');
const actionError = ref('');
const replacingKey = ref(false);

const form = useForm({
    api_key: '',
    test_mode: props.easypostSettings?.test_mode ?? true,
});

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

const isEnabled = computed(() => props.currentIntegration?.active ?? false);
const hasApiKey = computed(() => props.easypostSettings?.has_api_key ?? false);

onMounted(() => {
    const flash = usePage().props.flash ?? {};
    if (flash.success) actionMessage.value = flash.success;
    if (flash.error) actionError.value = flash.error;
});

function saveSettings() {
    actionError.value = '';
    actionMessage.value = '';
    form.post(route('easypost.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.api_key = '';
            replacingKey.value = false;
            actionMessage.value = 'EasyPost settings saved.';
            router.reload({ only: ['isConnected', 'easypostSettings', 'currentIntegration'] });
        },
        onError: () => {
            actionError.value = 'Could not save EasyPost settings.';
        },
    });
}

function disableIntegration() {
    if (!confirm('Disable EasyPost for this workspace? Shipments navigation will be hidden.')) return;
    router.delete(route('easypost.destroy'));
}

function enableIntegration() {
    router.patch(route('easypost.enable'), {}, { preserveScroll: true });
}

async function testConnection() {
    testing.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('easypost.test-connection'));
        actionMessage.value = data.message ?? 'Connected to EasyPost.';
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Connection test failed.';
    } finally {
        testing.value = false;
    }
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
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Shipping &amp; label integration</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="isConnected"
                            :href="route('shipments.index')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        >
                            <span class="material-icons text-base">local_shipping</span>
                            View shipments
                        </Link>
                        <Link
                            :href="route('integrations')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        >
                            <span class="material-icons text-base">arrow_back</span>
                            All integrations
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-3xl space-y-5 px-4 py-6">

            <div
                v-if="actionMessage"
                class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-200"
            >
                <span class="material-icons mt-0.5 shrink-0 text-lg text-green-600 dark:text-green-400">check_circle</span>
                {{ actionMessage }}
            </div>
            <div
                v-if="actionError"
                class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200"
            >
                <span class="material-icons mt-0.5 shrink-0 text-lg text-red-600 dark:text-red-400">error</span>
                {{ actionError }}
            </div>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-4 border-b border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-700">
                        <span class="material-icons text-2xl text-gray-700 dark:text-gray-200">local_shipping</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">EasyPost</h3>
                            <span
                                v-if="hasApiKey && isEnabled"
                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-md font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                                Connected &amp; enabled
                            </span>
                            <span
                                v-else-if="hasApiKey"
                                class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-0.5 text-md font-semibold text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-yellow-500" />
                                Key saved, disabled
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-md font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Not connected
                            </span>
                        </div>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Multi-carrier shipping API</p>
                    </div>
                    <a
                        href="https://www.easypost.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-md font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        easypost.com
                        <span class="material-icons text-base">open_in_new</span>
                    </a>
                </div>

                <div class="px-6 py-5">
                    <p class="text-md leading-relaxed text-gray-700 dark:text-gray-300">
                        EasyPost is a shipping API that connects to 100+ carriers including UPS, FedEx, USPS, DHL, and more through a single integration.
                        Once connected, you can compare live shipping rates, purchase labels directly, and track packages, all without a carrier account per carrier.
                    </p>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-2xl text-primary-600 dark:text-primary-400">compare_arrows</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Rate shopping</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Compare live rates across carriers before committing to a label.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-2xl text-primary-600 dark:text-primary-400">label</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Label purchase</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Buy and print shipping labels without leaving this workspace.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-2xl text-primary-600 dark:text-primary-400">track_changes</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Tracking</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Unified tracking across all carriers from a single dashboard.</p>
                        </div>
                    </div>

                    <div
                        v-if="!hasApiKey"
                        class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary-100 bg-primary-50 px-4 py-3.5 dark:border-primary-900/50 dark:bg-primary-900/20"
                    >
                        <div>
                            <p class="text-md font-medium text-primary-900 dark:text-primary-200">Don't have an EasyPost account yet?</p>
                            <p class="mt-0.5 text-md text-primary-700 dark:text-primary-300">Sign up free. No contract required. Grab your API key from the EasyPost dashboard and paste it below.</p>
                        </div>
                        <a
                            href="https://www.easypost.com/signup"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                        >
                            Create an account
                            <span class="material-icons text-base">open_in_new</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-white">API key</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">
                        Find your key under <strong class="font-medium text-gray-700 dark:text-gray-300">API Keys</strong> in your
                        <a href="https://www.easypost.com/account/api-keys" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline dark:text-primary-400">EasyPost dashboard</a>.
                        Use a test key while setting up, switch to production when ready.
                        Ship-from addresses are resolved per shipment via subsidiary and location.
                    </p>
                </div>

                <form class="space-y-5 px-6 py-5" autocomplete="off" @submit.prevent="saveSettings">
                    <div>
                        <label class="mb-1.5 block text-md font-medium text-gray-700 dark:text-gray-300">EasyPost API key</label>
                        <template v-if="hasApiKey && !replacingKey">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-900/40">
                                    <span class="material-icons text-base text-gray-400">lock</span>
                                    <span class="font-mono text-md text-gray-500 dark:text-gray-400">••••••••••••••••</span>
                                </div>
                                <button
                                    type="button"
                                    class="text-md font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    @click="replacingKey = true"
                                >
                                    Replace key
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <input
                                v-model="form.api_key"
                                type="password"
                                :required="!hasApiKey"
                                placeholder="EZAK..."
                                class="input-style w-full"
                                autocomplete="new-password"
                                data-lpignore="true"
                                data-1p-ignore
                            />
                            <button
                                v-if="hasApiKey && replacingKey"
                                type="button"
                                class="mt-1.5 text-md text-gray-500 hover:underline dark:text-gray-400"
                                @click="replacingKey = false; form.api_key = ''"
                            >
                                Cancel
                            </button>
                        </template>
                        <p v-if="form.errors.api_key" class="mt-1.5 text-md text-red-600 dark:text-red-400">{{ form.errors.api_key }}</p>
                    </div>

                    <label class="flex cursor-pointer items-start gap-2.5 text-md text-gray-700 dark:text-gray-300">
                        <input
                            v-model="form.test_mode"
                            type="checkbox"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span>
                            Test mode
                            <span class="block text-md font-normal text-gray-500 dark:text-gray-400">Use your EasyPost test API key. Labels are free but not real.</span>
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Save settings
                    </button>
                </form>
            </section>

            <section
                v-if="hasApiKey"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-white">Status</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">
                        <template v-if="isEnabled">
                            EasyPost is enabled. Shipments appear in navigation for users with access.
                        </template>
                        <template v-else>
                            EasyPost is disabled. Your API key is saved but shipping features are hidden from navigation.
                        </template>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 px-6 py-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="testing"
                        @click="testConnection"
                    >
                        <span class="material-icons text-base">wifi_tethering</span>
                        {{ testing ? 'Testing…' : 'Test connection' }}
                    </button>
                    <button
                        v-if="isEnabled"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-md font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/30"
                        @click="disableIntegration"
                    >
                        <span class="material-icons text-base">block</span>
                        Disable
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-md font-medium text-white hover:bg-primary-700"
                        @click="enableIntegration"
                    >
                        <span class="material-icons text-base">check_circle</span>
                        Enable
                    </button>
                </div>
            </section>

        </div>
    </TenantLayout>
</template>
