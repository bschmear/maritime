<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import ContactAddressAutocomplete from '@/Components/ContactAddressAutocomplete.vue';
import Modal from '@/Components/Modal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    /** `create` | `edit` | `view` */
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit', 'view'].includes(v),
    },
    /** Prefill for create (e.g. from `?asset_unit_id=`). */
    prefill: { type: Object, default: () => ({}) },
    /** Optional schema keys for RecordSelect labels (falls back to sensible defaults). */
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    /** When true, only post-sign fields (sold price, notes, boat title) are editable. */
    postSignOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['cancel']);

const isView = computed(() => props.mode === 'view');
const isCreate = computed(() => props.mode === 'create');
const isEdit = computed(() => props.mode === 'edit');
const isPostSignOnly = computed(() => props.postSignOnly && isEdit.value);
const isMutable = computed(() => !isView.value && !isPostSignOnly.value);
const postSignFieldKeys = new Set(['notes', 'asking_sold', 'minimum_sold', 'boat_title_signed_delivered']);
const isPostSignField = (key) => isPostSignOnly.value && postSignFieldKeys.has(key);
const fieldDisabled = (key) => !isMutable.value && !isPostSignField(key);
const showFormActions = computed(() => isMutable.value || isPostSignOnly.value);

const fieldOr = (key, fallback) => props.fieldsSchema[key] ?? fallback;

const assetUnitField = computed(() =>
    fieldOr('asset_unit_id', {
        type: 'record',
        typeDomain: 'AssetUnit',
        label: 'Asset unit',
        relationship: 'assetUnit',
    }),
);

const ownerContactField = computed(() =>
    fieldOr('owner_contact_id', {
        type: 'record',
        typeDomain: 'Contact',
        label: 'Owner / seller (contact)',
        relationship: 'ownerContact',
        required: true,
    }),
);

const lockOwnerContact = computed(
    () => !!(isCreate.value && props.prefill?.lock_owner_contact && props.prefill?.owner_contact_id),
);

const formatDateInput = (value) => {
    if (value == null || value === '') {
        return '';
    }
    const s = String(value);
    return s.length >= 10 ? s.slice(0, 10) : s;
};

const money = (v) => {
    if (v == null || v === '') {
        return '';
    }
    return typeof v === 'number' ? String(v) : String(v);
};

const addrFromContactAddress = (addr) => {
    if (!addr || typeof addr !== 'object') {
        return {
            owner_mailing_line1: '',
            owner_mailing_line2: '',
            owner_mailing_city: '',
            owner_mailing_state: '',
            owner_mailing_postal: '',
            owner_mailing_country: '',
        };
    }
    return {
        owner_mailing_line1: addr.address_line_1 ?? '',
        owner_mailing_line2: addr.address_line_2 ?? '',
        owner_mailing_city: addr.city ?? '',
        owner_mailing_state: addr.state ?? '',
        owner_mailing_postal: addr.postal_code ?? '',
        owner_mailing_country: addr.country ?? '',
    };
};

const buildDefaults = () => {
    const src = isCreate.value ? { ...props.prefill } : { ...props.record };
    const oca = src.owner_contact_address ?? src.ownerContactAddress ?? null;
    const lines = addrFromContactAddress(oca);
    return {
        asset_unit_id: src.asset_unit_id ?? null,
        agreement_date: formatDateInput(src.agreement_date),
        boat_description: src.boat_description ?? '',
        motor_description: src.motor_description ?? '',
        other_description: src.other_description ?? '',
        boat_title_signed_delivered: !!(src.boat_title_signed_delivered === true || src.boat_title_signed_delivered === 1 || src.boat_title_signed_delivered === '1'),
        owner_contact_id: src.owner_contact_id ?? null,
        owner_contact_address_id: src.owner_contact_address_id ?? null,
        ...lines,
        notes: src.notes ?? '',
        asking_boat: money(src.asking_boat),
        asking_motor: money(src.asking_motor),
        asking_other: money(src.asking_other),
        asking_sold: money(src.asking_sold),
        minimum_boat: money(src.minimum_boat),
        minimum_motor: money(src.minimum_motor),
        minimum_other: money(src.minimum_other),
        minimum_sold: money(src.minimum_sold),
    };
};

const form = useForm(buildDefaults());

const truthyFlag = (value) => value === true || value === 1 || value === '1';

const selectedAssetUnitIsConsignment = ref(
    !isCreate.value || !props.prefill?.asset_unit_id || truthyFlag(props.prefill?.is_consignment),
);

const showMarkConsignmentModal = ref(false);
const markAsConsignmentOnSubmit = ref(false);

watch(
    () => form.asset_unit_id,
    (id) => {
        if (!isCreate.value || id == null || id === '') {
            return;
        }
        if (Number(id) === Number(props.prefill?.asset_unit_id) && props.prefill?.is_consignment != null) {
            selectedAssetUnitIsConsignment.value = truthyFlag(props.prefill.is_consignment);
        }
    },
);

const needsMarkConsignmentPrompt = computed(
    () => isCreate.value && form.asset_unit_id && !selectedAssetUnitIsConsignment.value,
);

const selectedOwnerContactName = ref(
    props.prefill?.owner_contact?.display_name ?? props.prefill?.ownerContact?.display_name ?? '',
);

const resolveOwnerContactName = (contactId) => {
    if (!contactId) {
        return '';
    }
    const options = props.enumOptions.owner_contact_id ?? [];
    const match = options.find((o) => Number(o.id) === Number(contactId));
    if (match?.name) {
        return match.name;
    }
    const oc = props.prefill?.owner_contact ?? props.prefill?.ownerContact;
    if (oc && Number(oc.id) === Number(contactId)) {
        return oc.display_name ?? '';
    }
    return '';
};

const handleAssetUnitSelected = (record) => {
    if (!record || typeof record !== 'object') {
        return;
    }
    selectedAssetUnitIsConsignment.value = truthyFlag(record.is_consignment);
};

const markConsignmentOwnerName = computed(() => {
    if (selectedOwnerContactName.value) {
        return selectedOwnerContactName.value;
    }
    return resolveOwnerContactName(form.owner_contact_id) || 'the selected owner';
});

const canConfirmMarkConsignment = computed(() => !!form.owner_contact_id);

const isNotMarkedConsignmentError = (errors) => {
    const msg = errors?.asset_unit_id;
    const text = Array.isArray(msg) ? msg[0] : msg;
    return text && String(text).toLowerCase().includes('not marked as consignment');
};

const headerTitle = computed(() => {
    if (isCreate.value) {
        return 'New consignment agreement';
    }
    const ref = props.record?.display_name;
    if (ref != null && ref !== '') {
        return `#${ref}`;
    }
    return `Agreement #${props.record?.id ?? ''}`;
});

const headerSubtitle = computed(() => {
    if (isCreate.value) {
        return 'Select the consignment unit and complete the agreement details.';
    }
    if (isPostSignOnly.value) {
        return 'Update sold pricing, notes, or boat title status. The signed agreement terms are locked.';
    }
    if (isEdit.value) {
        return 'Update dealer prefill before sharing the public signing link.';
    }
    return 'Read-only agreement record.';
});

const moneyKeys = [
    'asking_boat',
    'asking_motor',
    'asking_other',
    'asking_sold',
    'minimum_boat',
    'minimum_motor',
    'minimum_other',
    'minimum_sold',
];

const transformPayload = (data) => {
    if (isPostSignOnly.value) {
        const out = {
            notes: data.notes,
            asking_sold: data.asking_sold,
            minimum_sold: data.minimum_sold,
            boat_title_signed_delivered: !!data.boat_title_signed_delivered,
        };
        for (const k of ['asking_sold', 'minimum_sold']) {
            if (out[k] === '') {
                out[k] = null;
            }
        }
        return out;
    }

    const out = {
        asset_unit_id: data.asset_unit_id,
        agreement_date: data.agreement_date,
        boat_description: data.boat_description,
        motor_description: data.motor_description,
        other_description: data.other_description,
        boat_title_signed_delivered: !!data.boat_title_signed_delivered,
        owner_contact_id: data.owner_contact_id,
        owner_contact_address_id: data.owner_contact_address_id || null,
        notes: data.notes,
        asking_boat: data.asking_boat,
        asking_motor: data.asking_motor,
        asking_other: data.asking_other,
        asking_sold: data.asking_sold,
        minimum_boat: data.minimum_boat,
        minimum_motor: data.minimum_motor,
        minimum_other: data.minimum_other,
        minimum_sold: data.minimum_sold,
    };
    for (const k of moneyKeys) {
        if (out[k] === '') {
            out[k] = null;
        }
    }
    return out;
};

const buildCreatePayload = (data) => {
    const payload = transformPayload(data);
    if (markAsConsignmentOnSubmit.value) {
        payload.mark_as_consignment = true;
    }
    return payload;
};

const performSubmit = () => {
    if (isCreate.value) {
        form.transform(buildCreatePayload).post(route('consignmentagreements.store'), {
            onSuccess: () => {
                markAsConsignmentOnSubmit.value = false;
            },
            onError: (errors) => {
                if (isNotMarkedConsignmentError(errors)) {
                    showMarkConsignmentModal.value = true;
                }
            },
        });
    } else if (props.record?.id != null) {
        form.transform(transformPayload).put(route('consignmentagreements.update', props.record.id));
    }
};

const submit = () => {
    if (!showFormActions.value) {
        return;
    }
    if (isCreate.value && needsMarkConsignmentPrompt.value && !markAsConsignmentOnSubmit.value) {
        showMarkConsignmentModal.value = true;
        return;
    }
    performSubmit();
};

const cancelMarkConsignment = () => {
    showMarkConsignmentModal.value = false;
};

const confirmMarkConsignment = () => {
    showMarkConsignmentModal.value = false;
    markAsConsignmentOnSubmit.value = true;
    selectedAssetUnitIsConsignment.value = true;
    performSubmit();
};

const pseudoRecord = computed(() => {
    const base = props.record ?? (Object.keys(props.prefill).length ? props.prefill : null);
    if (!base) {
        return null;
    }
    const oc = base.owner_contact ?? base.ownerContact ?? null;
    const oca = base.owner_contact_address ?? base.ownerContactAddress ?? null;
    return {
        ...base,
        ...(oc ? { owner_contact: oc, ownerContact: oc } : {}),
        ...(oca ? { owner_contact_address: oca, ownerContactAddress: oca } : {}),
    };
});

const inputClass =
    'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
const textareaClass = `${inputClass} resize-y min-h-[2.5rem]`;
const labelClass = 'mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300';

const signatureMethodLabel = (raw) => {
    const n = Number(raw);
    if (n === 1) {
        return 'Draw';
    }
    if (n === 5) {
        return 'Type';
    }
    return raw != null && raw !== '' ? String(raw) : '—';
};

const formatSignedAt = (v) => {
    if (v == null || v === '') {
        return '—';
    }
    try {
        return new Date(v).toLocaleString();
    } catch {
        return String(v);
    }
};

/* —— Owner address picker (same flow as InvoiceForm billing address) —— */
const showAddressPicker = ref(false);
const contactAddresses = ref([]);
const isFetchingAddresses = ref(false);
const addressPickerContactId = ref(null);
const postingContactAddress = ref(false);

const applyOwnerAddressToForm = (src) => {
    form.owner_mailing_line1 = src.address_line_1 || src.billing_address_line1 || '';
    form.owner_mailing_line2 = src.address_line_2 || src.billing_address_line2 || '';
    form.owner_mailing_city = src.city || src.billing_city || '';
    form.owner_mailing_state = src.state || src.billing_state || '';
    form.owner_mailing_postal = src.postal_code || src.billing_postal || '';
    form.owner_mailing_country = src.country || src.billing_country || '';
};

const fetchContactAddressesForPicker = async (contactId) => {
    isFetchingAddresses.value = true;
    contactAddresses.value = [];
    try {
        const res = await fetch(route('contacts.addresses.index', { contact: contactId }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            contactAddresses.value = data.addresses ?? [];
        }
    } catch {
        contactAddresses.value = [];
    } finally {
        isFetchingAddresses.value = false;
    }
};

const handleOwnerContactSelected = async (contact) => {
    if (!contact?.id || isView.value) {
        return;
    }
    selectedOwnerContactName.value = contact.display_name ?? '';
    form.owner_contact_id = contact.id;
    form.owner_contact_address_id = null;
    addressPickerContactId.value = contact.id;
    showAddressPicker.value = true;
    await fetchContactAddressesForPicker(contact.id);
};

const openOwnerContactAddressPicker = async () => {
    if (!form.owner_contact_id || isView.value) {
        return;
    }
    addressPickerContactId.value = form.owner_contact_id;
    showAddressPicker.value = true;
    await fetchContactAddressesForPicker(form.owner_contact_id);
};

const selectContactAddress = (addr) => {
    form.owner_contact_address_id = addr.id;
    applyOwnerAddressToForm(addr);
    dismissAddressPicker();
};

const dismissAddressPicker = () => {
    showAddressPicker.value = false;
    contactAddresses.value = [];
    addressPickerContactId.value = null;
};

const onOwnerContactAddressSaved = (payload) => {
    if (!addressPickerContactId.value) {
        return;
    }
    postingContactAddress.value = true;
    router.post(route('contacts.addresses.store', addressPickerContactId.value), payload, {
        preserveScroll: true,
        onFinish: () => {
            postingContactAddress.value = false;
        },
        onSuccess: async () => {
            applyOwnerAddressToForm({
                address_line_1: payload.address_line_1,
                address_line_2: payload.address_line_2 ?? null,
                city: payload.city,
                state: payload.state,
                postal_code: payload.postal_code,
                country: payload.country,
            });
            await fetchContactAddressesForPicker(addressPickerContactId.value);
            const match =
                contactAddresses.value.find((a) => a.address_line_1 === payload.address_line_1 && (a.postal_code || '') === (payload.postal_code || '')) ??
                contactAddresses.value[0];
            if (match) {
                form.owner_contact_address_id = match.id;
            } else {
                form.owner_contact_address_id = null;
            }
            dismissAddressPicker();
        },
    });
};

const handleOwnerMailingAddressUpdate = (data) => {
    form.owner_mailing_line1 = data.street ?? '';
    form.owner_mailing_line2 = data.unit ?? '';
    form.owner_mailing_city = data.city ?? '';
    form.owner_mailing_state = data.stateCode || data.state || '';
    form.owner_mailing_postal = data.postalCode ?? '';
    form.owner_mailing_country = data.countryCode || data.country || '';
    form.owner_contact_address_id = null;
};

const ownerView = computed(() => {
    const r = props.record;
    if (!r) {
        return { name: '—', lines: [], phone1: '—', phone2: '—' };
    }
    const c = r.owner_contact ?? r.ownerContact;
    const a = r.owner_contact_address ?? r.ownerContactAddress;
    const lines = [];
    if (a?.address_line_1) {
        lines.push(a.address_line_1);
    }
    if (a?.address_line_2) {
        lines.push(a.address_line_2);
    }
    const cityLine = [a?.city, [a?.state, a?.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ');
    if (cityLine) {
        lines.push(cityLine);
    }
    if (a?.country) {
        lines.push(a.country);
    }
    return {
        name: c?.display_name || '—',
        lines: lines.length ? lines : [],
        phone1: c?.phone || '—',
        phone2: c?.mobile || '—',
    };
});
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <form @submit.prevent="submit">
            <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                <div class="bg-gradient-to-r from-amber-600 to-amber-700 px-6 py-4 dark:from-amber-800 dark:to-amber-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-xl font-bold tracking-wide text-white sm:text-2xl">
                                {{ isCreate ? 'NEW' : isEdit ? 'EDIT' : 'VIEW' }} — CONSIGNMENT
                            </h1>
                            <p class="mt-1 text-sm text-amber-100">
                                {{ headerSubtitle }}
                            </p>
                        </div>
                        <div v-if="!isCreate" class="text-right">
                            <div class="text-xs font-medium uppercase tracking-wide text-amber-100">Reference</div>
                            <div class="font-mono text-lg text-white">
                                {{ headerTitle }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8 p-6">
                    <div v-if="isCreate" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400">info</span>
                        <p>
                            Only one unsigned agreement may exist per consignment unit. The customer signs on the public
                            review page after you save.
                        </p>
                    </div>

                    <!-- Unit -->
                    <section class="space-y-4">
                        <h3 class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:border-gray-700 dark:text-white">
                            Unit
                        </h3>
                        <div>
                            <label :class="labelClass" for="ca-asset-unit">{{ assetUnitField.label }}</label>
                            <RecordSelect
                                id="ca-asset-unit"
                                v-model="form.asset_unit_id"
                                :field="assetUnitField"
                                :enum-options="enumOptions.asset_unit_id ?? []"
                                :record="pseudoRecord"
                                field-key="asset_unit_id"
                                :disabled="!isCreate"
                                @record-selected="handleAssetUnitSelected"
                            />
                            <p v-if="form.errors.asset_unit_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.asset_unit_id }}
                            </p>
                        </div>
                    </section>

                    <!-- Details -->
                    <section class="space-y-4">
                        <h3 class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:border-gray-700 dark:text-white">
                            Agreement details
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label :class="labelClass" for="ca-agreement-date">Agreement date</label>
                                <DateInput id="ca-agreement-date" v-model="form.agreement_date" :disabled="!isMutable" />
                            </div>
                            <div class="flex items-center gap-2 pt-6 md:pt-8">
                                <input
                                    id="ca-boat-title"
                                    v-model="form.boat_title_signed_delivered"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                    :disabled="fieldDisabled('boat_title_signed_delivered')"
                                />
                                <label for="ca-boat-title" class="text-sm text-gray-800 dark:text-gray-200">
                                    Boat title signed &amp; delivered
                                </label>
                            </div>
                            <div class="md:col-span-2">
                                <label :class="labelClass" for="ca-boat">Boat</label>
                                <textarea id="ca-boat" v-model="form.boat_description" rows="3" :class="textareaClass" :disabled="!isMutable" />
                            </div>
                            <div class="md:col-span-2">
                                <label :class="labelClass" for="ca-motor">Motor</label>
                                <textarea id="ca-motor" v-model="form.motor_description" rows="3" :class="textareaClass" :disabled="!isMutable" />
                            </div>
                            <div class="md:col-span-2">
                                <label :class="labelClass" for="ca-other">Other</label>
                                <textarea id="ca-other" v-model="form.other_description" rows="3" :class="textareaClass" :disabled="!isMutable" />
                            </div>

                            <!-- Owner: contact + mailing (invoice-style) -->
                            <template v-if="!isView && !isPostSignOnly">
                                <div class="md:col-span-2 space-y-2">
                                    <label :class="labelClass" for="ca-owner-contact">{{ ownerContactField.label }} <span class="text-red-500">*</span></label>
                                    <RecordSelect
                                        id="ca-owner-contact"
                                        v-model="form.owner_contact_id"
                                        :field="ownerContactField"
                                        :enum-options="enumOptions.owner_contact_id ?? []"
                                        :record="pseudoRecord"
                                        field-key="owner_contact_id"
                                        :disabled="!isMutable || lockOwnerContact"
                                        @record-selected="handleOwnerContactSelected"
                                    />
                                    <p v-if="form.errors.owner_contact_id" class="text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.owner_contact_id }}
                                    </p>
                                    <p v-if="lockOwnerContact" class="text-xs text-gray-500 dark:text-gray-400">
                                        Contact is fixed to this unit’s customer. Use “Choose from contact” to pick the mailing address.
                                    </p>
                                </div>

                                <div class="md:col-span-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                            Owner mailing address
                                        </h4>
                                        <button
                                            v-if="form.owner_contact_id"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                            @click="openOwnerContactAddressPicker"
                                        >
                                            <span class="material-icons text-[16px]">person_pin_circle</span>
                                            Choose from contact
                                        </button>
                                    </div>
                                    <AddressAutocomplete
                                        :street="form.owner_mailing_line1"
                                        :unit="form.owner_mailing_line2"
                                        :city="form.owner_mailing_city"
                                        :state="form.owner_mailing_state"
                                        :stateCode="form.owner_mailing_state"
                                        :postalCode="form.owner_mailing_postal"
                                        :country="form.owner_mailing_country"
                                        :disabled="!isMutable"
                                        @update="handleOwnerMailingAddressUpdate"
                                    />
                                    <p v-if="form.errors.owner_contact_address_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.owner_contact_address_id }}
                                    </p>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Pick a saved address to link this agreement to that contact address record. Editing the
                                        lines manually clears the link until you choose again.
                                    </p>
                                </div>
                            </template>
                            <div v-else-if="isView || isPostSignOnly" class="md:col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40">
                                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Owner / seller</h4>
                                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <dt class="text-gray-500 dark:text-gray-400">Contact</dt>
                                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ ownerView.name }}</dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-gray-500 dark:text-gray-400">Mailing address</dt>
                                        <dd class="whitespace-pre-line text-gray-900 dark:text-gray-100">
                                            {{ ownerView.lines.length ? ownerView.lines.join('\n') : '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                                        <dd class="text-gray-900 dark:text-gray-100">{{ ownerView.phone1 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Mobile</dt>
                                        <dd class="text-gray-900 dark:text-gray-100">{{ ownerView.phone2 }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="md:col-span-2">
                                <label :class="labelClass" for="ca-notes">Notes</label>
                                <textarea id="ca-notes" v-model="form.notes" rows="3" :class="textareaClass" :disabled="fieldDisabled('notes')" />
                            </div>
                        </div>
                    </section>

                    <!-- Pricing -->
                    <section class="space-y-4">
                        <h3 class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:border-gray-700 dark:text-white">
                            Pricing
                        </h3>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase text-gray-600 dark:text-gray-400">Asking</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label :class="labelClass" for="ca-ask-boat">Boat</label>
                                        <CurrencyInput id="ca-ask-boat" v-model="form.asking_boat" icon-position="none" :disabled="!isMutable" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" for="ca-ask-motor">Motor</label>
                                        <CurrencyInput id="ca-ask-motor" v-model="form.asking_motor" icon-position="none" :disabled="!isMutable" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" for="ca-ask-other">Other</label>
                                        <CurrencyInput id="ca-ask-other" v-model="form.asking_other" icon-position="none" :disabled="!isMutable" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" for="ca-ask-sold">Sold</label>
                                        <CurrencyInput id="ca-ask-sold" v-model="form.asking_sold" icon-position="none" :disabled="fieldDisabled('asking_sold')" />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase text-gray-600 dark:text-gray-400">Minimum</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label :class="labelClass" for="ca-min-boat">Boat</label>
                                        <CurrencyInput id="ca-min-boat" v-model="form.minimum_boat" icon-position="none" :disabled="!isMutable" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" for="ca-min-motor">Motor</label>
                                        <CurrencyInput id="ca-min-motor" v-model="form.minimum_motor" icon-position="none" :disabled="!isMutable" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" for="ca-min-other">Other</label>
                                        <CurrencyInput id="ca-min-other" v-model="form.minimum_other" icon-position="none" :disabled="!isMutable" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" for="ca-min-sold">Sold</label>
                                        <CurrencyInput id="ca-min-sold" v-model="form.minimum_sold" icon-position="none" :disabled="fieldDisabled('minimum_sold')" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Signature (read-only) -->
                    <section v-if="record?.signed_at" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900 dark:text-white">Customer signature</h3>
                        <div
                            v-if="record.signature_url || (Number(record.signature_method) === 5 && record.customer_signature)"
                            class="flex flex-col gap-4 sm:flex-row sm:items-start"
                        >
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-600 dark:bg-gray-700">
                                <img
                                    v-if="record.signature_url"
                                    :src="record.signature_url"
                                    alt="Customer signature"
                                    class="max-h-28 w-auto object-contain"
                                />
                                <p v-else class="signature-cursive text-3xl text-gray-900 dark:text-gray-100">
                                    {{ record.customer_signature }}
                                </p>
                            </div>
                            <dl class="grid flex-1 grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Signed at</dt>
                                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatSignedAt(record.signed_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Signed name</dt>
                                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ record.signed_name || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Signature method</dt>
                                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ signatureMethodLabel(record.signature_method) }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-gray-500 dark:text-gray-400">Signature hash</dt>
                                    <dd class="break-all font-mono text-xs text-gray-800 dark:text-gray-200">
                                        {{ record.signature_hash || '—' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <dl v-else class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Signed at</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatSignedAt(record.signed_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Signed name</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ record.signed_name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Signature method</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ signatureMethodLabel(record.signature_method) }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500 dark:text-gray-400">Signature hash</dt>
                                <dd class="break-all font-mono text-xs text-gray-800 dark:text-gray-200">
                                    {{ record.signature_hash || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <p v-if="form.hasErrors" class="text-sm text-red-600 dark:text-red-400">
                        Please correct the errors highlighted above and try again.
                    </p>

                    <!-- Actions -->
                    <div v-if="showFormActions" class="flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="emit('cancel')"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving…' : isCreate ? 'Create agreement' : 'Save changes' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showAddressPicker"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                    @click.self="dismissAddressPicker"
                >
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="flex items-start justify-between gap-3 px-6 pt-6 pb-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Owner mailing address</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        <template v-if="isFetchingAddresses">Loading addresses…</template>
                                        <template v-else-if="contactAddresses.length > 0">Select one of this contact’s saved addresses</template>
                                        <template v-else>This contact has no saved addresses yet. Add one to save it on the contact and use it here.</template>
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 mt-0.5" @click="dismissAddressPicker">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div v-if="isFetchingAddresses" class="flex justify-center py-12">
                            <svg class="w-8 h-8 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>

                        <div v-else-if="contactAddresses.length > 0" class="px-6 pb-2 space-y-2 max-h-80 overflow-y-auto">
                            <button
                                v-for="addr in contactAddresses"
                                :key="addr.id"
                                type="button"
                                class="w-full text-left px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors group"
                                @click="selectContactAddress(addr)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm space-y-0.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ addr.address_line_1 }}</p>
                                            <span
                                                v-if="addr.is_primary"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                            >Primary</span>
                                            <span
                                                v-if="addr.label"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300"
                                            >{{ addr.label }}</span>
                                        </div>
                                        <p v-if="addr.address_line_2" class="text-gray-500 dark:text-gray-400">{{ addr.address_line_2 }}</p>
                                        <p class="text-gray-600 dark:text-gray-300">
                                            <span v-if="addr.city">{{ addr.city }}<span v-if="addr.state || addr.postal_code">, </span></span>
                                            <span v-if="addr.state">{{ addr.state }} </span>
                                            <span v-if="addr.postal_code">{{ addr.postal_code }}</span>
                                        </p>
                                        <p v-if="addr.country" class="text-gray-500 dark:text-gray-400">{{ addr.country }}</p>
                                    </div>
                                    <svg class="w-4 h-4 flex-shrink-0 text-gray-300 group-hover:text-primary-500 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </button>
                        </div>

                        <div v-else class="px-6 pb-2 space-y-4">
                            <ContactAddressAutocomplete
                                :disabled="postingContactAddress"
                                button-label="Add address to contact"
                                @saved="onOwnerContactAddressSaved"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 mt-2">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                @click="dismissAddressPicker"
                            >
                                Skip, fill manually
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Modal :show="showMarkConsignmentModal" max-width="md" @close="cancelMarkConsignment">
            <div class="p-6">
                <h3 class="text-center text-lg font-medium text-gray-900 dark:text-white">Not marked as consignment</h3>
                <p class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                    This unit is not marked as consignment. Continuing will update the asset unit and create the agreement.
                </p>
                <div
                    v-if="canConfirmMarkConsignment"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <p class="font-medium">The asset unit will be updated to:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Consignment = Yes</li>
                        <li>Customer owned = Yes</li>
                        <li>Customer = {{ markConsignmentOwnerName }} (agreement owner)</li>
                    </ul>
                </div>
                <p
                    v-else
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >
                    Select an owner on this agreement before marking the unit as consignment.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing || !canConfirmMarkConsignment"
                        @click="confirmMarkConsignment"
                    >
                        {{ form.processing ? 'Saving…' : 'Mark as consignment & continue' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        :disabled="form.processing"
                        @click="cancelMarkConsignment"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}
</style>
