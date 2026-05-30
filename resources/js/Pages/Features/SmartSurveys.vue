<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'info',
        title: 'Step 1 — Survey Info',
        description: 'Name the survey, pick a type (feedback, lead intake, follow-up, or custom), assign an owner, and write internal and public descriptions. Set visibility to public or private before you build questions.',
    },
    {
        icon: 'quiz',
        title: 'Step 2 — Question Builder',
        description: 'Add, reorder, duplicate, and preview questions. Choose from text, long answer, multiple choice, dropdown, star rating, or NPS. Mark fields required and branch later questions with OR-based conditional logic.',
    },
    {
        icon: 'send',
        title: 'Step 3 — Delivery & Settings',
        description: 'Configure thank-you copy, optional redirect URL, privacy rules, and automation triggers. Surveys never auto-send without your confirmation — Helmful prompts you when a deal closes or a lead converts.',
    },
];

const capabilities = [
    {
        icon: 'library_books',
        title: 'Marine Template Library',
        description: 'Start from pre-built templates — post-purchase feedback, service department surveys, boat show follow-up, lead intake, financing experience, and more — or build from scratch.',
    },
    {
        icon: 'drag_indicator',
        title: 'Drag-and-Drop Questions',
        description: 'Reorder questions on the canvas, duplicate any block, toggle preview mode to see the respondent view, and tune rating scales or NPS labels per question.',
    },
    {
        icon: 'account_tree',
        title: 'Conditional Logic',
        description: 'Show follow-up questions only when an earlier answer matches. Rules reference prior questions with OR logic, so branching surveys stay readable without a separate form tool.',
    },
    {
        icon: 'link',
        title: 'Shareable & Embeddable',
        description: 'Copy a direct public link or iframe embed code per survey. Team-visible surveys can generate links tied to a specific salesperson for attribution.',
    },
    {
        icon: 'person_add',
        title: 'Lead Surveys → Leads',
        description: 'Lead-type surveys feed your pipeline: responses can be converted into leads in Helmful with conversion metrics tracked on the survey dashboard.',
    },
    {
        icon: 'notifications',
        title: 'Team Alerts on Submit',
        description: 'When someone completes a survey, the assignee gets an in-app notification and email. Respondents can receive a thank-you message, and the response is logged on the contact record.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'Templates Load from JSON',
        body: 'SurveyCreator opens with a template picker. Categories filter JSON definitions in resources/survey-templates — each file ships questions, type, and copy so you can publish in minutes instead of typing every field.',
    },
    {
        step: '02',
        title: 'Questions Persist with Logic',
        body: 'On save, SurveyController stores the survey row and creates SurveyQuestion records with type, label, options, config, order, and conditional_logic. The builder serializes OR rules when a question should only appear if a prior answer equals a value.',
    },
    {
        step: '03',
        title: 'Public Intake & Privacy',
        body: 'PublicSurveyController serves the respondent experience with privacy_settings enforced: anonymous mode, required identity, one response per email, or post-submit results. Answers land in SurveyResponse and SurveyResponseAnswer tables.',
    },
    {
        step: '04',
        title: 'Post-Submit Automation',
        body: 'ProcessSurveyResponse queues thank-you email to the respondent, in-app and email notification to the assignee, and logs a communication on the matching contact when an email is known.',
    },
    {
        step: '05',
        title: 'Triggers Prompt, Never Blast',
        body: 'Automation triggers (after transaction close, X days from sale, on lead conversion) only create send prompts — immediate or delayed by N days. Your team confirms before a survey goes out, keeping outreach intentional.',
    },
];
</script>

<template>
    <Head title="Smart Surveys" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

            <!-- Hero -->
            <section class="relative overflow-hidden bg-gray-950 px-6 pb-12 pt-24 lg:pb-36 sm:px-12 lg:px-24">

                <div class="pointer-events-none absolute inset-0 opacity-[0.04]"
                    style="background-image: linear-gradient(to right, #60a5fa 1px, transparent 1px), linear-gradient(to bottom, #60a5fa 1px, transparent 1px); background-size: 48px 48px;">
                </div>

                <div class="pointer-events-none absolute -top-32 left-1/2 h-[500px] w-[700px] -translate-x-1/2 rounded-full bg-primary-500/10 blur-[120px]"></div>

                <div class="relative mx-auto max-w-7xl">
                    <Link
                        v-if="route().has('features')"
                        :href="route('features')"
                        class="mb-10 inline-flex items-center gap-1.5 text-sm font-medium text-gray-400 transition hover:text-white"
                    >
                        <span class="material-icons text-base leading-none">arrow_back</span>
                        All features
                    </Link>

                    <div class="mb-6 flex">
                        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-500/20 bg-primary-500/10 px-4 py-1.5">
                            <span class="material-icons text-sm leading-none text-primary-400">poll</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Smart Surveys</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Build surveys that<br>
                        <span class="text-primary-400">stay in your system.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        A guided three-step builder, marine-ready templates, and responses that
                        notify your team, log communications, and convert to leads when you are ready.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">3</div>
                            <div class="mt-1 text-sm text-gray-400">Step builder</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">6</div>
                            <div class="mt-1 text-sm text-gray-400">Question types</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">8+</div>
                            <div class="mt-1 text-sm text-gray-400">Marine templates</div>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-0 left-0 right-0 leading-none">
                    <svg viewBox="0 0 1440 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="h-16 w-full">
                        <path
                            class="fill-gray-50 dark:fill-gray-900"
                            d="M0,32 C180,64 360,0 540,32 C720,64 900,0 1080,32 C1260,64 1350,16 1440,32 L1440,64 L0,64 Z"
                        />
                    </svg>
                </div>
            </section>

            <!-- Survey builder -->
            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">The survey builder</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Three steps from template to live
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            SurveyCreator walks you through setup, questions, and delivery in order.
                            Save as draft anytime, switch templates before you start, and preview the respondent experience before publishing.
                        </p>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-3">
                        <div
                            v-for="(item, i) in hierarchy"
                            :key="item.title"
                            class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-gray-700/60 dark:bg-gray-800/80"
                        >
                            <div class="absolute right-5 top-5 select-none text-5xl font-black text-gray-100 dark:text-gray-700/60">
                                {{ String(i + 1).padStart(2, '0') }}
                            </div>
                            <div class="mb-5 inline-flex rounded-xl bg-primary-50 p-3 dark:bg-primary-900/30">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400">{{ item.icon }}</span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ item.title }}</h3>
                            <p class="mt-2 text-md leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Capabilities -->
            <section class="bg-secondary-50 px-6 py-24 dark:bg-secondary-950/25 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-secondary-700 dark:text-secondary-400">What it does</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Collect feedback and leads in one flow
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Beyond the builder, Smart Surveys connect to the rest of Helmful —
                            so a boat show follow-up or post-sale NPS is never stuck in a separate tool.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="cap in capabilities"
                            :key="cap.title"
                            class="group rounded-2xl border border-secondary-200 bg-white p-6 shadow-sm transition hover:border-secondary-400 hover:shadow-md dark:border-secondary-800 dark:bg-gray-900 dark:hover:border-secondary-600"
                        >
                            <div class="mb-4 inline-flex rounded-lg bg-secondary-100 p-2.5 dark:bg-secondary-900/50">
                                <span class="material-icons text-xl leading-none text-secondary-600 dark:text-secondary-400">{{ cap.icon }}</span>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ cap.title }}</h3>
                            <p class="mt-2 text-md leading-relaxed text-gray-600 dark:text-gray-400">{{ cap.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Behind the scenes -->
            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">Under the hood</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            From submit to sales follow-up
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Every response is stored, attributable, and actionable inside your tenant —
                            with notifications and optional lead conversion built in.
                        </p>
                    </div>

                    <div class="relative">
                        <div class="absolute left-[2.75rem] top-12 hidden h-[calc(100%-6rem)] w-px bg-gradient-to-b from-primary-500/40 via-primary-500/20 to-transparent lg:block"></div>

                        <ol class="space-y-4">
                            <li
                                v-for="item in behindTheScenes"
                                :key="item.step"
                                class="flex gap-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700/60 dark:bg-gray-800/80"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold tracking-wide text-white">
                                    {{ item.step }}
                                </div>
                                <div class="pt-1">
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ item.title }}</h3>
                                    <p class="mt-1.5 text-md leading-relaxed text-gray-500 dark:text-gray-400">{{ item.body }}</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <FeaturePageCta
                badge="Built for marine retail"
                badge-icon="poll"
                title="Stop exporting survey data into spreadsheets"
                description="Helmful surveys capture feedback and leads where your team already works — with templates and a builder designed for dealership workflows."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
