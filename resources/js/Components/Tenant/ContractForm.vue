<script setup>
import Form from '@/Components/Tenant/Form.vue';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit', 'show'].includes(v),
    },
    initialData: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['cancel']);

const formMode = computed(() => {
    if (props.mode === 'show') {
        return 'view';
    }
    if (props.mode === 'create') {
        return 'create';
    }
    return 'edit';
});

const statusEnumKey = 'App\\Enums\\Contract\\ContractStatus';
const paymentEnumKey = 'App\\Enums\\Contract\\ContractPaymentStatus';

const normalizedRecord = computed(() => {
    if (!props.record) {
        return null;
    }
    const r = { ...props.record };
    const statusOpts = props.enumOptions[statusEnumKey] || [];
    const payOpts = props.enumOptions[paymentEnumKey] || [];

    if (r.status != null && r.status !== '') {
        if (typeof r.status === 'string') {
            const opt = statusOpts.find((o) => o.value === r.status);
            if (opt) {
                r.status = opt.id;
            }
        }
    }

    if (r.payment_status != null && r.payment_status !== '') {
        if (typeof r.payment_status === 'string') {
            const opt = payOpts.find((o) => o.value === r.payment_status);
            if (opt) {
                r.payment_status = opt.id;
            }
        }
    }

    return r;
});
</script>

<template>
    <Form
        :schema="formSchema"
        :fields-schema="fieldsSchema"
        :record="normalizedRecord"
        record-type="contracts"
        record-title="Contract"
        :enum-options="enumOptions"
        :timezones="timezones"
        :mode="formMode"
        :initial-data="initialData"
        @cancel="$emit('cancel')"
    />
</template>
