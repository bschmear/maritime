<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    mode: { type: String, default: 'create' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['cancel']);

const statusEnumKey = 'App\\Enums\\WarrantyClaim\\Status';
const statusOptions = computed(() => props.enumOptions[statusEnumKey] || []);

const merged = props.record ?? {};
const rawStatus = merged.status?.value ?? merged.status ?? 'draft';

const form = useForm({
    vendor_id: merged.vendor_id ?? null,
    work_order_id: merged.work_order_id ?? null,
    invoice_id: merged.invoice_id ?? null,
    claim_number: merged.claim_number ?? '',
    status: rawStatus,
    notes: merged.notes ?? '',
    rejection_reason: merged.rejection_reason ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('warrantyclaims.store'));
    } else if (props.record?.id != null) {
        form.put(route('warrantyclaims.update', props.record.id));
    }
};

const fieldOr = (key, fallback) => props.fieldsSchema[key] ?? fallback;
</script>

<template>
    <form class="mx-auto w-full max-w-4xl space-y-6" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <RecordSelect
                    :id="`warranty-claim-vendor`"
                    v-model="form.vendor_id"
                    :field="fieldOr('vendor_id', { type: 'record', typeDomain: 'Vendor', label: 'Manufacturer (vendor)' })"
                    :enum-options="enumOptions.vendor_id ?? []"
                />
            </div>
            <div>
                <RecordSelect
                    :id="`warranty-claim-invoice`"
                    v-model="form.invoice_id"
                    :field="fieldOr('invoice_id', { type: 'record', typeDomain: 'Invoice', label: 'Invoice' })"
                    :enum-options="enumOptions.invoice_id ?? []"
                />
            </div>
            <div>
                <RecordSelect
                    :id="`warranty-claim-work-order`"
                    v-model="form.work_order_id"
                    :field="fieldOr('work_order_id', { type: 'record', typeDomain: 'WorkOrder', label: 'Work order' })"
                    :enum-options="enumOptions.work_order_id ?? []"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="claim_number">Claim #</label>
                <input
                    id="claim_number"
                    v-model="form.claim_number"
                    type="text"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="status">Status</label>
                <select
                    id="status"
                    v-model="form.status"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    <option
                        v-for="opt in statusOptions"
                        :key="String(opt.value ?? opt.id)"
                        :value="opt.value ?? opt.id"
                    >
                        {{ opt.name }}
                    </option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="notes">Notes</label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="rejection_reason">Rejection reason</label>
                <textarea
                    id="rejection_reason"
                    v-model="form.rejection_reason"
                    rows="3"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>
        </div>

        <div v-if="Object.keys(form.errors || {}).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
            <p v-for="(errs, key) in form.errors" :key="key">{{ key }}: {{ Array.isArray(errs) ? errs.join(', ') : errs }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button
                type="submit"
                class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ mode === 'create' ? 'Create claim' : 'Save changes' }}
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                @click="emit('cancel')"
            >
                Cancel
            </button>
        </div>
    </form>
</template>
