<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    legalEmail: {
        type: String,
        required: true,
    },
});

const page = usePage();
const appName = computed(() => page.props.app.name);

/** Matches prior copy (“Helmful Inc.”); override via config later if the legal name diverges from APP_NAME. */
const legalEntityName = computed(() => `${appName.value} Inc.`);

const lastUpdated = 'April 12, 2026';

const sections = computed(() => {
    const n = appName.value;

    return [
        {
            title: 'Acceptance of Terms',
            content: `By accessing or using ${n}'s platform, applications, and related services (the "Service"), you agree to be bound by these Terms and Conditions. If you do not agree, you may not use the Service. If you are using the Service on behalf of a dealership or other entity, you represent that you have authority to bind that entity.`,
        },
        {
            title: 'Description of Service',
            content: `${n} provides a cloud-based platform for boat dealerships and marine businesses to manage contacts, leads, customers, inventory, estimates, deals, service operations, events, surveys, and related workflows. ${n} is a software provider only and is not a broker, dealer, or party to any transaction between you and your customers.`,
        },
        {
            title: 'Account Responsibilities',
            content: `You are responsible for maintaining the confidentiality of your account credentials and for all activity under your account. You agree to ensure all users within your organization comply with these Terms. You must provide accurate information and keep it up to date. ${n} is not responsible for unauthorized access resulting from your failure to secure your account.`,
        },
        {
            title: 'Use of the Service',
            content: `You agree to use the Service only for lawful business purposes. You may not engage in fraudulent, deceptive, or illegal activities, including misrepresentation of inventory or pricing. You may not interfere with or disrupt the Service or attempt unauthorized access. ${n} may suspend or terminate accounts that violate these Terms.`,
        },
        {
            title: 'Data Ownership and Usage',
            content: `You retain ownership of all data you submit, including customer information, inventory, communications, and transaction records. You grant ${n} a limited license to store and process this data solely to provide the Service. You are responsible for ensuring your data complies with applicable laws.`,
        },
        {
            title: 'Inventory Listings and Transactions',
            content: `You are solely responsible for the accuracy and legality of all inventory listings, pricing, and transaction details. ${n} does not verify listings and is not responsible for disputes between you and your customers. All transactions and agreements are solely between you and the applicable third party.`,
        },
        {
            title: 'Payments and Financial Transactions',
            content: `The Service may integrate with third-party payment providers. ${n} does not process payments directly and is not responsible for payment failures, disputes, chargebacks, or financial losses. You are responsible for complying with all applicable payment and financial regulations.`,
        },
        {
            title: 'Events and Scheduling',
            content: `The Service may allow you to manage events, appointments, or boat shows. ${n} is not responsible for the execution, safety, or outcomes of any events. You are solely responsible for all event-related logistics and liabilities.`,
        },
        {
            title: 'Surveys and Communications',
            content: `You may use the Service to send surveys and communications. You are responsible for ensuring compliance with all applicable laws, including email, SMS, and privacy regulations. ${n} is not responsible for the content or delivery of such communications.`,
        },
        {
            title: 'Regulatory Compliance',
            content: `You are solely responsible for complying with all applicable laws, including those related to vessel registration, titling, taxes, and required disclosures. ${n} does not provide legal or regulatory advice.`,
        },
        {
            title: 'Third-Party Services',
            content: `The Service may integrate with third-party services. ${n} is not responsible for the performance, availability, or security of these services. Your use of third-party services is subject to their terms.`,
        },
        {
            title: 'User Access and Permissions',
            content: `You are responsible for managing user access within your organization. ${n} is not liable for actions taken by authorized users, including data errors, deletions, or misuse of the Service.`,
        },
        {
            title: 'Data Retention and Export',
            content: `You are responsible for maintaining backups of your data. While ${n} provides data access and export tools, we do not guarantee indefinite storage. Data may be deleted after account termination following a reasonable retention period.`,
        },
        {
            title: 'Subscription and Billing',
            content: `Certain features require a paid subscription. By subscribing, you authorize recurring charges to your payment method. Fees are non-refundable unless stated otherwise. ${n} may change pricing with at least 30 days’ notice. Failure to pay may result in suspension or termination.`,
        },
        {
            title: 'Service Availability and Modifications',
            content: `${n} does not guarantee uninterrupted or error-free operation. We may modify or discontinue features at any time. We are not liable for losses resulting from downtime, data loss, or system changes.`,
        },
        {
            title: 'Intellectual Property',
            content: `All rights in the Service, including software and design, are owned by ${n}. You are granted a limited license to use the Service. You may not copy, modify, or reverse engineer any part of the Service.`,
        },
        {
            title: 'Limitation of Liability',
            content: `To the fullest extent permitted by law, ${n} is not liable for indirect or consequential damages, including loss of profits or data. Our total liability shall not exceed the amount you paid in the twelve months preceding the claim.`,
        },
        {
            title: 'Termination',
            content: `You may stop using the Service at any time. ${n} may suspend or terminate your access for violations of these Terms or non-payment. Upon termination, your data may be deleted after a reasonable period.`,
        },
        {
            title: 'Modifications to Terms',
            content: `${n} may update these Terms from time to time. We will provide notice of material changes. Continued use of the Service constitutes acceptance of the updated Terms.`,
        },
        {
            title: 'Governing Law',
            content: `These Terms are governed by the laws of the State of Florida. Any disputes shall be resolved in the courts of Miami-Dade County, Florida.`,
        },
    ];
});
</script>

<template>
    <Head title="Terms and Conditions" />

    <AppLayout>

        <!-- Hero -->
        <section class="relative bg-gradient-to-br from-primary-100 via-secondary-50 to-primary-50 dark:from-navy-900 dark:via-primary-950 dark:to-navy-800 overflow-hidden">
            <div class="absolute inset-0 opacity-20 dark:opacity-10">
                <div class="absolute top-10 right-20 w-72 h-72 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 left-10 w-64 h-64 bg-gradient-to-tr from-secondary-500 to-primary-300 rounded-full blur-3xl"></div>
            </div>
            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary-200/60 dark:bg-primary-800/40 backdrop-blur-sm rounded-full text-primary-800 dark:text-primary-200 text-sm font-medium mb-6 border border-primary-300 dark:border-primary-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Legal</span>
                </div>
                <h1 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                    Terms and
                    <span class="bg-gradient-to-r from-primary-600 to-secondary-500 dark:from-primary-400 dark:to-secondary-400 bg-clip-text text-transparent">
                        Conditions
                    </span>
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Last updated {{ lastUpdated }}
                </p>
            </div>
        </section>

        <!-- Content -->
        <section class="py-20 bg-white dark:bg-navy-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Intro -->
                <div class="bg-primary-50 dark:bg-navy-800 rounded-2xl p-6 mb-12 border border-primary-100 dark:border-navy-700">
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        Please read these Terms and Conditions carefully before using the {{ appName }} platform. These terms constitute a legally binding agreement between you and {{ legalEntityName }} governing your access to and use of our services. If you are entering into these Terms on behalf of a company or other legal entity, you represent that you have the authority to bind that entity.
                    </p>
                </div>

                <!-- Table of contents -->
                <div class="mb-12">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Table of Contents</h2>
                    <ol class="space-y-1">
                        <li v-for="(section, i) in sections" :key="i">
                            <a :href="`#section-${i}`"
                               class="text-primary-600 dark:text-primary-400 hover:underline text-sm">
                                {{ i + 1 }}. {{ section.title }}
                            </a>
                        </li>
                    </ol>
                </div>

                <!-- Sections -->
                <div class="space-y-10">
                    <div v-for="(section, i) in sections" :key="i" :id="`section-${i}`" class="scroll-mt-8">
                        <div class="flex items-start gap-4 mb-3">
                            <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 text-sm font-bold">
                                {{ i + 1 }}
                            </span>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white pt-1">{{ section.title }}</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed pl-12 whitespace-pre-line">
                            {{ section.content }}
                        </p>
                        <div v-if="i < sections.length - 1" class="mt-10 border-b border-gray-100 dark:border-navy-800"></div>
                    </div>
                </div>

                <!-- Footer note -->
                <div class="mt-16 pt-8 border-t border-gray-200 dark:border-navy-700 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        These Terms and Conditions were last updated on {{ lastUpdated }}.<br />
                        Questions? Contact us at
                        <a :href="`mailto:${legalEmail}`" class="text-primary-600 dark:text-primary-400 hover:underline">{{ legalEmail }}</a>
                    </p>
                </div>

            </div>
        </section>

    </AppLayout>
</template>
