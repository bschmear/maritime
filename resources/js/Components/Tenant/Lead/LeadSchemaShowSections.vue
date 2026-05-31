<script setup>
import { computed } from 'vue';
import {
    useSchemaFormGroups,
    useRecordFieldDisplay,
    resolveRecordFieldRaw,
} from '@/composables/useSchemaFormGroups.js';

const props = defineProps({
    record: { type: Object, required: true },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
});

const { formGroups } = useSchemaFormGroups(() => props.formSchema);
const resolvedFieldsSchema = computed(() => {
    const fs = props.fieldsSchema;
    if (fs?.fields && typeof fs.fields === 'object' && !Array.isArray(fs.fields)) {
        return fs.fields;
    }
    return fs && typeof fs === 'object' ? fs : {};
});

const { fieldDef, displayFor } = useRecordFieldDisplay(
    () => props.record,
    resolvedFieldsSchema,
    () => props.enumOptions,
);

const hasAddressLines = computed(() => {
    const keys = ['address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country'];
    return keys.some((key) => hasFieldValue(resolveRecordFieldRaw(props.record, key)));
});

function hasFieldValue(value) {
    return value !== null && value !== undefined && value !== '';
}

const fieldIsVisible = (field) => {
    if (!field?.conditional) {
        return true;
    }
    const { key, value } = field.conditional;
    const current = resolveRecordFieldRaw(props.record, key);
    if (value === true) {
        return current === true || current === 1 || current === '1';
    }
    return current == value;
};

const linkHref = (fieldKey, display) => {
    if (display.empty) {
        return null;
    }
    const raw = display.raw ?? props.record?.[fieldKey];
    const type = fieldDef(fieldKey).type;
    if (type === 'email' && raw) {
        return `mailto:${raw}`;
    }
    if (type === 'tel' && raw) {
        return `tel:${raw}`;
    }
    return null;
};
</script>

<template>
    <div class="space-y-4">
        <section
            v-for="group in formGroups"
            :key="group.key"
            class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden"
        >
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ group.label }}
                </span>
            </div>

            <div v-if="group.is_address" class="px-5 py-4">
                <div v-if="hasAddressLines" class="flex items-start gap-3">
                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">location_on</span>
                    <div class="min-w-0 text-sm text-gray-900 dark:text-white leading-relaxed">
                        <p v-if="resolveRecordFieldRaw(record, 'address_line_1')">
                            {{ resolveRecordFieldRaw(record, 'address_line_1') }}
                        </p>
                        <p v-if="resolveRecordFieldRaw(record, 'address_line_2')">
                            {{ resolveRecordFieldRaw(record, 'address_line_2') }}
                        </p>
                        <p
                            v-if="resolveRecordFieldRaw(record, 'city')
                                || resolveRecordFieldRaw(record, 'state')
                                || resolveRecordFieldRaw(record, 'postal_code')"
                        >
                            {{
                                [
                                    resolveRecordFieldRaw(record, 'city'),
                                    resolveRecordFieldRaw(record, 'state'),
                                    resolveRecordFieldRaw(record, 'postal_code'),
                                ].filter(Boolean).join(', ')
                            }}
                        </p>
                        <p v-if="resolveRecordFieldRaw(record, 'country')">
                            {{ resolveRecordFieldRaw(record, 'country') }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
            </div>

            <div
                v-else-if="group.key === 'notes'"
                class="px-5 py-4 flex items-start gap-3"
            >
                <span class="material-icons text-[18px] text-gray-400 mt-0.5 shrink-0">notes</span>
                <p
                    v-if="resolveRecordFieldRaw(record, 'notes')"
                    class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed"
                >{{ resolveRecordFieldRaw(record, 'notes') }}</p>
                <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
            </div>

            <div
                v-else
                class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-50 dark:divide-gray-700/60"
            >
                <div
                    v-for="field in group.fields.filter(fieldIsVisible)"
                    :key="field.key"
                    class="px-5 py-3.5 flex items-start gap-3 border-b border-gray-50 dark:border-gray-700/60 sm:border-b-0"
                    :class="fieldDef(field.key).type === 'textarea' ? 'sm:col-span-2' : ''"
                >
                        <span class="material-icons text-[18px] text-gray-400 shrink-0 mt-0.5">label</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">
                                {{ fieldDef(field.key).label || field.key }}
                            </p>
                            <a
                                v-if="linkHref(field.key, displayFor(field.key))"
                                :href="linkHref(field.key, displayFor(field.key))"
                                class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline break-all"
                            >{{ displayFor(field.key).text }}</a>
                            <p
                                v-else
                                class="text-sm font-medium whitespace-pre-wrap"
                                :class="displayFor(field.key).empty
                                    ? 'text-gray-300 dark:text-gray-600'
                                    : 'text-gray-900 dark:text-white'"
                            >{{ displayFor(field.key).text }}</p>
                        </div>
                </div>
            </div>
        </section>
    </div>
</template>
