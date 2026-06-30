<script setup>
import { computed } from 'vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import { previewLocationPhone } from '@/Utils/documentPreviewLetterhead';

const props = defineProps({
    record: { type: Object, default: null },
    phone: { type: String, default: null },
    accountPhone: { type: String, default: null },
    formatPhone: { type: Boolean, default: true },
    wrapperClass: {
        type: String,
        default: 'bg-gray-900 px-4 py-4 text-center text-xs text-white sm:px-8 print:px-0',
    },
});

const resolvedPhone = computed(() => {
    if (props.phone) {
        return props.phone;
    }

    if (props.record) {
        return previewLocationPhone(props.record, props.accountPhone);
    }

    return props.accountPhone || null;
});

const phoneDisplay = computed(() => {
    const raw = resolvedPhone.value;
    if (!raw) {
        return null;
    }

    return props.formatPhone ? formatPhoneNumber(raw) : raw;
});
</script>

<template>
    <div :class="wrapperClass">
        <p>Thank you for your business!</p>
        <p v-if="phoneDisplay" class="mt-1">
            Questions? Call us at {{ phoneDisplay }}
        </p>
        <slot />
    </div>
</template>
