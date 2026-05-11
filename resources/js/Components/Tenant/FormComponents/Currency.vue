<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: null
    },
    id: {
        type: String,
        default: null
    },
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    placeholder: {
        type: String,
        default: '$0.00'
    },
    min: {
        type: Number,
        default: null
    },
    max: {
        type: Number,
        default: null
    },
    /** Where to show the currency icon; `'none'` removes icon and extra horizontal padding. */
    iconPosition: {
        type: String,
        default: 'left',
        validator: (v) => ['left', 'right', 'none'].includes(v),
    },
});

const emit = defineEmits(['update:modelValue']);

/** When an attach_money icon is shown, omit `$` inside the field to avoid double dollar signs. */
const showDollarPrefixInField = computed(() => props.iconPosition === 'none');

const inputPaddingClass = computed(() => {
    if (props.iconPosition === 'none') {
        return '';
    }

    return props.iconPosition === 'right' ? 'pr-10' : '!pl-8';
});

const resolvedPlaceholder = computed(() => {
    if (props.placeholder !== '$0.00') {
        return props.placeholder;
    }

    return showDollarPrefixInField.value ? '$0.00' : '0.00';
});

const inputRef = ref(null);
const displayValue = ref('');

// Format number for display (optional leading $ when no currency icon)
const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') return '';

    const numValue = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(numValue)) return '';

    // Format with commas and 2 decimal places
    const formatted = numValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return showDollarPrefixInField.value ? `$${formatted}` : formatted;
};

// Parse currency string to number
const parseCurrency = (value) => {
    if (!value) return null;
    // Remove dollar sign, commas, and any whitespace
    const cleaned = String(value).replace(/[$,\s]/g, '');
    const parsed = parseFloat(cleaned);
    return isNaN(parsed) ? null : parsed;
};

// Initialize display value
watch(() => props.modelValue, (newValue) => {
    if (document.activeElement !== inputRef.value) {
        displayValue.value = formatCurrency(newValue);
    }
}, { immediate: true });

const handleInput = (event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const oldValue = input.value;

    // Remove everything except digits and decimal point
    let cleaned = oldValue.replace(/[^0-9.]/g, '');

    // Ensure only one decimal point
    const parts = cleaned.split('.');
    if (parts.length > 2) {
        cleaned = parts[0] + '.' + parts.slice(1).join('');
    }

    // Limit to 2 decimal places
    if (parts.length === 2 && parts[1].length > 2) {
        cleaned = parts[0] + '.' + parts[1].substring(0, 2);
    }

    // Update the underlying value
    const numValue = parseCurrency(cleaned);
    emit('update:modelValue', numValue);

    // Format for display (no `$` prefix when attach_money icon is visible)
    const formatted = cleaned
        ? showDollarPrefixInField.value
            ? `$${cleaned}`
            : cleaned
        : '';
    displayValue.value = formatted;
    input.value = formatted;

    // Restore cursor position
    let newPosition = cursorPosition;
    if (oldValue.length < formatted.length) {
        newPosition = cursorPosition + (formatted.length - oldValue.length);
    }

    setTimeout(() => {
        input.setSelectionRange(newPosition, newPosition);
    }, 0);
};

const handleBlur = () => {
    if (props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '') {
        displayValue.value = formatCurrency(props.modelValue);
    } else {
        displayValue.value = '';
    }
};

const handleFocus = (event) => {
    // On focus, if value is zero, clear it for easier input
    const zeroPlain = '0.00';
    const zeroWithSymbol = '$0.00';
    if (displayValue.value === zeroWithSymbol || displayValue.value === zeroPlain) {
        displayValue.value = showDollarPrefixInField.value ? '$' : '';
        setTimeout(() => {
            const pos = showDollarPrefixInField.value ? 1 : 0;
            event.target.setSelectionRange(pos, pos);
        }, 0);
    }
};
</script>

<template>
    <div class="relative">
        <input
            ref="inputRef"
            :id="id"
            type="text"
            inputmode="decimal"
            v-model="displayValue"
            @input="handleInput"
            @blur="handleBlur"
            @focus="handleFocus"
            :required="required"
            :disabled="disabled"
            :placeholder="resolvedPlaceholder"
            :class="['input-style', inputPaddingClass]"
        />
        <div
            v-if="iconPosition !== 'none'"
            class="pointer-events-none absolute inset-y-0 flex items-center"
            :class="iconPosition === 'right' ? 'right-0 pr-3' : 'left-0 pl-3'"
        >
            <span class="material-icons text-[20px] leading-none text-gray-500 dark:text-gray-400">attach_money</span>
        </div>
    </div>
</template>

<style scoped>

</style>
