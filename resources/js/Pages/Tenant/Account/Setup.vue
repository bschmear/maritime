<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    account_setup_complete: { type: Boolean, default: false },
    summary: {
        type: Object,
        default: () => ({ total: 0, completed: 0, skipped: 0, pending: 0, resolved: 0 }),
    },
    groups: { type: Array, default: () => [] },
});

const breadcrumbItems = [
    { label: 'Home', href: route('dashboard') },
    { label: 'Account', href: route('account.index') },
    { label: 'Workspace tour' },
];

const progressPercent = computed(() => {
    if (!props.summary.total) {
        return 0;
    }

    return Math.round((props.summary.resolved / props.summary.total) * 100);
});

function statusLabel(status) {
    return {
        pending: 'Pending',
        completed: 'Completed',
        skipped: 'Skipped',
    }[status] ?? status;
}

function statusClasses(status) {
    if (status === 'completed') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200';
    }
    if (status === 'skipped') {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
    }

    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
}

function updateStepStatus(key, status) {
    router.patch(route('account.setup.steps.update', key), { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Workspace tour" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
            <div class="mt-4">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Workspace tour</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300">
                    Configure your account and learn where everything lives. Each step links to a real area of the app —
                    explore it at your own pace, then mark complete when you are ready.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-8">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tour progress</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ summary.resolved }} of {{ summary.total }} steps done
                        </p>
                    </div>
                    <div class="min-w-[12rem] flex-1 max-w-xs">
                        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-full rounded-full bg-indigo-600 transition-all"
                                :style="{ width: `${progressPercent}%` }"
                            />
                        </div>
                        <p class="mt-1 text-right text-xs text-gray-500 dark:text-gray-400">{{ progressPercent }}%</p>
                    </div>
                </div>
            </section>

            <section
                v-for="group in groups"
                :key="group.area"
                class="space-y-4"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ group.label }}
                </h2>

                <div class="space-y-3">
                    <article
                        v-for="step in group.steps"
                        :key="step.key"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        :class="step.status !== 'pending' ? 'opacity-80' : ''"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <span
                                    v-if="step.icon"
                                    class="material-icons mt-0.5 text-2xl text-indigo-600 dark:text-indigo-400"
                                >{{ step.icon }}</span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                            {{ step.title }}
                                        </h3>
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="statusClasses(step.status)"
                                        >
                                            {{ statusLabel(step.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                                <Link
                                    :href="step.url"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    <span class="material-icons text-base">open_in_new</span>
                                    Open
                                </Link>
                                <div
                                    v-if="step.status === 'pending'"
                                    class="flex flex-wrap gap-3 text-xs"
                                >
                                    <button
                                        type="button"
                                        class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                        @click="updateStepStatus(step.key, 'completed')"
                                    >
                                        Mark complete
                                    </button>
                                    <button
                                        type="button"
                                        class="font-medium text-gray-500 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300"
                                        @click="updateStepStatus(step.key, 'skipped')"
                                    >
                                        Skip
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
