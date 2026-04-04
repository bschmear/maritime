<script setup>
import { computed, ref } from 'vue';
import Form from '@/Components/Tenant/Form.vue';

const props = defineProps({
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    recordType: { type: String, default: '' },
    recordTitle: { type: String, default: '' },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    mode: {
        type: String,
        default: 'view',
        validator: (v) => ['view', 'edit', 'create'].includes(v),
    },
    preventRedirect: { type: Boolean, default: false },
    formId: { type: String, default: null },
    imageUrls: { type: Object, default: () => ({}) },
    initialData: { type: Object, default: () => ({}) },
    recordIdentifier: { type: [String, Number], default: null },
    extraRouteParams: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    specsContextAssetType: { type: Number, default: null },
});

defineEmits(['submit', 'cancel', 'created', 'updated']);

const formRef = ref(null);

const showAssetHints = computed(() =>
    props.recordType === 'assets' && (props.mode === 'create' || props.mode === 'edit'),
);

const isProcessing = computed(() => formRef.value?.isProcessing ?? false);

const submitForm = () => formRef.value?.submitForm?.();
const cancelForm = () => formRef.value?.cancelForm?.();

defineExpose({
    submitForm,
    cancelForm,
    isProcessing,
});
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="showAssetHints"
            class="rounded-lg border border-primary-200 bg-primary-50/80 px-4 py-3 text-sm text-primary-900 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-100"
            role="note"
        >
            <p class="font-medium">How assets, variants, and specs work</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-primary-800/90 dark:text-primary-200/90">
                <li>Turn on <strong>This asset has variants</strong> only when each sellable configuration should have its own specifications. Variants then carry <strong>Asset specs</strong>; this asset record does not.</li>
                <li>Leave variants off when the product has a single spec profile: specifications are stored on the <strong>asset</strong> (aligned with the selected asset type).</li>
                <li>With variants enabled, add <strong>units</strong> from the Units sublist and assign a variant on each unit when required.</li>
            </ul>
        </div>

        <Form
            ref="formRef"
            :schema="schema"
            :fields-schema="fieldsSchema"
            :record="record"
            :record-type="recordType"
            :record-title="recordTitle"
            :enum-options="enumOptions"
            :timezones="timezones"
            :mode="mode"
            :prevent-redirect="preventRedirect"
            :form-id="formId"
            :image-urls="imageUrls"
            :initial-data="initialData"
            :record-identifier="recordIdentifier"
            :extra-route-params="extraRouteParams"
            :available-specs="availableSpecs"
            :specs-context-asset-type="specsContextAssetType"
            @submit="$emit('submit', $event)"
            @cancel="$emit('cancel', $event)"
            @created="$emit('created', $event)"
            @updated="$emit('updated', $event)"
        />
    </div>
</template>
