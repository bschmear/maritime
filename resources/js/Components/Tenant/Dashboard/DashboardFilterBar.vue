<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ subsidiary_id: null, location_id: null }),
    },
    options: {
        type: Object,
        default: () => ({ subsidiaries: [], locations: [] }),
    },
});

const panelOpen = ref(false);
const subsidiaryId = ref(props.filters?.subsidiary_id ?? null);
const locationId = ref(props.filters?.location_id ?? null);

watch(
    () => [props.filters?.subsidiary_id, props.filters?.location_id],
    () => {
        subsidiaryId.value = props.filters?.subsidiary_id ?? null;
        locationId.value = props.filters?.location_id ?? null;
    }
);

const subsidiaryOptions = computed(() => props.options?.subsidiaries ?? []);

const locationOptions = computed(() => {
    const all = props.options?.locations ?? [];
    if (!subsidiaryId.value) {
        return all;
    }
    return all.filter((loc) => (loc.subsidiary_ids ?? []).includes(Number(subsidiaryId.value)));
});

const activeFilterCount = computed(() => {
    let count = 0;
    if (subsidiaryId.value) count++;
    if (locationId.value) count++;
    return count;
});

const summaryLabel = computed(() => {
    if (!activeFilterCount.value) {
        return 'All subsidiaries & locations';
    }
    const parts = [];
    const sub = subsidiaryOptions.value.find((s) => s.id === subsidiaryId.value);
    if (sub) {
        parts.push(sub.label);
    }
    const loc = (props.options?.locations ?? []).find((l) => l.id === locationId.value);
    if (loc) {
        parts.push(loc.label);
    }
    return parts.join(' · ') || 'Filtered';
});

watch(subsidiaryId, (next, prev) => {
    if (next === prev) {
        return;
    }
    if (!locationId.value) {
        return;
    }
    const stillValid = locationOptions.value.some((loc) => loc.id === locationId.value);
    if (!stillValid) {
        locationId.value = null;
    }
});

function applyFilters() {
    router.post(
        route('dashboard.filters'),
        {
            subsidiary_id: subsidiaryId.value || null,
            location_id: locationId.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                panelOpen.value = false;
            },
        }
    );
}

function clearFilters() {
    subsidiaryId.value = null;
    locationId.value = null;
    applyFilters();
}
</script>

<template>
    <div class="w-full sm:w-auto">
        <!-- Mobile: compact toggle -->
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm transition-colors sm:hidden"
            :class="
                activeFilterCount
                    ? 'border-primary-300 bg-primary-50 text-primary-800 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-100'
                    : 'border-gray-200 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'
            "
            @click="panelOpen = !panelOpen"
        >
            <span class="flex min-w-0 items-center gap-2">
                <span class="material-icons text-base">filter_list</span>
                <span class="truncate font-medium">{{ summaryLabel }}</span>
            </span>
            <span class="material-icons text-base text-gray-400">{{ panelOpen ? 'expand_less' : 'expand_more' }}</span>
        </button>

        <!-- Desktop: inline controls -->
        <div class="hidden sm:flex sm:flex-wrap sm:items-end sm:gap-2">
            <div class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Subsidiary</label>
                <select v-model="subsidiaryId" class="input-style w-full text-sm">
                    <option :value="null">All subsidiaries</option>
                    <option v-for="opt in subsidiaryOptions" :key="`sub-${opt.id}`" :value="opt.id">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <div class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Location</label>
                <select
                    v-model="locationId"
                    class="input-style w-full text-sm"
                    :disabled="!subsidiaryId && locationOptions.length === 0"
                >
                    <option :value="null">All locations</option>
                    <option v-for="opt in locationOptions" :key="`loc-${opt.id}`" :value="opt.id">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <button
                type="button"
                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                @click="applyFilters"
            >
                Apply
            </button>
            <button
                v-if="activeFilterCount"
                type="button"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                @click="clearFilters"
            >
                Clear
            </button>
        </div>

        <!-- Mobile: expandable panel -->
        <div
            v-show="panelOpen"
            class="mt-2 space-y-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-600 dark:bg-gray-800 sm:hidden"
        >
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subsidiary</label>
                <select v-model="subsidiaryId" class="input-style w-full">
                    <option :value="null">All subsidiaries</option>
                    <option v-for="opt in subsidiaryOptions" :key="`m-sub-${opt.id}`" :value="opt.id">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                <select v-model="locationId" class="input-style w-full">
                    <option :value="null">All locations</option>
                    <option v-for="opt in locationOptions" :key="`m-loc-${opt.id}`" :value="opt.id">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="applyFilters"
                >
                    Apply filters
                </button>
                <button
                    v-if="activeFilterCount"
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300"
                    @click="clearFilters"
                >
                    Clear
                </button>
            </div>
        </div>
    </div>
</template>
