<script setup>
import { ref, watch, computed } from 'vue';
import { mmToImperialParts, imperialFeetInchesToMm } from '@/utils/measurementMm.js';

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    id: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const footOptions = computed(() => {
    const o = [{ value: '', label: '—' }];
    for (let f = 0; f < 500; f += 1) {
        o.push({ value: f, label: `${f} ft` });
    }
    return o;
});
const inchOptions = computed(() => {
    const o = [{ value: '', label: '—' }];
    for (let i = 0; i < 12; i += 1) {
        o.push({ value: i, label: `${i} in` });
    }
    return o;
});

const feet = ref('');
const inches = ref('');

function applyFromModel(mm) {
    const n = mm == null || mm === '' ? null : Number(mm);
    if (n == null || !Number.isFinite(n) || n <= 0) {
        feet.value = '';
        inches.value = '';
        return;
    }
    const p = mmToImperialParts(n);
    if (!p) {
        feet.value = '';
        inches.value = '';
        return;
    }
    feet.value = p.feet;
    inches.value = p.inches;
}

watch(
    () => props.modelValue,
    (v) => applyFromModel(v),
    { immediate: true },
);

function onChange() {
    const v = imperialFeetInchesToMm(feet.value, inches.value);
    emit('update:modelValue', v);
}
</script>

<template>
    <div
        :id="id"
        class="flex flex-wrap items-end gap-2"
    >
        <div class="min-w-[6rem] flex-1 sm:flex-none sm:min-w-[7rem]">
            <label
                :for="id ? id + '-ft' : undefined"
                class="mb-1 block text-xs text-gray-500 dark:text-gray-400"
            >Feet</label>
            <select
                :id="id ? id + '-ft' : undefined"
                v-model="feet"
                :required="required"
                :disabled="disabled"
                class="input-style w-full"
                @change="onChange"
            >
                <option
                    v-for="opt in footOptions"
                    :key="'ft-'+String(opt.value)"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
        </div>
        <div class="min-w-[5rem] flex-1 sm:flex-none sm:min-w-[6rem]">
            <label
                :for="id ? id + '-in' : undefined"
                class="mb-1 block text-xs text-gray-500 dark:text-gray-400"
            >Inches</label>
            <select
                :id="id ? id + '-in' : undefined"
                v-model="inches"
                :required="required"
                :disabled="disabled"
                class="input-style w-full"
                @change="onChange"
            >
                <option
                    v-for="opt in inchOptions"
                    :key="'in-'+String(opt.value)"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
        </div>
    </div>
</template>
