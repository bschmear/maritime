<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'account_setup_ignored_steps';

const page = usePage();
const collapsed = ref(false);
const ignoredStepIds = ref([]);

const setup = computed(() => page.props.account_setup ?? null);
const showWidget = computed(() => setup.value?.show_widget === true);

const nextStep = computed(() => {
    const pending = setup.value?.pending_steps ?? [];

    return pending.find((step) => !ignoredStepIds.value.includes(step.id)) ?? null;
});

const summary = computed(() => setup.value?.summary ?? { total: 0, resolved: 0, pending: 0 });
const progressLabel = computed(() => {
    if (!nextStep.value) {
        return `Setup tour · ${summary.value.resolved} of ${summary.value.total} done`;
    }

    const index = summary.value.resolved + 1;

    return `Step ${index} of ${summary.value.total}`;
});

function loadIgnored() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        ignoredStepIds.value = raw ? JSON.parse(raw) : [];
    } catch {
        ignoredStepIds.value = [];
    }
}

function saveIgnored() {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(ignoredStepIds.value));
}

function ignoreStep() {
    if (!nextStep.value) {
        return;
    }

    if (!ignoredStepIds.value.includes(nextStep.value.id)) {
        ignoredStepIds.value = [...ignoredStepIds.value, nextStep.value.id];
        saveIgnored();
    }
}

function updateStepStatus(status) {
    if (!nextStep.value) {
        return;
    }

    router.patch(
        route('account.setup.steps.update', nextStep.value.key),
        { status },
        {
            preserveScroll: true,
            only: ['account_setup'],
        },
    );
}

onMounted(() => {
    loadIgnored();
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showWidget && (nextStep || collapsed)"
            class="fixed right-4 top-20 z-[90] w-[min(100vw-2rem,22rem)]"
        >
            <div
                v-if="collapsed"
                class="flex justify-end"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-secondary-200 bg-white px-4 py-2 text-sm font-medium text-secondary-700 shadow-lg hover:bg-secondary-50 dark:border-secondary-800 dark:bg-gray-800 dark:text-secondary-300 dark:hover:bg-gray-700"
                    @click="collapsed = false"
                >
                    <span class="material-icons text-base">explore</span>
                    {{ progressLabel }}
                </button>
            </div>

            <div
                v-else-if="nextStep"
                class="overflow-hidden rounded-xl border border-secondary-200 bg-white shadow-xl dark:border-secondary-800 dark:bg-gray-800"
            >
                <div class="flex items-start justify-between gap-3 border-b border-secondary-100 bg-secondary-50 px-4 py-3 dark:border-secondary-900 dark:bg-secondary-950/40">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary-700 dark:text-secondary-300">
                            Get to know your workspace
                        </p>
                        <p class="mt-0.5 text-sm text-secondary-900 dark:text-secondary-100">
                            {{ progressLabel }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-secondary-600 hover:bg-secondary-100 dark:text-secondary-300 dark:hover:bg-secondary-900/50"
                        aria-label="Collapse setup tour"
                        @click="collapsed = true"
                    >
                        <span class="material-icons text-lg">expand_more</span>
                    </button>
                </div>

                <div class="space-y-3 px-4 py-4">
                    <div class="flex items-start gap-3">
                        <span
                            v-if="nextStep.icon"
                            class="material-icons mt-0.5 text-2xl text-secondary-600 dark:text-secondary-400"
                        >{{ nextStep.icon }}</span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ nextStep.title }}
                            </h3>
                            <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                {{ nextStep.description }}
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="nextStep.url"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-secondary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-secondary-700"
                    >
                        <span class="material-icons text-base">open_in_new</span>
                        Explore this area
                    </Link>

                    <div class="flex flex-col gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-secondary-600 bg-secondary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-secondary-700 dark:border-secondary-500 dark:bg-secondary-600 dark:hover:bg-secondary-500"
                            @click="updateStepStatus('completed')"
                        >
                            Mark complete
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-secondary-300 bg-secondary-50 px-3 py-2 text-xs font-semibold text-secondary-800 shadow-sm hover:bg-secondary-100 dark:border-secondary-700 dark:bg-secondary-950/50 dark:text-secondary-200 dark:hover:bg-secondary-900/60"
                            @click="updateStepStatus('skipped')"
                        >
                            Skip
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            @click="ignoreStep"
                        >
                            Ignore for now
                        </button>
                    </div>

                    <Link
                        :href="route('account.setup.index')"
                        class="block text-center text-xs font-medium text-secondary-600 hover:underline dark:text-secondary-400"
                    >
                        View full tour
                    </Link>
                </div>
            </div>
        </div>
    </Teleport>
</template>
