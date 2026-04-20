<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const STRIPE_PRICING = 'https://stripe.com/pricing';
const STRIPE_CONNECT = 'https://stripe.com/connect';
const STRIPE_SECURITY = 'https://stripe.com/docs/security';

const breadcrumbItems = computed(() => [
    { label: 'Account', href: route('account.index') },
    { label: 'Payments', href: route('account.payments') },
    { label: 'About Stripe' },
]);
</script>

<template>
    <Head title="About Stripe" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full max-w-3xl">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">
                    About Stripe
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Who Stripe is, how invoice payments work with your connected account, and where to find current processing fees.
                </p>
                <div class="mt-4">
                    <Link
                        :href="route('account.payments')"
                        class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-base">arrow_back</span>
                        Back to payments
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-10 px-4 pb-16 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Who Stripe is
                </h3>
                <div class="mt-3 space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    <p>
                        <a
                            :href="STRIPE_CONNECT"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-primary-600 underline decoration-primary-300 hover:text-primary-700 dark:text-primary-400"
                        >Stripe</a>
                        is a major payment technology company. They provide the software, banking relationships, and compliance infrastructure that let businesses accept cards, bank debits, and other payment methods over the internet.
                    </p>
                    <p>
                        Stripe is not your dealership’s bank account by itself: they move money between your customer’s payment method and the payout destination you configure with Stripe (typically your business bank account). They are regulated and operate under strict security and risk programs.
                    </p>
                    <p>
                        For a deeper overview of how Stripe approaches security and compliance, see Stripe’s
                        <a
                            :href="STRIPE_SECURITY"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-primary-600 underline decoration-primary-300 hover:text-primary-700 dark:text-primary-400"
                        >security documentation</a>.
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    How it works in Maritime
                </h3>
                <div class="mt-3 space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    <p>
                        When you connect Stripe from <strong class="text-gray-800 dark:text-gray-200">Account → Payments → Stripe</strong>, Maritime helps you create or link a
                        <strong class="text-gray-800 dark:text-gray-200">Stripe Express</strong> connected account. That account is yours: customers who pay an invoice online complete checkout with Stripe, and the charge is processed on <em>your</em> connected account.
                    </p>
                    <p>
                        Maritime does not hold customer card funds as an intermediary for those invoice payments. Payout timing, statements, disputes, and tax reporting for those charges are handled in your Stripe Dashboard (subject to Stripe’s and your account’s rules).
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Maritime’s own subscription or software billing (if any) is separate from your Stripe Connect invoice payments and appears on your Maritime invoices or statements, not as an extra line item inside each customer’s Stripe checkout unless you configure it that way elsewhere.
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    What Stripe typically charges (United States)
                </h3>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    Stripe’s fees depend on country, payment method, and whether the card is domestic or international. For the United States, their public <strong class="text-gray-800 dark:text-gray-200">standard</strong> pricing (as commonly published for online businesses) includes:
                </p>
                <ul class="mt-4 list-inside list-disc space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <strong class="text-gray-800 dark:text-gray-200">Cards</strong> (standard domestic consumer and business cards used online): on the order of <strong class="text-gray-800 dark:text-gray-200">2.9% + 30¢</strong> per successful charge. Manually entered cards and international cards often have <em>additional</em> percentage fees on top of that base.
                    </li>
                    <li>
                        <strong class="text-gray-800 dark:text-gray-200">ACH / US bank debits</strong> (when enabled and offered by Stripe for your flow): commonly a small percentage of the payment with a per-debit cap (see Stripe’s pricing page for the exact current numbers).
                    </li>
                </ul>
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50/80 p-3 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                    <strong class="font-semibold">Important:</strong> Stripe updates pricing and product names from time to time. The figures above are a plain-language summary for orientation only. Your effective rate can differ based on card type, presentment, Connect pricing, currency conversion, disputes, and any custom agreement you have with Stripe.
                </p>
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    Always use Stripe’s official page for the numbers that apply to you:
                    <a
                        :href="STRIPE_PRICING"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-primary-600 underline decoration-primary-300 hover:text-primary-700 dark:text-primary-400"
                    >https://stripe.com/pricing</a>
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Surcharges on invoices
                </h3>
                <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    If you configure a card surcharge on an invoice in Maritime, that amount is part of what the customer authorizes at checkout. Stripe still charges their processing fee on the total amount captured according to their pricing; the surcharge is not a separate “Maritime fee” from Stripe—it is part of the charge your customer pays.
                </p>
            </section>

            <div class="text-center">
                <Link
                    :href="route('account.payments')"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-primary-700"
                >
                    Return to payment settings
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
