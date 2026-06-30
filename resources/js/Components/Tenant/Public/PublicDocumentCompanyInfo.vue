<script setup>
import { computed } from 'vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import {
    locationBlockFromObject,
    resolvePreviewLocation,
    resolvePreviewSubsidiary,
} from '@/Utils/documentPreviewLetterhead';

const props = defineProps({
    record: { type: Object, default: null },
    /** Override subsidiary (e.g. consignment uses asset unit subsidiary). */
    subsidiary: { type: Object, default: null },
    /** Override location; when omitted, resolved from record. */
    location: { type: Object, default: null },
    fallbackName: { type: String, default: 'Company Name' },
    formatPhone: { type: Boolean, default: true },
    dark: { type: Boolean, default: false },
    headingClass: { type: String, default: '' },
});

const effectiveHeadingClass = computed(() => {
    if (props.headingClass) {
        return props.headingClass;
    }

    return [
        'text-xl font-bold break-words sm:text-2xl',
        props.dark ? 'text-white' : 'text-gray-900',
    ].join(' ');
});

const textMutedClass = computed(() =>
    props.dark ? 'text-gray-300' : 'text-gray-600',
);

const companyName = computed(() => {
    const sub = props.subsidiary ?? (props.record ? resolvePreviewSubsidiary(props.record) : null);
    const name = sub?.display_name?.trim();
    return name || props.fallbackName;
});

const locationBlock = computed(() => {
    const loc = props.location
        ?? (props.record ? resolvePreviewLocation(props.record) : null);

    return locationBlockFromObject(loc);
});

const displayPhone = computed(() => {
    const raw = locationBlock.value?.phone;
    if (!raw) {
        return '';
    }

    return props.formatPhone ? formatPhoneNumber(raw) : raw;
});
</script>

<template>
    <h1 :class="effectiveHeadingClass">{{ companyName }}</h1>
    <slot name="after-title" />
    <div
        v-if="locationBlock"
        class="mt-2 space-y-1 text-sm"
        :class="textMutedClass"
    >
        <p v-if="locationBlock.line1">
            {{ locationBlock.line1 }}<span v-if="locationBlock.line2">, {{ locationBlock.line2 }}</span>
        </p>
        <p v-if="locationBlock.city">
            {{ locationBlock.city }}<span v-if="locationBlock.state">, {{ locationBlock.state }}</span>
            <template v-if="locationBlock.postal"> {{ locationBlock.postal }}</template>
        </p>
        <p v-if="displayPhone" class="flex items-center gap-1 break-all">
            <span class="material-icons shrink-0 text-sm">phone</span>
            {{ displayPhone }}
        </p>
        <p v-if="locationBlock.email" class="flex items-center gap-1 break-all">
            <span class="material-icons shrink-0 text-sm">email</span>
            {{ locationBlock.email }}
        </p>
    </div>
</template>
