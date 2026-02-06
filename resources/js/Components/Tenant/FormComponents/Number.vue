<script setup>
import { ref, watch } from 'vue';

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
        default: '0'
    },
    min: {
        type: Number,
        default: null
    },
    max: {
        type: Number,
        default: null
    },
    step: {
        type: [Number, String],
        default: 1
    },
    allowDecimals: {
        type: Boolean,
        default: true
    },
    isYear: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const inputRef = ref(null);
const displayValue = ref('');

// Format number with commas for display
const formatNumber = (value) => {
    if (value === null || value === undefined || value === '') return '';
    
    // If it's a year field, don't format with commas
    if (props.isYear) {
        return String(value);
    }
    
    // Convert to string to handle decimals properly
    const strValue = String(value);
    const parts = strValue.split('.');
    
    // Add commas to integer part
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    
    return parts.join('.');
};

// Remove formatting to get raw number
const unformatNumber = (value) => {
    if (value === null || value === undefined || value === '') return null;
    return String(value).replace(/,/g, '');
};

// Initialize display value
watch(() => props.modelValue, (newValue) => {
    // Only update display if input is not focused
    if (document.activeElement !== inputRef.value) {
        displayValue.value = formatNumber(newValue);
    }
}, { immediate: true });

const handleInput = (event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const oldValue = input.value;
    
    // Remove commas to get raw input
    const unformatted = unformatNumber(oldValue);
    
    // If empty, emit null
    if (unformatted === null || unformatted === '') {
        displayValue.value = '';
        emit('update:modelValue', null);
        return;
    }
    
    // Validate: only allow numbers and decimal point
    let cleaned = unformatted;
    if (props.isYear) {
        // Year field: only allow digits, max 4 characters
        cleaned = cleaned.replace(/\D/g, '');
        if (cleaned.length > 4) {
            cleaned = cleaned.slice(0, 4);
        }
    } else if (props.allowDecimals) {
        // Allow digits and single decimal point
        cleaned = cleaned.replace(/[^\d.]/g, '');
        const parts = cleaned.split('.');
        if (parts.length > 2) {
            cleaned = parts[0] + '.' + parts.slice(1).join('');
        }
    } else {
        // Only allow digits
        cleaned = cleaned.replace(/\D/g, '');
    }
    
    // Update the underlying value (store as number or null)
    let numValue;
    if (props.isYear || !props.allowDecimals) {
        numValue = parseInt(cleaned, 10);
    } else {
        numValue = parseFloat(cleaned);
    }
    emit('update:modelValue', isNaN(numValue) ? null : numValue);
    
    // Format for display
    const formatted = formatNumber(cleaned);
    displayValue.value = formatted;
    input.value = formatted;
    
    // Restore cursor position
    // Count digits (and decimal) before cursor in old value
    let significantCharsBeforeCursor = 0;
    for (let i = 0; i < cursorPosition; i++) {
        if (/[\d.]/.test(oldValue[i])) {
            significantCharsBeforeCursor++;
        }
    }
    
    // Find new position in formatted value
    let newPosition = 0;
    let charsEncountered = 0;
    for (let i = 0; i < formatted.length; i++) {
        if (/[\d.]/.test(formatted[i])) {
            charsEncountered++;
        }
        if (charsEncountered === significantCharsBeforeCursor) {
            newPosition = i + 1;
            break;
        }
    }
    
    if (significantCharsBeforeCursor === 0) newPosition = 0;
    
    setTimeout(() => {
        input.setSelectionRange(newPosition, newPosition);
    }, 0);
};

const handleBlur = () => {
    // Validate min/max bounds and format
    if (props.modelValue !== null && props.modelValue !== undefined) {
        let value = props.modelValue;
        
        // Apply min constraint
        if (props.min !== null && value < props.min) {
            value = props.min;
            emit('update:modelValue', value);
        }
        
        // Apply max constraint
        if (props.max !== null && value > props.max) {
            value = props.max;
            emit('update:modelValue', value);
        }
        
        // Format for display
        displayValue.value = formatNumber(value);
    } else {
        displayValue.value = '';
    }
};

const handleFocus = () => {
    // Optional: You can add focus behavior here if needed
};
</script>

<template>
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
        :placeholder="placeholder"
        class="input-style"
    />
</template>
