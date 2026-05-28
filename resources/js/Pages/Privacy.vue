<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    legalEmail: {
        type: String,
        required: true,
    },
});

const lastUpdated = 'April 12, 2026';

const sections = [
    {
        title: 'Introduction',
        content: `This Privacy Policy explains how Helmful Inc. ("Helmful", "we", "us", or "our") collects, uses, and protects your information when you use our platform and services (the "Service"). By using the Service, you agree to the collection and use of information in accordance with this policy.`,
    },
    {
        title: 'Information We Collect',
        content: `We collect information you provide directly to us, including account information, contact and customer data, inventory details, communications, and transaction records. We also collect technical data such as IP address, browser type, device information, and usage activity within the Service.`,
    },
    {
        title: 'How We Use Information',
        content: `We use your information to provide, operate, and improve the Service. This includes managing your account, processing data, supporting your workflows, communicating with you, and ensuring the security and performance of the platform.`,
    },
    {
        title: 'Customer and Business Data',
        content: `You may upload and manage data related to your customers, leads, and business operations. This data remains under your control. Helmful processes this data solely to provide the Service and does not sell your data to third parties.`,
    },
    {
        title: 'Sharing of Information',
        content: `We do not sell your personal data. We may share information with trusted third-party service providers who help us operate the Service, such as hosting providers, payment processors, and communication tools. We may also disclose information if required by law or to protect our rights.`,
    },
    {
        title: 'Third-Party Services',
        content: `The Service may integrate with third-party services such as payment processors, email providers, or analytics tools. These services are governed by their own privacy policies, and Helmful is not responsible for their practices.`,
    },
    {
        title: 'Data Security',
        content: `We implement reasonable technical and organizational measures to protect your information from unauthorized access, loss, or misuse. However, no system is completely secure, and we cannot guarantee absolute security.`,
    },
    {
        title: 'Data Retention',
        content: `We retain your information for as long as necessary to provide the Service and fulfill our legal obligations. Upon account termination, your data may be deleted after a reasonable retention period.`,
    },
    {
        title: 'Your Responsibilities',
        content: `You are responsible for ensuring that the data you collect and store using the Service complies with applicable privacy laws. This includes obtaining any necessary consent from your customers before collecting or using their information.`,
    },
    {
        title: 'Your Rights',
        content: `Depending on your location, you may have rights to access, correct, or delete your personal information. You may contact us to request access or deletion of your data, subject to applicable legal requirements.`,
    },
    {
        title: 'Cookies and Tracking',
        content: `We may use cookies and similar technologies to improve user experience, analyze usage, and maintain session functionality. You can control cookie settings through your browser.`,
    },
    {
        title: 'Children’s Privacy',
        content: `The Service is not intended for use by individuals under the age of 18, and we do not knowingly collect personal information from children.`,
    },
    {
        title: 'International Data Transfers',
        content: `Your information may be stored and processed in the United States or other jurisdictions where our service providers operate. By using the Service, you consent to such transfers.`,
    },
    {
        title: 'Changes to This Policy',
        content: `We may update this Privacy Policy from time to time. If changes are material, we will provide notice within the Service or via email. Continued use of the Service constitutes acceptance of the updated policy.`,
    }
];
</script>

<template>
    <Head title="Privacy Policy" />

    <AppLayout>

        <!-- Hero -->
        <section class="relative border-b border-gray-200 bg-primary-50 py-20 dark:border-gray-800 dark:bg-gray-900 px-4 sm:px-6 lg:px-8">
            <div class="relative mx-auto max-w-4xl text-center">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-200/50 bg-primary-100 px-4 py-2 text-sm font-medium text-primary-700 backdrop-blur-sm dark:border-primary-700/50 dark:bg-primary-900/50 dark:text-primary-300">
                    <span class="material-icons text-base leading-none">policy</span>
                    <span>Legal</span>
                </div>
                <h1 class="mb-4 text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                    Privacy
                    <span class="text-primary-600 dark:text-primary-400">Policy</span>
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-300">
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
                        Your privacy is important to us. This Privacy Policy explains how Helmful collects, uses, and protects your information when using our platform. By using the Service, you agree to the practices described in this policy.
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
                        This Privacy Policy was last updated on {{ lastUpdated }}.<br />
                        Questions? Contact us at
                        <a :href="`mailto:${legalEmail}`" class="text-primary-600 dark:text-primary-400 hover:underline">{{ legalEmail }}</a>
                    </p>
                </div>

            </div>
        </section>

    </AppLayout>
</template>
