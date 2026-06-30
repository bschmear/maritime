<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    optionLabel: { type: String, default: '' },
    catalogDefaultPrice: { type: Number, default: 0 },
    currentPrice: { type: Number, default: 0 },
    formatPrice: { type: Function, required: true },
});

const emit = defineEmits(['close', 'save']);

const draftPrice = ref(0);

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const catalogDefault = computed(() => roundMoney(props.catalogDefaultPrice));
const isCustom = computed(() => roundMoney(draftPrice.value) !== catalogDefault.value);

watch(
    () => props.show,
    (show) => {
        if (show) {
            draftPrice.value = roundMoney(props.currentPrice);
        }
    },
);

function adjustByPercent(percent) {
    const base = roundMoney(draftPrice.value);
    draftPrice.value = roundMoney(base * (1 + percent / 100));
}

function resetToDefault() {
    draftPrice.value = catalogDefault.value;
}

function save() {
    emit('save', roundMoney(draftPrice.value));
}

function close() {
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-950/60 p-4 backdrop-blur-sm"
            @click.self="close"
        >
            <div
                class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                role="dialog"
                aria-modal="true"
                aria-labelledby="asset-option-price-title"
            >
                <h2 id="asset-option-price-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                    Custom option price
                </h2>
                <p v-if="optionLabel" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ optionLabel }}</p>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Catalog default</dt>
                        <dd class="font-medium tabular-nums text-gray-800 dark:text-gray-200">
                            {{ formatPrice(catalogDefault) }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="asset-option-line-price">
                        Line price on this estimate
                    </label>
                    <input
                        id="asset-option-line-price"
                        v-model.number="draftPrice"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input-style mt-1 w-full"
                    />
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="adjustByPercent(-10)"
                    >
                        −10%
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="adjustByPercent(-5)"
                    >
                        −5%
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="adjustByPercent(5)"
                    >
                        +5%
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="adjustByPercent(10)"
                    >
                        +10%
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="resetToDefault"
                    >
                        Reset to default
                    </button>
                </div>

                <p v-if="isCustom" class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                    This price is locked to this line and will not change if the catalog default is updated later.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="close"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="save"
                    >
                        Save price
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
