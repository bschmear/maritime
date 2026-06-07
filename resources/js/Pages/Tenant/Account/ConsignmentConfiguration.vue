<script setup>
import ConsignmentAgreementPreview from '@/Components/Tenant/ConsignmentAgreementPreview.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref, watch } from 'vue';

const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

const props = defineProps({
    account: { type: Object, required: true },
    policies: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const showPreview = ref(false);

const settingsForm = useForm({
    consignment_fee_percent: parseFloat(props.account?.consignment_fee_percent) || 20,
    consignment_terms: props.account?.consignment_terms ?? '',
});

const newPolicyForm = useForm({
    body: '',
    is_active: true,
});

/** Local drafts for each policy row (avoid mutating props). */
const policyDrafts = ref({});

const logoUrl = computed(() => props.account?.logo_url ?? null);

const previewAccount = computed(() => ({
    ...props.account,
    consignment_fee_percent: settingsForm.consignment_fee_percent,
    consignment_terms: settingsForm.consignment_terms,
}));

const previewPolicies = computed(() =>
    props.policies
        .filter((policy) => policyDrafts.value[policy.id]?.is_active ?? policy.is_active)
        .map((policy, index) => ({
            id: policy.id,
            body: policyDrafts.value[policy.id]?.body ?? policy.body,
            sort_order: index,
        })),
);

const sampleAgreementRecord = computed(() => {
    const companyName = page.props.app?.name || 'Your dealership';

    return {
        display_name: 'PREVIEW-001',
        agreement_date: new Date().toISOString(),
        created_at: new Date().toISOString(),
        boat_title_signed_delivered: true,
        boat_description:
            '2024 Example Boats 2400 XS — white hull, blue canvas, Garmin electronics package, and bow filler seating.',
        motor_description: 'Mercury 250 HP Verado outboard with digital throttle and shift.',
        other_description: 'Tandem axle galvanized trailer with spare tire.',
        notes: 'Sample agreement for preview only. Replace with real unit and owner details on each consignment.',
        asking_boat: 89500,
        minimum_boat: 82500,
        asking_motor: 0,
        minimum_motor: 0,
        asking_other: 3500,
        minimum_other: 3000,
        asking_sold: 0,
        minimum_sold: 0,
        owner_contact: {
            display_name: 'Jordan Sample',
            email: 'owner@example.com',
            phone: '(555) 555-0100',
            mobile: '(555) 555-0101',
        },
        owner_contact_address: {
            address_line_1: '123 Harbor View Dr',
            city: 'Anytown',
            state: 'FL',
            postal_code: '33101',
            country: 'USA',
        },
        asset_unit: {
            display_name: '2024 Example Boats 2400 XS',
            serial_number: 'EXB-2400-001',
            asset: {
                year: 2024,
                make: { display_name: 'Example Boats' },
            },
            subsidiary: {
                display_name: companyName,
                address_line_1: '100 Marina Way',
                city: 'Anytown',
                state: 'FL',
                postal_code: '33101',
                phone: '(555) 555-0200',
                email: 'sales@example.com',
            },
        },
    };
});

const openPreview = () => {
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
};

const syncDraftsFromPolicies = () => {
    const next = {};
    for (const p of props.policies) {
        next[p.id] = {
            body: p.body ?? '',
            is_active: !!p.is_active,
        };
    }
    policyDrafts.value = next;
};

watch(
    () => props.policies,
    () => {
        syncDraftsFromPolicies();
    },
    { immediate: true, deep: true },
);

const saveSettings = () => {
    settingsForm
        .transform((data) => ({
            ...data,
            consignment_fee_percent: parseFloat(data.consignment_fee_percent),
        }))
        .patch(route('account.consignment.settings'), { preserveScroll: true });
};

const addPolicy = () => {
    newPolicyForm.post(route('account.consignment.policies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newPolicyForm.reset();
            newPolicyForm.is_active = true;
            showToast('success', 'Policy added.');
        },
    });
};

const savePolicy = (policyId) => {
    const draft = policyDrafts.value[policyId];
    if (!draft) {
        return;
    }
    router.patch(
        route('account.consignment.policies.update', policyId),
        { body: draft.body, is_active: draft.is_active },
        { preserveScroll: true },
    );
};

const deletePolicy = (policyId) => {
    if (!window.confirm('Remove this policy?')) {
        return;
    }
    router.delete(route('account.consignment.policies.destroy', policyId), { preserveScroll: true });
};

const movePolicy = (index, delta) => {
    const list = [...props.policies];
    const ni = index + delta;
    if (ni < 0 || ni >= list.length) {
        return;
    }
    const reordered = [...list];
    const t = reordered[index];
    reordered[index] = reordered[ni];
    reordered[ni] = t;
    router.post(
        route('account.consignment.policies.reorder'),
        { ids: reordered.map((p) => p.id) },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Consignment policy & agreements" />

    <TenantLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Link
                        :href="route('account.index')"
                        class="mb-2 inline-flex items-center gap-1 text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-lg leading-none">arrow_back</span>
                        Account
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Consignment policy &amp; agreements
                    </h2>
                    <p class="mt-1 text-md text-gray-500 dark:text-gray-400">
                        Set the default consignment fee, long-form terms, and policy bullets shown to owners on the public consignment agreement page.
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Preview sample agreement"
                    class="inline-flex shrink-0 items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-secondary-600 p-2 text-md font-medium text-white transition-colors hover:bg-secondary-700 md:gap-1.5 md:px-4 md:py-2.5"
                    @click="openPreview"
                >
                    <span class="material-icons text-xl leading-none md:text-md">visibility</span>
                    <span class="hidden md:inline">Preview agreement</span>
                </button>
            </div>
        </template>

        <!-- Flash -->
        <div v-if="flashSuccess" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
            {{ flashSuccess }}
        </div>

        <!-- Two-column grid -->
        <div class="grid grid-cols-1 gap-0 lg:grid-cols-[520px_1fr] lg:gap-0 lg:divide-x lg:divide-gray-200 dark:lg:divide-gray-700">

            <!-- ── LEFT: Fee & terms ──────────────────────────────────── -->
            <div class="bg-white px-6 py-8 dark:bg-gray-900 lg:px-8">
                <!-- Section label -->
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/50">
                        <span class="material-icons text-[18px] leading-none text-primary-600 dark:text-primary-400">percent</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Step 1</p>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Fee &amp; terms</h3>
                    </div>
                </div>

                <form class="space-y-6" @submit.prevent="saveSettings">
                    <!-- Fee -->
                    <div>
                        <label for="fee" class="mb-1.5 block text-md font-semibold text-gray-700 dark:text-gray-300">
                            Consignment fee
                        </label>
                        <div class="relative max-w-[140px]">
                            <input
                                id="fee"
                                v-model.number="settingsForm.consignment_fee_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                class="block w-full rounded-lg border border-gray-300 py-2.5 pl-3 pr-8 text-md shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-md font-medium text-gray-400">%</span>
                        </div>
                        <p v-if="settingsForm.errors.consignment_fee_percent" class="mt-1 text-sm text-red-600">
                            {{ settingsForm.errors.consignment_fee_percent }}
                        </p>
                    </div>

                    <!-- Terms -->
                    <div>
                        <label for="terms" class="mb-1.5 block text-md font-semibold text-gray-700 dark:text-gray-300">
                            Terms of consignment
                        </label>
                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                            Optional narrative shown alongside the policy bullets.
                        </p>
                        <textarea
                            id="terms"
                            v-model="settingsForm.consignment_terms"
                            rows="10"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-md shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            placeholder="Optional narrative terms…"
                        />
                        <p v-if="settingsForm.errors.consignment_terms" class="mt-1 text-sm text-red-600">
                            {{ settingsForm.errors.consignment_terms }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="settingsForm.processing"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-md font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50"
                    >
                        <span class="material-icons text-lg leading-none">save</span>
                        {{ settingsForm.processing ? 'Saving…' : 'Save fee & terms' }}
                    </button>
                </form>
            </div>

            <!-- ── RIGHT: Policy bullets ──────────────────────────────── -->
            <div class="bg-gray-50 px-6 py-8 dark:bg-gray-950 lg:px-8">

                <!-- Section label -->
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/50">
                            <span class="material-icons text-[18px] leading-none text-primary-600 dark:text-primary-400">format_list_bulleted</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Step 2</p>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Policy bullets</h3>
                        </div>
                    </div>
                    <span v-if="policies.length" class="rounded-full bg-primary-100 px-2.5 py-0.5 text-sm font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                        {{ policies.length }} {{ policies.length === 1 ? 'policy' : 'policies' }}
                    </span>
                </div>

                <!-- Add policy form -->
                <form class="mb-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900" @submit.prevent="addPolicy">
                    <p class="mb-3 text-sm font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">New policy bullet</p>
                    <textarea
                        v-model="newPolicyForm.body"
                        rows="3"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-md focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="e.g. Boats will only be taken in if space is available."
                    />
                    <p v-if="newPolicyForm.errors.body" class="mt-1 text-sm text-red-600">{{ newPolicyForm.errors.body }}</p>

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <label class="flex cursor-pointer items-center gap-2 text-md text-gray-600 dark:text-gray-300 select-none">
                            <input v-model="newPolicyForm.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                            Show on public agreement
                        </label>
                        <button
                            type="submit"
                            :disabled="newPolicyForm.processing || !newPolicyForm.body.trim()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-md font-semibold text-white transition hover:bg-gray-700 disabled:opacity-40 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                        >
                            <span class="material-icons text-lg leading-none">add</span>
                            {{ newPolicyForm.processing ? 'Adding…' : 'Add' }}
                        </button>
                    </div>
                </form>

                <!-- Policy list -->
                <ul v-if="policies.length" class="space-y-3">
                    <li
                        v-for="(policy, index) in policies"
                        :key="policy.id"
                        class="group relative rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
                    >
                        <!-- Left accent bar — green when active, gray when inactive -->
                        <div
                            class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl transition-colors"
                            :class="policyDrafts[policy.id]?.is_active ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-700'"
                        />

                        <div class="pl-4 pr-4 py-4">
                            <!-- Reorder + status row -->
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex h-6 w-6 items-center justify-center rounded border border-gray-200 text-gray-400 transition hover:bg-gray-50 hover:text-gray-700 disabled:opacity-25 dark:border-gray-700 dark:hover:bg-gray-800"
                                        :disabled="index === 0"
                                        title="Move up"
                                        @click="movePolicy(index, -1)"
                                    >
                                        <span class="material-icons text-md leading-none">arrow_upward</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-6 w-6 items-center justify-center rounded border border-gray-200 text-gray-400 transition hover:bg-gray-50 hover:text-gray-700 disabled:opacity-25 dark:border-gray-700 dark:hover:bg-gray-800"
                                        :disabled="index === policies.length - 1"
                                        title="Move down"
                                        @click="movePolicy(index, 1)"
                                    >
                                        <span class="material-icons text-md leading-none">arrow_downward</span>
                                    </button>
                                    <span class="ml-1 text-sm font-mono text-gray-300 dark:text-gray-600">#{{ index + 1 }}</span>
                                </div>

                                <label v-if="policyDrafts[policy.id]" class="flex cursor-pointer items-center gap-1.5 text-sm font-semibold select-none"
                                    :class="policyDrafts[policy.id].is_active ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400'"
                                >
                                    <input v-model="policyDrafts[policy.id].is_active" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                                    {{ policyDrafts[policy.id].is_active ? 'Active' : 'Hidden' }}
                                </label>
                            </div>

                            <!-- Textarea -->
                            <textarea
                                v-if="policyDrafts[policy.id]"
                                v-model="policyDrafts[policy.id].body"
                                rows="3"
                                class="block w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-md focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />

                            <!-- Actions -->
                            <div class="mt-3 flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    @click="savePolicy(policy.id)"
                                >
                                    <span class="material-icons text-md leading-none">save</span>
                                    Save
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-red-100 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400"
                                    @click="deletePolicy(policy.id)"
                                >
                                    <span class="material-icons text-md leading-none">delete_outline</span>
                                    Remove
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>

                <div v-else class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                    <span class="material-icons mb-2 text-3xl text-gray-300 dark:text-gray-600">article</span>
                    <p class="text-md font-medium text-gray-500 dark:text-gray-400">No policies yet</p>
                    <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Add your first bullet above.</p>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showPreview" class="consignment-agreement-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <ConsignmentAgreementPreview
                    :record="sampleAgreementRecord"
                    :account="previewAccount"
                    :logo-url="logoUrl"
                    :consignment-policies="previewPolicies"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>
