<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    breadcrumbs: { type: Object, default: () => ({}) },
    integration: { type: Object, required: true },
    hasMailchimpToken: { type: Boolean, default: false },
    currentIntegration: { type: Object, default: null },
    oauthNotice: { type: Object, default: null },
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
        if (link?.url && link?.name) items.push({ label: link.name, href: link.url });
    }
    if (props.breadcrumbs?.current) items.push({ label: props.breadcrumbs.current });
    return items;
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => {
    const fromFlash = page.props.flash?.error;
    if (fromFlash) return fromFlash;
    const errs = page.props.errors;
    if (!errs || typeof errs !== 'object') return null;
    const flat = Object.values(errs).flat().filter(Boolean);
    return flat.length ? flat.join(' ') : null;
});

function disconnect() {
    if (!confirm('Remove the Mailchimp connection for this workspace?')) return;
    router.delete(route('mailchimp.destroy'));
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
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Email marketing integration</p>
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

            <!-- Flash / OAuth notices -->
            <div
                v-if="oauthNotice?.type === 'success' || flashSuccess"
                class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-200"
            >
                <span class="material-icons mt-0.5 shrink-0 text-[16px] text-green-600 dark:text-green-400">check_circle</span>
                {{ oauthNotice?.type === 'success' ? oauthNotice.message : flashSuccess }}
            </div>
            <div
                v-if="oauthNotice?.type === 'error' || flashError"
                class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200"
            >
                <span class="material-icons mt-0.5 shrink-0 text-[16px] text-red-600 dark:text-red-400">error</span>
                {{ oauthNotice?.type === 'error' ? oauthNotice.message : flashError }}
            </div>

            <!-- ── What is Mailchimp ───────────────────────────────── -->
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">

                <!-- Header strip -->
                <div class="flex items-center gap-4 border-b border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-700">
                        <span class="material-icons text-[22px] text-yellow-500 dark:text-yellow-400">campaign</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Mailchimp</h3>
                            <span
                                v-if="hasMailchimpToken"
                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-md font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                                Connected
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-md font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Not connected
                            </span>
                        </div>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Email marketing and audience management</p>
                    </div>
                    <a
                        href="https://mailchimp.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-md font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        mailchimp.com
                        <span class="material-icons text-[13px]">open_in_new</span>
                    </a>
                </div>

                <!-- Body -->
                <div class="px-6 py-5">
                    <p class="text-md leading-relaxed text-gray-700 dark:text-gray-300">
                        Mailchimp is an email marketing platform used by millions of businesses to manage contact lists, build campaigns, and track engagement. Connecting it to Helmful lets you keep your Mailchimp audiences in sync with your contacts and leads so your marketing lists are always up to date.
                    </p>

                    <!-- Benefits -->
                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">sync_alt</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Audience sync</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Keep Mailchimp audiences in sync with contacts and leads in Helmful.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">upload</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Export contacts</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Push contacts from Helmful into your Mailchimp lists for targeted campaigns.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">download</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Import audiences</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Pull Mailchimp subscribers into Helmful as contacts or leads.</p>
                        </div>
                    </div>

                    <!-- Sign-up callout (not connected) -->
                    <div
                        v-if="!hasMailchimpToken"
                        class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary-100 bg-primary-50 px-4 py-3.5 dark:border-primary-900/50 dark:bg-primary-900/20"
                    >
                        <div>
                            <p class="text-md font-medium text-primary-900 dark:text-primary-200">Don't have a Mailchimp account yet?</p>
                            <p class="mt-0.5 text-md text-primary-700 dark:text-primary-300">Sign up free and come back to connect once your account is ready.</p>
                        </div>
                        <a
                            href="https://login.mailchimp.com/signup/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                        >
                            Create an account
                            <span class="material-icons text-[15px]">open_in_new</span>
                        </a>
                    </div>

                    <!-- Connect CTA (not connected) -->
                    <div v-if="!hasMailchimpToken" class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <p class="text-md text-gray-600 dark:text-gray-300">
                            Connect your Mailchimp account to start syncing audiences. You'll be taken to Mailchimp to authorize access and returned here when done.
                        </p>
                        <a
                            :href="route('mailchimp.connect')"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-md font-semibold text-white hover:bg-primary-700"
                        >
                            <span class="material-icons text-[18px]">link</span>
                            Connect with Mailchimp
                        </a>
                    </div>

                    <!-- Connected state -->
                    <div v-if="hasMailchimpToken" class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <p class="text-md text-gray-700 dark:text-gray-300">
                            Mailchimp is connected to this workspace. Use the contacts list to import or export audiences between Helmful and Mailchimp.
                        </p>
                        <p v-if="currentIntegration?.last_synced_at" class="mt-2 text-md text-gray-500 dark:text-gray-400">
                            Last sync: {{ currentIntegration.last_synced_at }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('contacts.index')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                            >
                                <span class="material-icons text-[15px]">people</span>
                                Go to contacts
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Disconnect ─────────────────────────────────────── -->
            <section
                v-if="hasMailchimpToken"
                class="rounded-xl border border-red-100 bg-white shadow-sm dark:border-red-900/40 dark:bg-gray-800"
            >
                <div class="border-b border-red-100 px-6 py-4 dark:border-red-900/40">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Disconnect</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Removing the connection stops all audience syncing. Your contacts in Helmful are not affected.</p>
                </div>
                <div class="px-6 py-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-md font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/30"
                        @click="disconnect"
                    >
                        <span class="material-icons text-[15px]">link_off</span>
                        Disconnect Mailchimp
                    </button>
                </div>
            </section>

        </div>
    </TenantLayout>
</template>