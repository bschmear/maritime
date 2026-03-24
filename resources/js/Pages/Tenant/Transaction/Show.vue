<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const statusEnumKey = 'App\\Enums\\Transaction\\TransactionStatus';

const statusMeta = computed(() => {
    const raw = props.record.status;
    const opts = props.enumOptions[statusEnumKey] || [];
    const opt = opts.find((o) => o.value === raw || o.id === raw);
    if (opt) {
        return { label: opt.name, bgClass: opt.bgClass || 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
    }
    const map = {
        open:      { label: 'Active',    bgClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' },
        active:    { label: 'Active',    bgClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' },
        won:       { label: 'Won',       bgClass: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' },
        lost:      { label: 'Lost',      bgClass: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
        cancelled: { label: 'Cancelled', bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' },
    };
    return map[raw] || { label: raw || '—', bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Transactions', href: route('transactions.index') },
    { label: props.record.title || `Deal #${props.record.sequence}` },
]);

const items = computed(() => props.record.items || []);

const lineBaseTotal = (item) => Number(item.unit_price || 0) * Number(item.quantity || 1);

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const addonPreTaxTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);

const dealTaxRatePercent = () => Number(props.record.tax_rate) || 0;

const taxOnItemBase = (item) => {
    const r = dealTaxRatePercent();
    const taxable = item.taxable !== false && item.taxable !== 0 && item.taxable !== '0';
    if (!taxable || r <= 0) return 0;
    return roundMoney(lineBaseTotal(item) * (r / 100));
};

const taxOnAddon = (addon) => {
    const r = dealTaxRatePercent();
    const taxable = addon.taxable !== false && addon.taxable !== 0 && addon.taxable !== '0';
    if (!taxable || r <= 0) return 0;
    return roundMoney(addonPreTaxTotal(addon) * (r / 100));
};

function formatMoney(amount, currency) {
    if (amount == null || amount === '') return '—';
    const n = Number(amount);
    if (Number.isNaN(n)) return String(amount);
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || props.record.currency || 'USD' }).format(n);
    } catch {
        return `${currency || 'USD'} ${n.toFixed(2)}`;
    }
}

function formatDateTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return iso; }
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch { return iso; }
}

const isWon = computed(() => {
    const raw = props.record.status;
    const opts = props.enumOptions[statusEnumKey] || [];
    const opt = opts.find((o) => o.value === raw || o.id === raw);
    return opt?.value === 'won' || raw === 'won';
});

const isLost = computed(() => {
    const raw = props.record.status;
    const opts = props.enumOptions[statusEnumKey] || [];
    const opt = opts.find((o) => o.value === raw || o.id === raw);
    return opt?.value === 'lost' || raw === 'lost';
});

const deleteTransaction = () => {
    if (confirm('Delete this transaction? This cannot be undone.')) {
        router.delete(route('transactions.destroy', props.record.id));
    }
};

// Related records — build a list of whatever is linked
const relatedRecords = computed(() => {
    const links = [];
    if (props.record.estimate_id) {
        links.push({
            key: 'estimate',
            icon: 'description',
            label: 'Estimate',
            name: props.record.estimate?.display_name || `#${props.record.estimate_id}`,
            href: route('estimates.show', props.record.estimate_id),
            meta: props.record.estimate?.status_label || null,
            amount: props.record.estimate?.total ?? null,
        });
    }
    if (props.record.opportunity_id) {
        links.push({
            key: 'opportunity',
            icon: 'trending_up',
            label: 'Opportunity',
            name: props.record.opportunity?.display_name || `#${props.record.opportunity_id}`,
            href: route('opportunities.show', props.record.opportunity_id),
            meta: props.record.opportunity?.status_label || null,
            amount: props.record.opportunity?.amount ?? null,
        });
    }
    if (props.record.contract_id) {
        links.push({
            key: 'contract',
            icon: 'gavel',
            label: 'Contract',
            name: props.record.contract?.contract_number || `#${props.record.contract_id}`,
            href: route('contracts.show', props.record.contract_id),
            meta: props.record.contract?.status_label || null,
            amount: props.record.contract?.total_amount ?? null,
        });
    }

    return links;
});

// ─── Stepper ──────────────────────────────────────────────────────────────
//
// Estimate status — stored as unsignedTinyInteger (EstimateStatus ids):
//   1=Draft  2=PendingApproval  3=Approved  4=Declined  5=Expired  6=Cancelled
//
// Contract status — stored as string (ContractStatus values):
//   draft | pending_approval | signed | cancelled | expired
//
// Delivery status — stored as string (Deliveries\Status values):
//   scheduled | confirmed | en_route | delivered | cancelled | rescheduled
//
const stepperSteps = computed(() => {
    const steps = [];

    // ── Estimate (shown only when linked) ─────────────────────────────────
    if (props.record.estimate_id) {
        const status = props.record.estimate?.status; // integer id
        const done    = status === 3;                  // Approved
        const pending = status === 2;                  // PendingApproval
        steps.push({
            key:   'estimate',
            label: 'Estimate',
            icon:  'description',
            state: done ? 'complete' : pending ? 'pending' : 'current',
            href:  route('estimates.show', props.record.estimate_id),
        });
    }

    // ── Deal (always complete — you are on this page) ─────────────────────
    // steps.push({
    //     key:   'deal',
    //     label: 'Deal',
    //     icon:  'handshake',
    //     state: 'complete',
    //     href:  null,
    // });

    // ── Contract ──────────────────────────────────────────────────────────
    // Contract has `transaction_id` FK; loaded as hasOne from Transaction.
    const contract       = props.record.contract;
    const contractStatus = contract?.status ?? null;
    if (props.record.needs_contract) {
        const done    = contractStatus === 'signed';
        const pending = ['draft', 'pending_approval'].includes(contractStatus);
        steps.push({
            key:   'contract',
            label: 'Contract',
            icon:  'gavel',
            state: done ? 'complete' : pending ? 'pending' : 'todo',
            href:  contract?.id ? route('contracts.show', contract.id) : null,
        });
    } else {
        steps.push({
            key:   'contract',
            label: 'Contract',
            icon:  'gavel',
            state: contractStatus === 'signed' ? 'complete' : 'optional',
            href:  contract?.id ? route('contracts.show', contract.id) : null,
        });
    }

    steps.push({
        key:   'invoice',
        label: 'Invoice',
        icon:  'receipt_long',
        state: 'todo',
        href:  null,
    });

    // ── Delivery ──────────────────────────────────────────────────────────
    // Deliveries link via asset_unit_id / work_order_id — no direct
    // transaction_id FK exists yet, so state is derived from needs_delivery only.
    if (props.record.needs_delivery) {
        steps.push({
            key:   'delivery',
            label: 'Delivery',
            icon:  'local_shipping',
            state: 'todo',
            href:  null,
        });
    } else {
        steps.push({
            key:   'delivery',
            label: 'Delivery',
            icon:  'local_shipping',
            state: 'optional',
            href:  null,
        });
    }

    // ── Completed (derived) ───────────────────────────────────────────────
    // Deal must be won AND all required downstream steps must be satisfied.
    const dealWon    = props.record.status === 'won' || props.record.status === 2;
    const contractOk = !props.record.needs_contract || contractStatus === 'signed';
    // Delivery verification requires a direct delivery record (not yet linked).
    const deliveryOk = !props.record.needs_delivery;
    steps.push({
        key:   'completed',
        label: 'Completed',
        icon:  'check_circle',
        state: dealWon && contractOk && deliveryOk ? 'complete' : 'todo',
        href:  null,
    });

    return steps;
});

// ─── Grand total (all items + addons, including tax) ──────────────────────
const computedGrandTotal = computed(() => {
    let total = 0;
    for (const item of items.value) {
        total += lineBaseTotal(item) + taxOnItemBase(item);
        for (const addon of (item.addons || [])) {
            total += addonPreTaxTotal(addon) + taxOnAddon(addon);
        }
    }
    return roundMoney(total);
});

// ─── Create contract modal ─────────────────────────────────────────────────
const createContractModal = ref(false);

const contractForm = useForm({
    transaction_id: props.record.id,
    customer_id: props.record.customer_id,
    estimate_id: props.record.estimate_id ?? null,
    total_amount: 0,
    currency: props.record.currency || 'USD',
    notes: '',
    signature_required: true,
});

const openCreateContractModal = () => {
    contractForm.total_amount = computedGrandTotal.value;
    createContractModal.value = true;
};

const submitContract = () => {
    contractForm.post(route('contracts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createContractModal.value = false;
        },
    });
};

// ─── Optional step confirm modal ──────────────────────────────────────────
const confirmModal = ref({ show: false, type: null });

const openOptionalModal = (type) => {
    confirmModal.value = { show: true, type };
};

const confirmAddStep = () => {
    router.patch(route('transactions.update', props.record.id), {
        [confirmModal.value.type === 'contract' ? 'needs_contract' : 'needs_delivery']: true,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            const type = confirmModal.value.type;
            confirmModal.value = { show: false, type: null };
            if (type === 'contract') {
                openCreateContractModal();
            }
        },
    });
};
</script>

<template>
    <Head :title="record.title || `Deal #${record.sequence}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                            {{ record.title || `Deal #${record.sequence}` }}
                        </h2>
                        <!-- <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Deal #{{ record.sequence }}
                            <span v-if="record.uuid" class="ml-2 font-mono text-sm text-gray-400 dark:text-gray-500">{{ record.uuid }}</span>
                        </p> -->
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium" :class="statusMeta.bgClass">
                                {{ statusMeta.label }}
                            </span>
                            <span v-if="record.closed_at" class="text-sm text-gray-500 dark:text-gray-400">
                                Closed {{ formatDateTime(record.closed_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('transactions.index')">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                <span class="material-icons text-base">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        <Link :href="route('transactions.edit', record.id)">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                <span class="material-icons text-base">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" @click="deleteTransaction">
                            <span class="material-icons text-base">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Flash messages -->
        <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ flash.error }}
        </div>

<!-- ─── Deal Progress Stepper ─────────────────────────────────────────── -->
<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-6 py-5 ">
    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Deal Progress</h3>
    <div class="relative flex items-start justify-between">

        <!-- Connector line behind the icons -->
        <div class="absolute top-4 left-0 right-0 h-px bg-gray-200 dark:bg-gray-700 z-0" aria-hidden="true"></div>

        <template v-for="(step, index) in stepperSteps" :key="step.key">
            <div class="relative z-10 flex flex-col items-center text-center"
                 :class="stepperSteps.length <= 4 ? 'w-1/4' : stepperSteps.length === 5 ? 'w-1/5' : 'w-1/6'">

                <!-- Icon bubble -->
                <component
                    :is="step.href ? 'a' : step.state === 'optional' ? 'button' : 'div'"
                    :href="step.href || undefined"
                    @click="step.state === 'optional' ? openOptionalModal(step.key) : undefined"
                    class="flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all"
                    :class="{
                        'bg-green-500 border-green-500 text-white': step.state === 'complete',
                        'bg-blue-600 border-blue-600 text-white ring-4 ring-blue-100 dark:ring-blue-900/40': step.state === 'current',
                        'bg-amber-400 border-amber-400 text-white': step.state === 'pending',
                        'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500': step.state === 'todo',
                        'bg-white dark:bg-gray-800 border-dashed border-gray-300 dark:border-gray-600 text-gray-300 dark:text-gray-600 hover:border-blue-400 hover:text-blue-400 cursor-pointer': step.state === 'optional',
                    }"
                >
                    <!-- complete -->
                    <span v-if="step.state === 'complete'" class="material-icons text-sm">check</span>
                    <!-- pending -->
                    <span v-else-if="step.state === 'pending'" class="material-icons text-sm">hourglass_top</span>
                    <!-- optional -->
                    <span v-else-if="step.state === 'optional'" class="material-icons text-sm">add</span>
                    <!-- todo / current -->
                    <span v-else class="material-icons text-sm">{{ step.icon }}</span>
                </component>

                <!-- Label -->
                <span class="mt-2 text-xs font-medium leading-tight"
                    :class="{
                        'text-green-600 dark:text-green-400': step.state === 'complete',
                        'text-blue-600 dark:text-blue-400': step.state === 'current',
                        'text-amber-600 dark:text-amber-400': step.state === 'pending',
                        'text-gray-400 dark:text-gray-500': step.state === 'todo',
                        'text-gray-300 dark:text-gray-600': step.state === 'optional',
                    }">
                    {{ step.label }}
                </span>

                <!-- Sub-label -->
                <span class="mt-0.5 text-xs leading-tight"
                    :class="{
                        'text-green-500 dark:text-green-500': step.state === 'complete',
                        'text-blue-500 dark:text-blue-500': step.state === 'current',
                        'text-amber-500 dark:text-amber-500': step.state === 'pending',
                        'text-gray-300 dark:text-gray-600': step.state === 'todo' || step.state === 'optional',
                    }">
                    <template v-if="step.state === 'complete'">Done</template>
                    <template v-else-if="step.state === 'current'">In progress</template>
                    <template v-else-if="step.state === 'pending'">In progress</template>
                    <template v-else-if="step.state === 'optional'">Optional</template>
                    <template v-else>Pending</template>
                </span>
            </div>
        </template>
    </div>
</div>

<!-- ─── Optional Step Confirm Modal ───────────────────────────────────── -->
<div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="confirmModal.show = false">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                <span class="material-icons text-blue-600 dark:text-blue-400">{{ confirmModal.type === 'contract' ? 'gavel' : 'local_shipping' }}</span>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    Add {{ confirmModal.type === 'contract' ? 'Contract' : 'Delivery' }}?
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">This will mark the deal as requiring a {{ confirmModal.type }}.</p>
            </div>
        </div>
        <div class="flex gap-3 justify-end mt-6">
            <button type="button" @click="confirmModal.show = false"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                Cancel
            </button>
            <button type="button" @click="confirmAddStep"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                Yes, add {{ confirmModal.type }}
            </button>
        </div>
    </div>
</div>

<!-- ─── Create Contract Modal ──────────────────────────────────────────── -->
<div v-if="createContractModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="createContractModal = false">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg mx-4">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                    <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">gavel</span>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create Contract</h3>
            </div>
            <button type="button" @click="createContractModal = false"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <span class="material-icons">close</span>
            </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="submitContract" class="p-6 space-y-4">

            <!-- Validation errors -->
            <div v-if="Object.keys(contractForm.errors).length" class="rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300 space-y-1">
                <p v-for="(msg, field) in contractForm.errors" :key="field">{{ msg }}</p>
            </div>

            <!-- Total amount -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 uppercase tracking-wide">
                    Contract Amount <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                    <input
                        v-model.number="contractForm.total_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 pl-7 pr-4 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Pre-filled from deal line items total</p>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 uppercase tracking-wide">Notes</label>
                <textarea
                    v-model="contractForm.notes"
                    rows="3"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"
                    placeholder="Optional contract notes…"
                />
            </div>

            <!-- Signature required -->
            <div class="flex items-center gap-3">
                <input
                    id="sig-required"
                    v-model="contractForm.signature_required"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                />
                <label for="sig-required" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    Signature required
                </label>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="createContractModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="contractForm.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg transition-colors">
                    <span v-if="contractForm.processing" class="material-icons text-sm animate-spin">refresh</span>
                    <span v-else class="material-icons text-sm">save</span>
                    Create Contract
                </button>
            </div>

        </form>
    </div>
</div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            
            <!-- ─── MAIN COLUMN ─────────────────────────────────────────── -->
            <div class="space-y-6 lg:col-span-8">

                <!-- Main detail card -->
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">

                    <!-- Blue header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-xl font-bold text-white">{{ record.title || `Deal #${record.sequence}` }}</h1>
                                <p class="text-blue-100 text-sm mt-0.5">Deal Record</p>
                            </div>
                            <div class="text-right">
                                <p class="text-blue-200 text-sm mb-0.5">Deal #</p>
                                <p class="text-white text-lg font-mono font-semibold">{{ record.sequence || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- Won / Lost banners -->
                        <div v-if="isWon" class="flex items-center gap-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3">
                            <span class="material-icons text-green-600 dark:text-green-400">emoji_events</span>
                            <div>
                                <p class="text-sm font-semibold text-green-800 dark:text-green-200">Deal Won</p>
                                <p v-if="record.won_at" class="text-sm text-green-600 dark:text-green-400">{{ formatDateTime(record.won_at) }}</p>
                            </div>
                        </div>
                        <div v-if="isLost" class="flex items-center gap-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                            <span class="material-icons text-red-600 dark:text-red-400">sentiment_dissatisfied</span>
                            <div>
                                <p class="text-sm font-semibold text-red-800 dark:text-red-200">Deal Lost</p>
                                <p v-if="record.lost_at" class="text-sm text-red-600 dark:text-red-400">{{ formatDateTime(record.lost_at) }}</p>
                                <p v-if="record.loss_reason_category" class="text-sm text-red-600 dark:text-red-400 mt-0.5">
                                    Category: {{ record.loss_reason_category }}
                                </p>
                            </div>
                        </div>

                        <!-- Customer & Relations -->
                        <div class=" border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Customer &amp; Relations
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</p>
                                    <Link v-if="record.customer_id" :href="route('customers.show', record.customer_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.customer?.display_name || '—' }}
                                    </Link>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">—</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Salesperson</p>
                                    <Link v-if="record.user" :href="route('users.show', record.user)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.user?.display_name || `#${record.user}` }}
                                    </Link>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">—</p>
                                </div>
                                <div v-if="record.estimate_id">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Estimate</p>
                                    <Link :href="route('estimates.show', record.estimate_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.estimate?.display_name || `#${record.estimate_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.opportunity_id">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Opportunity</p>
                                    <Link :href="route('opportunities.show', record.opportunity_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.opportunity?.display_name || `#${record.opportunity_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.subsidiary_id">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Subsidiary</p>
                                    <Link :href="route('subsidiaries.show', record.subsidiary_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.subsidiary?.display_name || `#${record.subsidiary_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.location_id">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Location</p>
                                    <Link :href="route('locations.show', record.location_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.location?.display_name || `#${record.location_id}` }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Next steps -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Next steps
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

                                <!-- Contract -->
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Needs contract</p>
                                    <p class="text-gray-900 dark:text-gray-100 mb-2">{{ record.needs_contract ? 'Yes' : 'No' }}</p>
                                    <template v-if="record.needs_contract">
                                        <a v-if="record.contract?.id"
                                            :href="route('contracts.show', record.contract.id)"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            <span class="material-icons text-sm">gavel</span>
                                            View Contract
                                        </a>
                                        <button v-else type="button"
                                            @click="openCreateContractModal"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                                            <span class="material-icons text-sm">add</span>
                                            Create Contract
                                        </button>
                                    </template>
                                </div>

                                <!-- Delivery -->
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Needs delivery</p>
                                    <p class="text-gray-900 dark:text-gray-100 mb-2">{{ record.needs_delivery ? 'Yes' : 'No' }}</p>
                                    <template v-if="record.needs_delivery">
                                        <a :href="route('deliveries.create')"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                                            <span class="material-icons text-sm">add</span>
                                            Schedule Delivery
                                        </a>
                                    </template>
                                </div>

                            </div>
                        </div>

                        <!-- Customer Snapshot -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Customer Snapshot
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 -mt-2">Preserved at time of deal creation</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Name</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.customer_name || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.customer_email || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Phone</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ formatPhoneNumber(record.customer_phone) || '—' }}</p>
                                </div>
                                <div v-if="record.billing_address_line1 || record.billing_city">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Billing Address</p>
                                    <div class="text-sm text-gray-900 dark:text-gray-100 space-y-0.5">
                                        <div v-if="record.billing_address_line1">{{ record.billing_address_line1 }}</div>
                                        <div v-if="record.billing_address_line2">{{ record.billing_address_line2 }}</div>
                                        <div v-if="record.billing_city || record.billing_state || record.billing_postal">
                                            {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                                        </div>
                                        <div v-if="record.billing_country">{{ record.billing_country }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    
                        <!-- Tax -->
                        <div v-if="record.tax_rate != null || record.tax_jurisdiction" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Tax
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Tax Rate</div>
                                    <div class="text-gray-900 dark:text-gray-100">{{ record.tax_rate != null ? `${record.tax_rate}%` : '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Jurisdiction</div>
                                    <div class="text-gray-900 dark:text-gray-100">{{ record.tax_jurisdiction || '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="record.notes" class=" border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Notes
                            </h3>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.notes }}</p>
                        </div>


                        <!-- Line Items -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Line Items
                            </h3>

                            <div v-if="items.length > 0" class="overflow-x-auto -mx-6 sm:mx-0">
                                <div class="inline-block min-w-full align-middle">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name / Description</th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Taxable</th>
                                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Qty</th>
                                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Unit Price</th>
                                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Pre-tax</th>
                                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Tax</th>
                                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <template v-for="row in items" :key="row.id">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-sm text-gray-900 dark:text-white">{{ row.name }}</div>
                                                        <div v-if="row.description" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ row.description }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ row.taxable !== false && row.taxable !== 0 ? 'Yes' : 'No' }}</td>
                                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ row.quantity }}</td>
                                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(row.unit_price) }}</td>
                                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(row)) }}</td>
                                                    <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatMoney(taxOnItemBase(row)) }}</td>
                                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(row) + taxOnItemBase(row)) }}</td>
                                                </tr>
                                                <tr
                                                    v-for="addon in (row.addons || [])"
                                                    :key="'addon-' + addon.id"
                                                    class="bg-blue-50/30 dark:bg-blue-900/10"
                                                >
                                                    <td class="px-4 py-2 pl-10 text-sm text-gray-600 dark:text-gray-400 italic">
                                                        ↳ {{ addon.name || 'Add-on' }}
                                                        <span v-if="addon.notes" class="block text-gray-400 dark:text-gray-500 not-italic">{{ addon.notes }}</span>
                                                    </td>
                                                    <td class="px-4 py-2 text-center text-xs text-gray-500 dark:text-gray-400">{{ addon.taxable !== false && addon.taxable !== 0 ? 'Yes' : 'No' }}</td>
                                                    <td class="px-4 py-2 text-right text-sm text-gray-400">{{ addon.quantity }}</td>
                                                    <td class="px-4 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</td>
                                                    <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatMoney(addonPreTaxTotal(addon)) }}</td>
                                                    <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(taxOnAddon(addon)) }}</td>
                                                    <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-else class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                                <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">receipt_long</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No line items on this deal</p>
                            </div>
                        </div>



                        <!-- Loss Tracking -->
                        <div v-if="record.loss_reason || record.loss_reason_category" class="border-red-200 dark:border-red-800 pt-6">
                            <h3 class="text-sm font-semibold text-red-700 dark:text-red-300 uppercase tracking-wide border-b border-red-200 dark:border-red-800 pb-2 mb-4">
                                Loss Tracking
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Category</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.loss_reason_category || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Reason</p>
                                    <p class="text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ record.loss_reason || '—' }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ─── SIDEBAR ────────────────────────────────────────────── -->
            <div class="space-y-4 lg:col-span-4">

                <!-- Status & Dates -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Status</span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium" :class="statusMeta.bgClass">
                            {{ statusMeta.label }}
                        </span>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-gray-900 dark:text-white text-right">{{ formatDateTime(record.created_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Updated</span>
                            <span class="text-gray-900 dark:text-white text-right">{{ formatDateTime(record.updated_at) }}</span>
                        </div>
                        <div v-if="record.won_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Won</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ formatDateTime(record.won_at) }}</span>
                        </div>
                        <div v-if="record.lost_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Lost</span>
                            <span class="font-medium text-red-600 dark:text-red-400">{{ formatDateTime(record.lost_at) }}</span>
                        </div>
                        <div v-if="record.closed_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Closed</span>
                            <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.closed_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Deal Summary -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Deal Summary</span>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(record.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Tax ({{ record.tax_rate != null ? record.tax_rate : '—' }}%)</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(record.tax_total) }}</span>
                        </div>
                        <div v-if="Number(record.discount_total) > 0" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Discount</span>
                            <span class="font-medium text-red-600 dark:text-red-400">−{{ formatMoney(record.discount_total) }}</span>
                        </div>
                        <div v-if="Number(record.fees_total) > 0" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Fees</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(record.fees_total) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold border-t border-gray-200 dark:border-gray-600 pt-3">
                            <span class="text-gray-900 dark:text-white">Total</span>
                            <span class="text-blue-600 dark:text-blue-400">{{ formatMoney(record.total) }}</span>
                        </div>
                        <div class="pt-1 text-sm text-gray-400 dark:text-gray-500 text-right">
                            {{ record.currency || 'USD' }} · {{ items.length }} line item{{ items.length !== 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>

                <!-- Related Records -->
                <div v-if="relatedRecords.length > 0" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Related Records</span>
                    </div>
                    <div class="p-4 space-y-2">
                        <component
                            :is="rel.href ? Link : 'div'"
                            v-for="rel in relatedRecords"
                            :key="rel.key"
                            :href="rel.href || undefined"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group"
                            :class="{ 'cursor-default': !rel.href }"
                        >
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">{{ rel.icon }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ rel.label }}</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ rel.name }}</p>
                                <p v-if="rel.meta" class="text-sm text-gray-500 dark:text-gray-400">{{ rel.meta }}</p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p v-if="rel.amount != null" class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(rel.amount) }}</p>
                                <span v-if="rel.href" class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-base transition-colors">chevron_right</span>
                            </div>
                        </component>
                    </div>
                </div>


            </div>
        </div>
    </TenantLayout>
</template>
