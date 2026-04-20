<script setup>
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stripe: {
        type: Object,
        required: true,
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    can_connect_stripe: {
        type: Boolean,
        default: true,
    },
    can_use_stripe_payment_methods: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const breadcrumbItems = computed(() => [
    { label: 'Account', href: route('account.index') },
    { label: 'Payments', href: route('account.payments') },
    { label: 'Stripe' },
]);

function formatDate(value) {
    if (!value) return null;
    try {
        return new Date(value).toLocaleString();
    } catch (_e) {
        return value;
    }
}

const stripeConnectedAt = computed(() => formatDate(props.stripe?.connected_at));

const disconnectingStripe = ref(false);

function disconnectStripe() {
    if (!props.stripe?.account_id || disconnectingStripe.value) return;
    if (!confirm('Disconnect Stripe from this workspace? You can connect QuickBooks Online afterward, or connect Stripe again later.')) return;
    disconnectingStripe.value = true;
    router.post(route('stripe.disconnect'), {}, {
        preserveScroll: true,
        onFinish: () => {
            disconnectingStripe.value = false;
        },
    });
}

const capabilityLabel = (v) => {
    if (v == null || v === '') return '—';
    if (v === 'active') return 'Active';
    if (v === 'pending') return 'Pending';
    if (v === 'inactive') return 'Action needed';
    return String(v);
};

const statusMetricClass = computed(() => {
    if (props.stripe.ready) {
        return 'text-green-600 dark:text-green-400';
    }
    if (props.stripe.account_id && props.stripe.card_payments_capability === 'pending') {
        return 'text-amber-600 dark:text-amber-400';
    }
    if (props.stripe.account_id && props.stripe.details_submitted) {
        return 'text-amber-600 dark:text-amber-400';
    }
    if (props.stripe.account_id) {
        return 'text-amber-600 dark:text-amber-400';
    }
    return 'text-gray-600 dark:text-gray-300';
});

const activeMethodCount = computed(() => props.paymentMethods.filter(m => m.is_enabled).length);

const syncingStripe = ref(false);

function verifyStripeStatus() {
    if (!props.can_connect_stripe || !props.stripe.account_id || syncingStripe.value) {
        return;
    }
    syncingStripe.value = true;
    router.post(route('account.payments.sync-stripe'), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncingStripe.value = false;
        },
    });
}

function toggleMethod(code, isEnabled) {
    if (!props.can_use_stripe_payment_methods) {
        return;
    }
    router.patch(
        route('account.payments.methods'),
        { code, is_enabled: isEnabled },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Stripe payments" />

    <TenantLayout>
        <template #header>
            <div class="w-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">
                    Stripe Connect
                </h2>
                <p class="mt-2 text-md text-gray-600 dark:text-gray-400">
                    Who Stripe is, how Express Connect works for your dealership, and how to connect or disconnect.
                </p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                    <Link
                        :href="route('account.payments')"
                        class="inline-flex items-center gap-1 text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-lg">arrow_back</span>
                        All payments
                    </Link>
                    <Link
                        :href="route('account.payments.stripe-info')"
                        class="inline-flex items-center gap-1 text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-lg">help_outline</span>
                        About Stripe &amp; fees
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full space-y-6">
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

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    What this is
                </h3>
                <div class="mt-3 space-y-3 text-md leading-relaxed text-gray-600 dark:text-gray-300">
                    <p>
                        <strong class="text-gray-900 dark:text-gray-100">Stripe</strong> processes card and bank payments when your customers pay invoices online.
                        Maritime uses <strong class="text-gray-900 dark:text-gray-100">Stripe Connect (Express)</strong>: the connected account is yours—charges and payouts belong to your business, not the platform.
                    </p>
                    <p>
                        You only need one active payment processor per workspace. If you use QuickBooks Online for payments instead, disconnect Stripe here first (or disconnect QuickBooks on its page before finishing Stripe).
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Who it is for
                </h3>
                <p class="mt-3 text-md leading-relaxed text-gray-600 dark:text-gray-300">
                    Dealership staff with access to <strong class="text-gray-900 dark:text-gray-100">Account → Payments → Stripe</strong> can start onboarding, refresh status, and disconnect.
                    Customers never see this page—they only see checkout when paying an invoice.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    How it works
                </h3>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-md leading-relaxed text-gray-600 dark:text-gray-300">
                    <li>Click <strong class="text-gray-900 dark:text-gray-100">Connect with Stripe</strong> to create or resume an Express account.</li>
                    <li>Complete Stripe’s onboarding (business details, bank account, identity checks).</li>
                    <li>Use <strong class="text-gray-900 dark:text-gray-100">Check status with Stripe</strong> to refresh capability flags after Stripe finishes review.</li>
                    <li>Enable checkout methods below (card, ACH, etc.) according to what your Stripe account supports.</li>
                </ol>
            </section>

            <div
                v-if="!can_connect_stripe"
                class="flex gap-3 rounded-lg border border-primary-200/90 bg-primary-50 px-4 py-3 text-md text-primary-950 dark:border-primary-800 dark:bg-primary-950/85 dark:text-primary-50"
                role="status"
            >
                <span class="material-icons shrink-0 text-[20px] text-primary-700 dark:text-primary-200" aria-hidden="true">info</span>
                <p class="leading-relaxed">
                    QuickBooks Online is the active payment connection. Disconnect QuickBooks on the QuickBooks page to use Stripe here.
                </p>
            </div>

            <div
                v-if="stripe.setup_hint && can_connect_stripe"
                class="flex gap-3 rounded-lg border border-amber-200/90 bg-amber-50 px-4 py-3 text-md text-amber-950 dark:border-amber-800 dark:bg-amber-950/70 dark:text-amber-50"
                role="status"
            >
                <span class="material-icons shrink-0 text-[20px] text-amber-700 dark:text-amber-200" aria-hidden="true">info</span>
                <p class="leading-relaxed">{{ stripe.setup_hint }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
                    <p class="mt-1 font-medium" :class="statusMetricClass">
                        {{ stripe.status_label }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Charges</p>
                    <p
                        class="mt-1 font-medium"
                        :class="stripe.charges_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                    >
                        {{ stripe.charges_enabled ? 'Enabled' : 'Not enabled' }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Payouts</p>
                    <p
                        class="mt-1 font-medium"
                        :class="stripe.payouts_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                    >
                        {{ stripe.payouts_enabled ? 'Enabled' : 'Not enabled' }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Methods active</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                        {{ activeMethodCount }} of {{ paymentMethods.length }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Card payments</p>
                    <p
                        class="mt-1 font-medium"
                        :class="stripe.card_payments_capability === 'active'
                            ? 'text-green-600 dark:text-green-400'
                            : stripe.card_payments_capability === 'pending'
                                ? 'text-amber-600 dark:text-amber-400'
                                : stripe.card_payments_capability === 'inactive'
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-500 dark:text-gray-400'"
                    >
                        {{ capabilityLabel(stripe.card_payments_capability) }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Transfers</p>
                    <p
                        class="mt-1 font-medium"
                        :class="stripe.transfers_capability === 'active'
                            ? 'text-green-600 dark:text-green-400'
                            : stripe.transfers_capability === 'pending'
                                ? 'text-amber-600 dark:text-amber-400'
                                : stripe.transfers_capability === 'inactive'
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-500 dark:text-gray-400'"
                    >
                        {{ capabilityLabel(stripe.transfers_capability) }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/30">
                        <span class="material-icons text-[18px] text-primary-600 dark:text-primary-400">link</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900 dark:text-white">Connection</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Express account onboarding</p>
                    </div>
                    <span
                        class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium"
                        :class="stripe.ready
                            ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                            : stripe.account_id
                                ? 'bg-amber-50 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700/80 dark:text-gray-200'"
                    >
                        {{ stripe.ready ? 'Connected' : stripe.account_id ? 'In progress' : 'Not connected' }}
                    </span>
                </div>

                <div class="mt-5 divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-700 dark:border-gray-700">
                    <div v-if="stripe.account_id" class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Account ID</span>
                        <code class="rounded bg-gray-50 px-2 py-0.5 font-mono text-sm text-gray-700 dark:bg-gray-900/80 dark:text-gray-200">{{ stripe.account_id }}</code>
                    </div>
                    <div v-if="stripe.email" class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Stripe email</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ stripe.email }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Charges enabled</span>
                        <span class="text-md font-medium" :class="stripe.charges_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                            {{ stripe.charges_enabled ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Payouts enabled</span>
                        <span class="text-md font-medium" :class="stripe.payouts_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                            {{ stripe.payouts_enabled ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Details submitted</span>
                        <span
                            class="text-md font-medium"
                            :class="stripe.details_submitted ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                        >
                            {{ stripe.details_submitted ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    <div v-if="stripeConnectedAt" class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Connect started</span>
                        <span class="text-md text-gray-900 dark:text-white">{{ stripeConnectedAt }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Access token expires</span>
                        <span class="text-md text-gray-700 dark:text-gray-200">Not stored (Stripe Connect)</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-md text-gray-500 dark:text-gray-400">Refresh token expires</span>
                        <span class="text-md text-gray-700 dark:text-gray-200">Not stored (Stripe Connect)</span>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 dark:border-gray-700 sm:flex-row sm:flex-wrap">
                    <a
                        v-if="can_connect_stripe"
                        :href="route('stripe.connect')"
                        class="stripe-button flex-1 justify-center"
                    >
                        <span class="material-icons text-lg">link</span>
                        {{ stripe.account_id ? 'Continue setup' : 'Connect with Stripe' }}
                    </a>
                    <button
                        v-else
                        type="button"
                        disabled
                        class="inline-flex flex-1 cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-md font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-300"
                    >
                        <span class="material-icons text-lg" aria-hidden="true">block</span>
                        Unavailable while QuickBooks is active
                    </button>
                    <button
                        v-if="stripe.account_id"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-md font-medium text-gray-800 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                        :disabled="syncingStripe || !can_connect_stripe"
                        @click="verifyStripeStatus"
                    >
                        <span
                            class="material-icons text-lg"
                            :class="{ 'animate-spin': syncingStripe }"
                        >{{ syncingStripe ? 'sync' : 'cloud_download' }}</span>
                        {{ syncingStripe ? 'Checking…' : 'Check status with Stripe' }}
                    </button>
                    <button
                        v-if="stripe.account_id"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-md font-medium text-red-800 shadow-sm transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-200 dark:hover:bg-red-950/40"
                        :disabled="disconnectingStripe"
                        @click="disconnectStripe"
                    >
                        <span
                            class="material-icons text-lg"
                            :class="{ 'animate-spin': disconnectingStripe }"
                        >{{ disconnectingStripe ? 'sync' : 'link_off' }}</span>
                        {{ disconnectingStripe ? 'Disconnecting…' : 'Disconnect Stripe' }}
                    </button>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-gray-400 dark:text-gray-500">
                    Funds settle on your connected account. Maritime’s platform Stripe keys are only used to create the connection and process charges on your behalf.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span class="material-icons text-[18px] text-gray-500 dark:text-gray-400">credit_card</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Payment methods</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Shown to customers at checkout</p>
                    </div>
                </div>

                <p
                    v-if="!can_use_stripe_payment_methods"
                    class="mt-4 rounded-md border border-amber-200/90 bg-amber-50 px-3 py-2 text-sm leading-relaxed text-amber-950 dark:border-amber-800 dark:bg-amber-950/70 dark:text-amber-50"
                >
                    These toggles apply to Stripe checkout only. Disconnect QuickBooks on the QuickBooks page to change them.
                </p>

                <ul class="mt-5 divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-700 dark:border-gray-700">
                    <li
                        v-for="m in paymentMethods"
                        :key="m.code"
                        class="flex items-center justify-between gap-4 py-3.5"
                    >
                        <div class="min-w-0">
                            <p class="text-md font-medium text-gray-900 dark:text-white">{{ m.label }}</p>
                            <p class="mt-0.5 font-mono text-sm text-gray-400 dark:text-gray-500">{{ m.code }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2.5">
                            <span
                                class="rounded-md px-2 py-0.5 text-sm font-medium"
                                :class="m.is_enabled
                                    ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                            >
                                {{ m.is_enabled ? 'Active' : 'Off' }}
                            </span>
                            <input
                                type="checkbox"
                                :checked="m.is_enabled"
                                :disabled="!can_use_stripe_payment_methods"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700"
                                @change="toggleMethod(m.code, $event.target.checked)"
                            >
                        </div>
                    </li>
                </ul>

                <p class="mt-4 border-t border-gray-100 pt-4 text-sm leading-relaxed text-gray-400 dark:border-gray-700 dark:text-gray-500">
                    Method availability depends on your Stripe account region and enabled capabilities.
                </p>
            </div>
        </div>
    </TenantLayout>
</template>
