<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import axios from 'axios';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    breadcrumbs: { type: Object, default: () => ({}) },
    integration: { type: Object, required: true },
    isConnected: { type: Boolean, default: false },
    wordpressSettings: { type: Object, default: () => ({}) },
    tenantDomain: { type: String, default: null },
    helmfulApiKey: { type: String, default: null },
    pluginDownloadUrl: { type: String, default: null },
    pluginVersion: { type: String, default: '1.0.0' },
});

const testing = ref(false);
const pushing = ref(false);
const actionMessage = ref('');
const actionError = ref('');
const revealedKey = ref(props.helmfulApiKey ?? '');

const hasHelmfulApiKey = computed(() => Boolean(props.wordpressSettings?.has_helmful_api_key));

const replacingWpKey = ref(false);

const integrationKeyButtonLabel = computed(() => (
    hasHelmfulApiKey.value ? 'Replace current key' : 'Generate integration key'
));

watch(() => props.helmfulApiKey, (key) => {
    if (key) {
        revealedKey.value = key;
    }
});

onMounted(() => {
    const flash = usePage().props.flash ?? {};
    if (flash.success) {
        actionMessage.value = flash.success;
    }
    if (flash.error) {
        actionError.value = flash.error;
    }
});

const form = useForm({
    wordpress_url: props.wordpressSettings.wordpress_url ?? '',
    wordpress_api_key: '',
    auto_push_enabled: props.wordpressSettings.auto_push_enabled ?? true,
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

function saveSettings() {
    actionError.value = '';
    actionMessage.value = '';
    form.post(route('wordpress.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.wordpress_api_key = '';
            replacingWpKey.value = false;
            actionMessage.value = 'WordPress settings saved.';
            router.reload({ only: ['isConnected', 'wordpressSettings', 'helmfulApiKey'] });
        },
        onError: () => {
            actionError.value = 'Could not save WordPress settings.';
        },
    });
}

function regenerateKey() {
    const isRegenerating = hasHelmfulApiKey.value;
    const message = isRegenerating
        ? 'Replace the integration key? Update WordPress with the new key afterward.'
        : 'Generate an integration key for WordPress?';

    if (! confirm(message)) {
        return;
    }

    router.post(route('wordpress.regenerate-key'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['helmfulApiKey', 'wordpressSettings'],
                onSuccess: (page) => {
                    if (page.props.helmfulApiKey) {
                        revealedKey.value = page.props.helmfulApiKey;
                    }
                    actionMessage.value = isRegenerating
                        ? 'Integration key replaced. Copy it into WordPress.'
                        : 'Integration key generated. Copy it into WordPress.';
                },
            });
        },
        onError: () => {
            actionError.value = 'Could not generate integration key.';
        },
    });
}

async function testConnection() {
    testing.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('wordpress.test-connection'));
        actionMessage.value = data.message ?? 'Connected to WordPress.';
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Connection test failed.';
    } finally {
        testing.value = false;
    }
}

async function pushAll() {
    if (! confirm('Push all boat shows and events to WordPress?')) {
        return;
    }
    pushing.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.post(route('wordpress.push-all'));
        actionMessage.value = data.message
            ?? `Pushed ${data.shows_synced ?? 0} shows and ${data.events_synced ?? 0} events.`;
        if ((data.errors ?? []).length) {
            actionError.value = data.errors.join(' ');
        }
        router.reload({ only: ['wordpressSettings', 'isConnected'] });
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'Push to WordPress failed.';
    } finally {
        pushing.value = false;
    }
}

function disconnect() {
    if (! confirm('Disconnect WordPress from this workspace?')) {
        return;
    }
    router.delete(route('wordpress.destroy'));
}

function copyText(text) {
    if (! text) {
        return;
    }
    navigator.clipboard?.writeText(text);
    actionMessage.value = 'Copied to clipboard.';
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
            <div
                v-if="actionMessage"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ actionMessage }}
            </div>
            <div
                v-if="actionError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ actionError }}
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">WordPress plugin</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Download and install the Helmful Sync plugin on your WordPress site, then activate it under Plugins.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a
                        v-if="pluginDownloadUrl"
                        :href="pluginDownloadUrl"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <span class="material-icons text-base" aria-hidden="true">download</span>
                        Download helmful-sync.zip
                    </a>
                    <span v-if="pluginVersion" class="text-sm text-gray-500 dark:text-gray-400">
                        Version {{ pluginVersion }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip file, and activate
                    <strong>Helmful Sync</strong>. Then configure it under Settings → Helmful Sync.
                </p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    After saving settings here, use <strong>Push all to WordPress</strong> below or
                    <strong>Pull from Helmful</strong> in WordPress admin to sync boat shows.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Helmful credentials</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Paste these into WordPress under Settings → Helmful Sync. Save the Helmful integration key in WordPress before testing the connection.
                </p>

                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Tenant domain</dt>
                        <dd class="mt-1 flex items-center gap-2">
                            <code class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-900">{{ tenantDomain || '—' }}</code>
                            <button
                                v-if="tenantDomain"
                                type="button"
                                class="text-primary-600 hover:underline"
                                @click="copyText(tenantDomain)"
                            >
                                Copy
                            </button>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Helmful integration key</dt>
                        <dd class="mt-1 space-y-2">
                            <p v-if="revealedKey" class="break-all rounded bg-amber-50 px-3 py-2 font-mono text-xs text-amber-900 dark:bg-amber-900/20 dark:text-amber-100">
                                {{ revealedKey }}
                            </p>
                            <p v-else-if="!hasHelmfulApiKey" class="text-gray-500 dark:text-gray-400">
                                Generate an integration key, then paste it into WordPress under Settings → Helmful Sync.
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    :class="hasHelmfulApiKey && !revealedKey
                                        ? 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200'
                                        : 'rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700'"
                                    @click="regenerateKey"
                                >
                                    {{ integrationKeyButtonLabel }}
                                </button>
                                <button
                                    v-if="revealedKey"
                                    type="button"
                                    class="text-sm text-primary-600 hover:underline"
                                    @click="copyText(revealedKey)"
                                >
                                    Copy key
                                </button>
                            </div>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">WordPress site</h3>
                <form class="mt-4 space-y-4" @submit.prevent="saveSettings">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">WordPress site URL</label>
                        <input
                            v-model="form.wordpress_url"
                            type="url"
                            required
                            placeholder="https://example.com"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                        />
                        <p v-if="form.errors.wordpress_url" class="mt-1 text-sm text-red-600">{{ form.errors.wordpress_url }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">WordPress API key</label>
                        <template v-if="isConnected && !replacingWpKey">
                            <button
                                type="button"
                                class="mt-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                                @click="replacingWpKey = true"
                            >
                                Replace current key
                            </button>
                        </template>
                        <template v-else>
                            <input
                                v-model="form.wordpress_api_key"
                                type="password"
                                :required="!isConnected || replacingWpKey"
                                :placeholder="isConnected ? 'Paste new WordPress API key' : 'From WordPress plugin settings'"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                            />
                            <button
                                v-if="isConnected && replacingWpKey"
                                type="button"
                                class="mt-2 text-sm text-gray-500 hover:underline dark:text-gray-400"
                                @click="replacingWpKey = false; form.wordpress_api_key = ''"
                            >
                                Cancel
                            </button>
                        </template>
                        <p v-if="form.errors.wordpress_api_key" class="mt-1 text-sm text-red-600">{{ form.errors.wordpress_api_key }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="form.auto_push_enabled" type="checkbox" class="rounded border-gray-300" />
                        Auto-push boat shows and events when saved in Helmful
                    </label>
                    <button
                        type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Save settings
                    </button>
                </form>
            </section>

            <section
                v-if="isConnected"
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Sync</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Push all boat shows and events from Helmful to WordPress, or pull from Helmful in the WordPress plugin.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="pushing"
                        @click="pushAll"
                    >
                        {{ pushing ? 'Pushing…' : 'Push all to WordPress' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        :disabled="testing"
                        @click="testConnection"
                    >
                        {{ testing ? 'Testing…' : 'Test connection' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300"
                        @click="disconnect"
                    >
                        Disconnect
                    </button>
                </div>
                <p v-if="wordpressSettings.last_pushed_at" class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Last pushed: {{ wordpressSettings.last_pushed_at }}
                </p>
            </section>
        </div>
    </TenantLayout>
</template>
