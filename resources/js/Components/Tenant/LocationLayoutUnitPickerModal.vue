<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    pickerUrl: { type: String, required: true },
    storeUrl: { type: String, required: true },
    locationName: { type: String, default: 'this location' },
    unitStatusOptions: { type: Array, default: () => [] },
    defaultStatusFilter: { type: Array, default: () => [1, 4, 6, 5] },
});

const emit = defineEmits(['update:modelValue', 'attached']);

const units = ref([]);
const loading = ref(false);
const submitting = ref(false);
const errorMsg = ref(null);
const searchQuery = ref('');
const scope = ref('all');
const selectedStatusIds = ref([...props.defaultStatusFilter]);
const pendingUnit = ref(null);
const confirmTransfer = ref(true);

const statusLabel = (id) => {
    const hit = props.unitStatusOptions.find(
        (o) => o.id === id || String(o.id) === String(id),
    );
    return hit?.name ?? `Status ${id}`;
};

function scopeButtonClass(key) {
    const active = scope.value === key;

    return active
        ? 'bg-slate-200 text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white'
        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/80';
}

const filteredUnits = computed(() => units.value);

function close() {
    emit('update:modelValue', false);
}

function reset() {
    units.value = [];
    searchQuery.value = '';
    scope.value = 'all';
    selectedStatusIds.value = [...props.defaultStatusFilter];
    pendingUnit.value = null;
    confirmTransfer.value = true;
    errorMsg.value = null;
}

async function fetchUnits() {
    if (!props.pickerUrl) return;
    loading.value = true;
    errorMsg.value = null;
    try {
        const response = await axios.get(props.pickerUrl, {
            params: {
                status: selectedStatusIds.value,
                scope: scope.value,
                search: searchQuery.value.trim() || undefined,
            },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        units.value = response.data.units ?? [];
    } catch (e) {
        errorMsg.value = e.response?.data?.message ?? 'Could not load units.';
        units.value = [];
    } finally {
        loading.value = false;
    }
}

function toggleStatus(id) {
    const idx = selectedStatusIds.value.indexOf(id);
    if (idx >= 0) {
        selectedStatusIds.value = selectedStatusIds.value.filter((v) => v !== id);
    } else {
        selectedStatusIds.value = [...selectedStatusIds.value, id];
    }
}

function beginAdd(unit) {
    if (unit.is_at_location) {
        submitAdd(unit, false);
        return;
    }
    pendingUnit.value = unit;
    confirmTransfer.value = true;
}

async function submitAdd(unit, transfer) {
    submitting.value = true;
    errorMsg.value = null;
    try {
        await axios.post(
            props.storeUrl,
            {
                asset_unit_id: unit.asset_unit_id,
                transfer,
            },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        pendingUnit.value = null;
        emit('attached');
        close();
    } catch (e) {
        errorMsg.value = e.response?.data?.message ?? 'Could not add unit.';
    } finally {
        submitting.value = false;
    }
}

function confirmPendingAdd() {
    if (!pendingUnit.value) return;
    submitAdd(pendingUnit.value, confirmTransfer.value);
}

let searchTimer = null;
watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchUnits, 300);
});

watch([scope, selectedStatusIds], fetchUnits, { deep: true });

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            reset();
            fetchUnits();
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-[220] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            @click.self="close"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
                @click.stop
            >
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Add unit to floor plan</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Units at {{ locationName }} or from other locations
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="close">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <div class="space-y-3 border-b border-slate-100 px-5 py-3 dark:border-slate-700">
                    <div>
                        <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="opt in unitStatusOptions"
                                :key="opt.id"
                                type="button"
                                class="rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                                :class="selectedStatusIds.includes(opt.id)
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300'"
                                @click="toggleStatus(opt.id)"
                            >
                                {{ opt.name }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-600 dark:bg-slate-900/50">
                            <button
                                type="button"
                                class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                                :class="scopeButtonClass('all')"
                                @click="scope = 'all'"
                            >All</button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                                :class="scopeButtonClass('at_location')"
                                @click="scope = 'at_location'"
                            >At this location</button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                                :class="scopeButtonClass('other')"
                                @click="scope = 'other'"
                            >Other locations</button>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search HIN, serial, asset…"
                            class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>
                </div>

                <div v-if="pendingUnit" class="border-b border-amber-200 bg-amber-50 px-5 py-3 dark:border-amber-800 dark:bg-amber-900/20">
                    <p class="text-sm text-amber-900 dark:text-amber-100">
                        <strong>{{ pendingUnit.display_name }}</strong> is at
                        {{ pendingUnit.current_location_name ?? 'another location' }}.
                    </p>
                    <label class="mt-2 flex items-center gap-2 text-sm text-amber-800 dark:text-amber-200">
                        <input v-model="confirmTransfer" type="checkbox" class="rounded border-amber-400" />
                        Transfer unit to {{ locationName }} when adding
                    </label>
                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            :disabled="submitting"
                            class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            @click="confirmPendingAdd"
                        >
                            {{ submitting ? 'Adding…' : 'Add to layout' }}
                        </button>
                        <button type="button" class="text-sm text-slate-600 hover:underline dark:text-slate-300" @click="pendingUnit = null">
                            Cancel
                        </button>
                    </div>
                </div>

                <div class="min-h-[200px] flex-1 overflow-y-auto">
                    <p v-if="loading" class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">Loading units…</p>
                    <p v-else-if="errorMsg" class="p-6 text-center text-sm text-red-600 dark:text-red-400">{{ errorMsg }}</p>
                    <p v-else-if="!filteredUnits.length" class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        No units match your filters.
                    </p>
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                        <li
                            v-for="unit in filteredUnits"
                            :key="unit.asset_unit_id"
                            class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                    {{ unit.display_name }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ unit.unit_label ?? '—' }}
                                    · {{ statusLabel(unit.status) }}
                                    · {{ unit.length_ft }}×{{ unit.width_ft }} ft
                                </p>
                                <p
                                    v-if="!unit.is_at_location"
                                    class="mt-1 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    At: {{ unit.current_location_name ?? 'Unassigned' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :disabled="submitting"
                                class="shrink-0 rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300"
                                @click="beginAdd(unit)"
                            >
                                Add
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </Teleport>
</template>
