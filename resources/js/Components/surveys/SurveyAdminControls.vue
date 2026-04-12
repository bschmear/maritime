<script setup>
import { ref, computed, watch, getCurrentInstance } from 'vue';
import axios from 'axios';

const props = defineProps({
    surveyId: { type: String, required: true },
    editUrl: { type: String, required: true },
    analyticsUrl: { type: String, required: true },
    updateRoute: { type: String, required: true },
    /** When false, only show current color swatch (read-only). */
    canCustomizeColors: { type: Boolean, default: true },
    initialColorScheme: { type: String, default: 'default' },
    initialCustomColor: { type: String, default: '#14c2ad' },
    defaultColor: { type: String, default: '#14c2ad' },
    teamColor: { type: String, default: null },
    currentColor: { type: String, default: '#14c2ad' },
    embed: { type: Boolean, default: false },
});

const emit = defineEmits(['preview']);

const showColorPicker = ref(false);
const colorScheme = ref(normalizeScheme(props.initialColorScheme));
const customColor = ref(props.initialCustomColor || props.defaultColor);
const originalScheme = ref(normalizeScheme(props.initialColorScheme));
const originalCustom = ref(props.initialCustomColor || props.defaultColor);
const updating = ref(false);
const saveError = ref('');

function normalizeScheme(s) {
    const v = s === 'team' || s === 'custom' || s === 'default' ? s : 'default';
    return v;
}

const previewEffectiveColor = computed(() => {
    if (colorScheme.value === 'custom') {
        return customColor.value || props.defaultColor;
    }
    if (colorScheme.value === 'team' && props.teamColor) {
        return props.teamColor;
    }
    return props.defaultColor;
});

const hasChanges = computed(
    () =>
        colorScheme.value !== originalScheme.value ||
        customColor.value !== originalCustom.value,
);

function toast(type, message) {
    const inst = getCurrentInstance();
    const fn = inst?.proxy?.$toast;
    if (typeof fn === 'function') {
        fn(type, message);
        return;
    }
    const root = inst?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, message);
    }
}

watch(
    previewEffectiveColor,
    (c) => {
        emit('preview', c);
    },
    { immediate: true },
);

watch(
    () => [props.initialColorScheme, props.initialCustomColor],
    ([scheme, custom]) => {
        colorScheme.value = normalizeScheme(scheme);
        customColor.value = custom || props.defaultColor;
        originalScheme.value = normalizeScheme(scheme);
        originalCustom.value = custom || props.defaultColor;
    },
);

function cancelChanges() {
    saveError.value = '';
    colorScheme.value = originalScheme.value;
    customColor.value = originalCustom.value;
}

async function saveColor() {
    if (updating.value || !hasChanges.value) return;
    updating.value = true;
    saveError.value = '';
    try {
        const response = await axios.put(
            props.updateRoute,
            {
                survey_id: props.surveyId,
                color_scheme: colorScheme.value,
                custom_color: colorScheme.value === 'custom' ? customColor.value : null,
            },
            { headers: { Accept: 'application/json' } },
        );
        if (response.data?.success) {
            originalScheme.value = colorScheme.value;
            originalCustom.value = customColor.value;
            const eff = response.data.data?.effective_color;
            if (eff) {
                emit('preview', eff);
            }
            toast('success', response.data.message || 'Saved.');
            setTimeout(() => window.location.reload(), 900);
        }
    } catch (e) {
        console.error(e);
        const msg =
            e.response?.data?.message ||
            e.response?.data?.errors?.custom_color?.[0] ||
            'Failed to update survey color.';
        saveError.value = msg;
        toast('error', msg);
    } finally {
        updating.value = false;
    }
}
</script>

<template>
    <div
        class="z-[100]"
        :class="embed ? 'relative mb-4' : 'fixed right-4 top-4 max-w-md'"
    >
        <div
            class="rounded-lg border-2 border-blue-500 bg-white p-4 shadow-xl dark:border-blue-500 dark:bg-gray-800"
        >
            <div class="mb-3 flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="material-icons shrink-0 text-blue-600 dark:text-blue-400">shield</span>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Survey controls</h4>
                </div>
                <button
                    v-if="canCustomizeColors"
                    type="button"
                    class="shrink-0 rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    aria-label="Toggle color options"
                    @click="showColorPicker = !showColorPicker"
                >
                    <span class="material-icons text-[20px]">palette</span>
                </button>
            </div>

            <div class="space-y-1">
                <a
                    :href="editUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <span class="material-icons mr-2 text-[18px] text-gray-500 dark:text-gray-400">edit</span>
                    Edit survey
                </a>
                <a
                    :href="analyticsUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <span class="material-icons mr-2 text-[18px] text-gray-500 dark:text-gray-400">bar_chart</span>
                    View analytics
                </a>

                <div v-if="canCustomizeColors">
                    <div
                        v-show="showColorPicker"
                        class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700"
                    >
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Quick color</p>

                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="colorScheme"
                                    type="radio"
                                    value="default"
                                    class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="flex-1 text-xs text-gray-700 dark:text-gray-300">Default brand</span>
                                <div
                                    class="h-6 w-6 shrink-0 rounded border border-gray-300 dark:border-gray-600"
                                    :style="{ backgroundColor: defaultColor }"
                                />
                            </label>

                            <label v-if="teamColor" class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="colorScheme"
                                    type="radio"
                                    value="team"
                                    class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="flex-1 text-xs text-gray-700 dark:text-gray-300">Account / team color</span>
                                <div
                                    class="h-6 w-6 shrink-0 rounded border border-gray-300 dark:border-gray-600"
                                    :style="{ backgroundColor: teamColor }"
                                />
                            </label>

                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="colorScheme"
                                    type="radio"
                                    value="custom"
                                    class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-xs text-gray-700 dark:text-gray-300">Custom</span>
                            </label>
                        </div>

                        <div v-show="colorScheme === 'custom'" class="flex items-center gap-2">
                            <input
                                v-model="customColor"
                                type="color"
                                class="h-10 w-10 cursor-pointer rounded border border-gray-300 dark:border-gray-600"
                            />
                            <input
                                v-model="customColor"
                                type="text"
                                class="flex-1 rounded border border-gray-300 px-2 py-1 font-mono text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                :placeholder="defaultColor"
                            />
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                :disabled="updating || !hasChanges"
                                @click="cancelChanges"
                            >
                                <span class="material-icons mr-1 align-middle text-[14px]">close</span>
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md bg-blue-600 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="updating || !hasChanges"
                                @click="saveColor"
                            >
                                <span
                                    v-if="updating"
                                    class="material-icons mr-1 align-middle text-[14px] animate-spin"
                                >sync</span>
                                <span
                                    v-else
                                    class="material-icons mr-1 align-middle text-[14px]"
                                >save</span>
                                {{ updating ? 'Saving…' : 'Save' }}
                            </button>
                        </div>
                        <p v-if="saveError" class="text-xs text-red-600 dark:text-red-400">{{ saveError }}</p>
                    </div>
                </div>

                <div
                    v-else
                    class="space-y-2 border-t border-gray-200 pt-3 dark:border-gray-700"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400">Current color</p>
                    <div class="flex items-center gap-2">
                        <div
                            class="h-8 w-8 rounded border-2 border-gray-300 dark:border-gray-600"
                            :style="{ backgroundColor: currentColor }"
                        />
                        <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ currentColor }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
