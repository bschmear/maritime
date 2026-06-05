<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    step: {
        type: Object,
        required: true,
    },
    expanded: {
        type: Boolean,
        default: undefined,
    },
    /** When true, card fills its column (vertical pipeline layout). */
    fluid: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle']);

const localExpanded = ref(false);

const isExpanded = computed(() => {
    if (props.expanded !== undefined) {
        return props.expanded;
    }
    return localExpanded.value;
});

const accentClasses = computed(() => {
    const map = {
        slate: 'border-slate-400 dark:border-slate-500',
        amber: 'border-amber-400 dark:border-amber-500',
        yellow: 'border-yellow-400 dark:border-yellow-500',
        orange: 'border-orange-400 dark:border-orange-500',
        emerald: 'border-emerald-400 dark:border-emerald-500',
        blue: 'border-blue-400 dark:border-blue-500',
        indigo: 'border-indigo-400 dark:border-indigo-500',
        violet: 'border-violet-400 dark:border-violet-500',
        purple: 'border-purple-400 dark:border-purple-500',
        fuchsia: 'border-fuchsia-400 dark:border-fuchsia-500',
        teal: 'border-teal-400 dark:border-teal-500',
        cyan: 'border-cyan-400 dark:border-cyan-500',
        rose: 'border-rose-400 dark:border-rose-500',
        green: 'border-green-500 dark:border-green-500 ring-2 ring-green-400/30',
    };
    return map[props.step.accent] ?? map.slate;
});

const recordHref = computed(() => {
    if (!props.step.routeName) {
        return null;
    }
    try {
        return route(props.step.routeName);
    } catch {
        return null;
    }
});

function onToggle() {
    if (props.expanded === undefined) {
        localExpanded.value = !localExpanded.value;
    }
    emit('toggle', props.step.id);
}
</script>

<template>
    <article
        class="flex flex-col rounded-lg border-2 bg-white shadow-sm transition-shadow dark:bg-gray-800"
        :class="[
            accentClasses,
            fluid ? 'w-full max-w-md' : 'w-[220px] shrink-0 sm:w-[240px]',
            { 'shadow-md ring-2 ring-primary-400/40': isExpanded },
        ]"
    >
        <button
            type="button"
            class="w-full px-3 py-2.5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded-lg"
            @click="onToggle"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ step.title }}
                        </h3>
                        <span
                            v-if="step.optional"
                            class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        >
                            Optional
                        </span>
                        <span
                            v-if="step.milestone"
                            class="rounded bg-green-200 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-green-800 dark:bg-green-900 dark:text-green-200"
                        >
                            Complete
                        </span>
                    </div>
                    <p v-if="step.subtitle" class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                        {{ step.subtitle }}
                    </p>
                </div>
                <span
                    class="material-icons flex-shrink-0 text-lg text-gray-500 transition-transform dark:text-gray-400"
                    :class="{ 'rotate-180': isExpanded }"
                    aria-hidden="true"
                >
                    expand_more
                </span>
            </div>
            <p class="mt-1 text-sm text-primary-600 dark:text-primary-400">
                {{ isExpanded ? 'Click to collapse' : 'Click for details' }}
            </p>
        </button>

        <div
            v-show="isExpanded"
            class="border-t border-gray-200 px-3 py-2.5 dark:border-gray-600"
        >
            <ul class="space-y-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                <li v-for="(note, index) in step.notes" :key="index" class="flex gap-2">
                    <span class="mt-1.5 h-1 w-1 flex-shrink-0 rounded-full bg-primary-500" />
                    <span>{{ note }}</span>
                </li>
            </ul>
            <Link
                v-if="recordHref"
                :href="recordHref"
                class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                @click.stop
            >
                Open list
                <span class="material-icons text-sm">arrow_forward</span>
            </Link>
        </div>
    </article>
</template>
