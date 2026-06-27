<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import DeliveryPreview from '@/Components/Tenant/DeliveryPreview.vue';
import MobileActionBar from '@/Components/Tenant/MobileActionBar.vue';
import MobileActionBarButton from '@/Components/Tenant/MobileActionBarButton.vue';
import { useMobileActionBar } from '@/composables/useMobileActionBar';
import { usePwaLinks } from '@/composables/usePwaLinks';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { useTimezone } from '@/composables/useTimezone';
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue';

dayjs.extend(utc);
dayjs.extend(timezone);

const props = defineProps({
    record: { type: Object, required: true },
    formSchema: { type: Object, default: null },
    domainName: { type: String, default: 'Delivery' },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    checklistItems: { type: Array, default: () => [] },
    checklistTemplates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    customerAddresses: { type: Array, default: () => [] },
    deliveryEnRouteSms: {
        type: Object,
        default: () => ({ modal: false, offered: false, hint: null }),
    },
    deliveryArrivedSms: {
        type: Object,
        default: () => ({
            show_sms_choice: false,
            offered: false,
            hint: null,
            category_enabled: false,
        }),
    },
    deliverySignatureSms: {
        type: Object,
        default: () => ({
            category_enabled: false,
            offered: false,
            hint: null,
        }),
    },
    logoUrl: { type: String, default: null },
    canApproveRequest: { type: Boolean, default: false },
    canResubmitRequest: { type: Boolean, default: false },
    canUpdateRequest: { type: Boolean, default: false },
    canCancelDeniedRequest: { type: Boolean, default: false },
    canCreateDelivery: { type: Boolean, default: true },
});

const page = usePage();
const inertiaApp = getCurrentInstance();
const { accountTimezone } = useTimezone();
const { headerActionsClass } = useMobileActionBar();
const { externalLinkTarget } = usePwaLinks();

const effectiveLogoUrl = computed(() => props.logoUrl ?? props.account?.logo_url ?? null);

/** Delivery SMS category enabled in account settings (strict — avoids stale / loose truthy props). */
const deliverySmsEnabledForEnRoute = computed(() => props.deliveryEnRouteSms?.modal === true);

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);

const visibleSublists = computed(() => (
    (props.formSchema?.sublists ?? []).filter((sublist) => sublist.domain !== 'DeliveryChecklistItem')
));

const showDeleteModal = ref(false);
const isDeleting = ref(false);
const showPreview = ref(false);
const showMarkDeliveredModal = ref(false);

// Checklist state (carried over from original)
const showChecklistModal = ref(false);
const checklistCreationMode = ref('template');
const selectedTemplate = ref(null);
const newChecklistItems = ref([]);
const isLoadingChecklist = ref(false);

const showAddItemModal = ref(false);
const editingChecklistItem = ref(null);
const newItemLabel = ref('');
const newItemCategory = ref('');
const newItemRequired = ref(false);

/** Default category label when `categories` prop is empty (aligned with tenant seeder). */
const DEFAULT_CHECKLIST_CATEGORY_NAME = 'Pre Delivery Checklist';

const showCategoryAdminModal = ref(false);
const categoryAdminEditingId = ref(null);
const categoryAdminName = ref('');
const categoryAdminColor = ref('blue');
const categoryAdminSaving = ref(false);

const categoryColorOptions = [
    { value: 'blue', label: 'Blue' },
    { value: 'green', label: 'Green' },
    { value: 'red', label: 'Red' },
    { value: 'amber', label: 'Amber' },
    { value: 'purple', label: 'Purple' },
];

const firstChecklistCategoryName = () => props.categories?.[0]?.name ?? DEFAULT_CHECKLIST_CATEGORY_NAME;

const openCategoryAdminModal = () => {
    categoryAdminEditingId.value = null;
    categoryAdminName.value = '';
    categoryAdminColor.value = 'blue';
    showCategoryAdminModal.value = true;
};

const openCategoryAdminModalForEdit = (categoryId) => {
    const c = (props.categories || []).find((x) => x.id === categoryId);
    if (!c) return;
    categoryAdminEditingId.value = c.id;
    categoryAdminName.value = c.name;
    categoryAdminColor.value = c.color || 'blue';
    showCategoryAdminModal.value = true;
};

const closeCategoryAdminModal = () => {
    showCategoryAdminModal.value = false;
    categoryAdminEditingId.value = null;
    categoryAdminName.value = '';
    categoryAdminColor.value = 'blue';
};

const saveCategoryAdminModal = async () => {
    const name = categoryAdminName.value.trim();
    if (!name) return;
    categoryAdminSaving.value = true;
    try {
        if (categoryAdminEditingId.value != null) {
            await axios.put(route('delivery-checklist-templates.categories.update', categoryAdminEditingId.value), {
                name,
                color: categoryAdminColor.value,
            });
        } else {
            await axios.post(route('delivery-checklist-templates.categories.store'), {
                name,
                color: categoryAdminColor.value,
            });
        }
        closeCategoryAdminModal();
        router.reload({ only: ['categories', 'checklistItems'] });
    } catch (e) {
        const err = e.response?.data;
        const msg = err?.message
            || (err?.errors && Object.values(err.errors).flat().join(' '))
            || 'Could not save category.';
        alert(msg);
    } finally {
        categoryAdminSaving.value = false;
    }
};

/* ─── Status ─── */
const isSigned = computed(() => !!props.record?.signed_at);

const deliveryStatusOptions = computed(() => props.enumOptions?.delivery_status || [
    { id: 'requested', name: 'Requested' },
    { id: 'scheduled', name: 'Scheduled' },
    { id: 'en_route', name: 'En Route' },
    { id: 'delivered', name: 'Delivered' },
    { id: 'cancelled', name: 'Cancelled' },
    { id: 'rescheduled', name: 'Rescheduled' },
]);

const statusOptionValue = (o) => o.value ?? o.id;
const statusOptionLabel = (o) => o.name ?? o.label ?? String(statusOptionValue(o));

const statusOptionsForSelect = computed(() => {
    const opts = Array.isArray(deliveryStatusOptions.value) ? [...deliveryStatusOptions.value] : [];
    const vals = new Set(opts.map(o => statusOptionValue(o)));
    const cur = props.record?.status;
    if (cur && !vals.has(cur)) {
        opts.unshift({ id: cur, name: cur });
    }
    return opts;
});

const recordStatusLabel = computed(() => {
    if (props.record?.pending_request) {
        return 'Pending request';
    }
    const cur = props.record?.status;
    if (cur == null || cur === '') return '—';
    const opts = Array.isArray(deliveryStatusOptions.value) ? deliveryStatusOptions.value : [];
    const found = opts.find((o) => statusOptionValue(o) === cur);
    if (found) return statusOptionLabel(found);
    return String(cur);
});

const statusUpdating = ref(false);

const updateDeliveryStatus = async (event) => {
    const el = event?.target;
    const newStatus = el?.value;
    if (!newStatus || newStatus === props.record?.status || isSigned.value) return;
    statusUpdating.value = true;
    try {
        await axios.put(route('deliveries.update', props.record.id), { status: newStatus });
        router.reload({ only: ['record', 'deliveryEnRouteSms', 'deliveryArrivedSms'] });
    } catch (e) {
        console.error(e);
        alert(e?.response?.data?.message ?? 'Failed to update status.');
        if (el) el.value = props.record.status;
    } finally {
        statusUpdating.value = false;
    }
};

/* ─── Formatting helpers ─── */
const formatDateTime = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return '—';
        const opts = {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        };
        if (props.account?.timezone) {
            opts.timeZone = props.account.timezone;
        }
        return d.toLocaleString('en-US', opts);
    } catch { return '—'; }
};
const formatDate = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return '—';
        const opts = { month: 'short', day: 'numeric', year: 'numeric' };
        if (props.account?.timezone) {
            opts.timeZone = props.account.timezone;
        }
        return d.toLocaleDateString('en-US', opts);
    } catch { return '—'; }
};

/** `formatDateTime` plus account IANA zone in parentheses when configured. */
const formatDateTimeWithZoneId = (v) => {
    const s = formatDateTime(v);
    if (!props.account?.timezone || s === '—') return s;
    return `${s} (${props.account.timezone})`;
};

const minutesFromSeconds = (sec) => {
    if (sec == null || !Number.isFinite(Number(sec)) || Number(sec) <= 0) {
        return null;
    }
    return Math.max(1, Math.round(Number(sec) / 60));
};

/** Outbound depot → customer (Google). */
const routingOutboundMin = computed(() => minutesFromSeconds(props.record?.estimated_travel_duration_seconds));

/** Return customer → depot; mirrors outbound when Google return not stored (legacy). */
const routingReturnMin = computed(() => {
    const r = props.record?.estimated_return_travel_duration_seconds;
    if (r != null && Number(r) > 0) {
        return minutesFromSeconds(r);
    }

    return routingOutboundMin.value;
});

const routingAtLocationMin = computed(() => {
    const m = props.record?.delivery_duration_minutes;
    if (m != null && Number(m) >= 1) {
        return Number(m);
    }
    return 15;
});

const routingTotalDrivingMin = computed(() => {
    const a = routingOutboundMin.value;
    const b = routingReturnMin.value;
    if (a == null && b == null) {
        return null;
    }
    return (a ?? 0) + (b ?? 0);
});

/* ─── Items ─── */
const items = computed(() => Array.isArray(props.record?.items) ? props.record.items : []);

const itemName = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    const variant = item.asset_variant ?? item.assetVariant ?? null;
    const assetDisplay = unit?.asset?.display_name;
    if (assetDisplay) return assetDisplay;
    if (variant?.display_name) return variant.display_name;
    return item.name ?? 'Asset';
};
const itemVariantLabel = (item) => {
    const v = item.asset_variant ?? item.assetVariant ?? null;
    return v?.display_name ?? v?.name ?? null;
};
const itemUnitLabel = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (!unit) return item.serial_number_snapshot ?? null;
    const raw = unit.display_name ?? null;
    if (raw) {
        const parts = String(raw).split(' - ');
        return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
    }
    return unit.serial_number ?? unit.hin ?? unit.sku ?? item.serial_number_snapshot ?? null;
};

const itemHullIdLabel = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (unit?.hin) {
        return unit.hin;
    }

    return item.serial_number_snapshot ?? unit?.serial_number ?? null;
};

const allItemsDelivered = computed(() => items.value.length > 0 && items.value.every(i => !!i.delivered_at));

/* ─── Related records sidebar ─── */
const relatedRecords = computed(() => {
    const r = props.record;
    const out = [];
    if (r?.customer?.id) {
        out.push({ label: 'Customer', name: r.customer.display_name ?? (r.customer.contact?.display_name) ?? `#${r.customer.id}`, href: route('customers.show', r.customer.id) });
    }
    if (r?.work_order?.id || r?.workOrder?.id) {
        const wo = r.work_order ?? r.workOrder;
        out.push({ label: 'Work Order', name: wo.display_name ?? `WO-${wo.work_order_number ?? wo.id}`, href: route('workorders.show', wo.id) });
    }
    if (r?.transaction?.id) {
        out.push({ label: 'Transaction', name: r.transaction.display_name ?? `#${r.transaction.id}`, href: route('transactions.show', r.transaction.id) });
    }
    const dloc = r?.delivery_location ?? r?.deliveryLocation;
    if (dloc?.id) {
        out.push({ label: 'Delivery Location', name: dloc.display_name ?? dloc.name, href: route('delivery-locations.show', dloc.id) });
    }
    if (r?.subsidiary?.id) {
        out.push({
            label: 'Subsidiary',
            name: r.subsidiary.display_name ?? `Subsidiary #${r.subsidiary.id}`,
            href: route('subsidiaries.show', r.subsidiary.id),
        });
    }
    if (r?.location?.id) {
        out.push({
            label: 'Depart from',
            name: r.location.display_name ?? `Location #${r.location.id}`,
            href: route('locations.show', r.location.id),
        });
    }
    return out;
});

const canMarkEnRoute = computed(
    () => !isSigned.value
        && ['scheduled', 'rescheduled'].includes(props.record?.status),
);

const destinationCompleteForTravel = computed(() => {
    const r = props.record;
    if (r?.latitude != null && r?.longitude != null) return true;
    const line1 = String(r?.address_line_1 ?? '').trim();
    const city = String(r?.city ?? '').trim();
    const state = String(r?.state ?? '').trim();
    return !!(line1 && city && state);
});

const showTravelComputeButton = computed(
    () => ['scheduled', 'rescheduled'].includes(props.record?.status)
        && props.record?.estimated_travel_duration_seconds == null,
);

const canRecalculateTravel = computed(
    () => ['scheduled', 'rescheduled'].includes(props.record?.status),
);

const travelComputeButtonLabel = computed(
    () => (props.record?.estimated_travel_duration_seconds == null
        ? 'Calculate driving times'
        : 'Recalculate driving times'),
);

const isPendingRequest = computed(() => props.record?.pending_request === true);
const isDeniedRequest = computed(() => props.record?.review_decision === 'denied');
const isCancelled = computed(
    () => props.record?.status === 'cancelled' && !isDeniedRequest.value,
);
const isRequestLimitedView = computed(
    () => isPendingRequest.value || isDeniedRequest.value || isCancelled.value,
);
const canPerformDeliveryActions = computed(() => !isRequestLimitedView.value);
const isRequestDelete = computed(() => isPendingRequest.value || isDeniedRequest.value);
const reviewNotes = ref('');
const proposedScheduledAt = ref('');
const reviewProcessing = ref(false);
const showRescheduleFields = ref(false);
const showDenyModal = ref(false);
const denyReason = ref('');
const denyReasonError = ref('');
const showCancelDeniedModal = ref(false);
const showFleetConflictModal = ref(false);
const showDriverConflictModal = ref(false);
const fleetConflicts = ref([]);
const driverConflicts = ref([]);
const driverUpcoming = ref([]);
const scheduleConflictMessage = ref('');

const serverUtcToLocalInput = (value) => {
    if (!value) return '';
    const m = dayjs(value);
    if (!m.isValid()) return '';
    return m.tz(accountTimezone.value).format('YYYY-MM-DDTHH:mm');
};

const accountDatetimeLocalToUtcIso = (value) => {
    if (!value?.trim()) return null;
    const m = dayjs.tz(value, accountTimezone.value);
    return m.isValid() ? m.utc().format() : null;
};

const approveRequest = async () => {
    if (!await ensureScheduleAllowsApproval()) {
        return;
    }

    reviewProcessing.value = true;
    router.post(route('deliveries.requests.approve', props.record.id), {
        review_notes: reviewNotes.value || null,
    }, { preserveScroll: true, onFinish: () => { reviewProcessing.value = false; } });
};

const buildApprovalSchedulePayload = () => ({
    technician_id: props.record?.technician_id,
    scheduled_at: props.record?.scheduled_at,
    time_to_leave_by: props.record?.time_to_leave_by,
    estimated_travel_duration_seconds: props.record?.estimated_travel_duration_seconds,
    estimated_return_travel_duration_seconds: props.record?.estimated_return_travel_duration_seconds,
    delivery_duration_minutes: props.record?.delivery_duration_minutes,
    exclude_delivery_id: props.record?.id,
});

const ensureScheduleAllowsApproval = async () => {
    if (!props.record?.technician_id || !props.record?.scheduled_at) {
        return true;
    }

    try {
        const { data } = await axios.post(route('deliveries.check-technician-schedule'), buildApprovalSchedulePayload());
        if (Array.isArray(data.conflicts) && data.conflicts.length > 0) {
            driverConflicts.value = data.conflicts;
            driverUpcoming.value = data.upcoming || [];
            scheduleConflictMessage.value = 'This driver already has overlapping deliveries at the proposed time. Change the schedule or driver before approving.';
            showDriverConflictModal.value = true;
            return false;
        }
    } catch {
        /* non-blocking if check fails */
    }

    return true;
};

const openFleetConflictModal = (conflicts, message = '') => {
    if (!Array.isArray(conflicts) || conflicts.length === 0) {
        return;
    }
    fleetConflicts.value = conflicts;
    scheduleConflictMessage.value = message || 'Fleet scheduling conflict: truck or trailer is already booked for this window.';
    showFleetConflictModal.value = true;
};

const handleScheduleConflictFlash = () => {
    const conflicts = page.props.flash?.delivery_fleet_conflicts;
    if (Array.isArray(conflicts) && conflicts.length > 0) {
        openFleetConflictModal(conflicts, page.props.flash?.error || '');
        return true;
    }

    return false;
};

watch(
    () => page.props.flash?.delivery_fleet_conflicts,
    (conflicts) => {
        if (Array.isArray(conflicts) && conflicts.length > 0) {
            openFleetConflictModal(conflicts, page.props.flash?.error || '');
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (!message || handleScheduleConflictFlash()) {
            return;
        }
        const root = inertiaApp?.appContext?.app?._instance?.proxy;
        if (typeof root?.createToast === 'function') {
            root.createToast('error', String(message));
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.success,
    (message) => {
        if (!message) {
            return;
        }
        const root = inertiaApp?.appContext?.app?._instance?.proxy;
        if (typeof root?.createToast === 'function') {
            root.createToast('success', String(message));
        }
    },
    { immediate: true },
);

const denyRequest = () => {
    denyReason.value = reviewNotes.value || '';
    denyReasonError.value = '';
    showDenyModal.value = true;
};

const closeDenyModal = () => {
    showDenyModal.value = false;
    denyReasonError.value = '';
};

const confirmDenyRequest = () => {
    if (!denyReason.value?.trim()) {
        denyReasonError.value = 'Please enter a reason for denying this request.';
        return;
    }

    reviewProcessing.value = true;
    router.post(route('deliveries.requests.deny', props.record.id), {
        review_notes: denyReason.value.trim(),
    }, {
        preserveScroll: true,
        onFinish: () => {
            reviewProcessing.value = false;
            closeDenyModal();
        },
    });
};

const cancelDeniedDelivery = () => {
    reviewProcessing.value = true;
    router.post(route('deliveries.requests.cancel', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            reviewProcessing.value = false;
            showCancelDeniedModal.value = false;
        },
    });
};

const proposeReschedule = () => {
    reviewProcessing.value = true;
    router.post(route('deliveries.requests.propose-reschedule', props.record.id), {
        review_notes: reviewNotes.value || null,
        proposed_scheduled_at: accountDatetimeLocalToUtcIso(proposedScheduledAt.value),
    }, {
        preserveScroll: true,
        onFinish: () => {
            reviewProcessing.value = false;
            showRescheduleFields.value = false;
        },
    });
};

const openProposeReschedule = () => {
    showRescheduleFields.value = true;
    if (!proposedScheduledAt.value && props.record?.scheduled_at) {
        proposedScheduledAt.value = serverUtcToLocalInput(props.record.scheduled_at);
    }
};

const cancelProposeReschedule = () => {
    showRescheduleFields.value = false;
};

const buildResubmitSchedulePayload = (scheduledAt) => ({
    scheduled_at: scheduledAt,
    time_to_leave_by: props.record?.time_to_leave_by ?? null,
    estimated_travel_duration_seconds: props.record?.estimated_travel_duration_seconds ?? null,
    estimated_return_travel_duration_seconds: props.record?.estimated_return_travel_duration_seconds ?? null,
    delivery_duration_minutes: props.record?.delivery_duration_minutes ?? null,
    exclude_delivery_id: props.record?.id,
});

const checkResubmitScheduleConflicts = async (scheduledAt) => {
    if (!scheduledAt) {
        return true;
    }

    const truckId = props.record?.fleet_truck_id ?? props.record?.fleetTruck?.id ?? null;
    const trailerId = props.record?.fleet_trailer_id ?? props.record?.fleetTrailer?.id ?? null;

    if (truckId || trailerId) {
        try {
            const { data } = await axios.post(route('deliveries.check-fleet-schedule'), {
                ...buildResubmitSchedulePayload(scheduledAt),
                fleet_truck_id: truckId,
                fleet_trailer_id: trailerId,
                location_id: props.record?.location_id ?? null,
            });
            if (Array.isArray(data.conflicts) && data.conflicts.length > 0) {
                fleetConflicts.value = data.conflicts;
                scheduleConflictMessage.value = 'Truck or trailer is already booked for this window. Update the request with a different time or fleet assignments before resubmitting.';
                showFleetConflictModal.value = true;

                return false;
            }
        } catch {
            /* non-blocking if check fails */
        }
    }

    if (props.record?.technician_id) {
        try {
            const { data } = await axios.post(route('deliveries.check-technician-schedule'), {
                ...buildResubmitSchedulePayload(scheduledAt),
                technician_id: props.record.technician_id,
            });
            if (Array.isArray(data.conflicts) && data.conflicts.length > 0) {
                driverConflicts.value = data.conflicts;
                driverUpcoming.value = data.upcoming || [];
                scheduleConflictMessage.value = 'This driver has overlapping deliveries at the selected time. Choose a different time or update the driver before resubmitting.';
                showDriverConflictModal.value = true;

                return false;
            }
        } catch {
            /* non-blocking if check fails */
        }
    }

    return true;
};

const resubmitRequest = async () => {
    const local = proposedScheduledAt.value || serverUtcToLocalInput(props.record?.proposed_scheduled_at);
    const scheduledAt = accountDatetimeLocalToUtcIso(local);
    if (!scheduledAt) {
        return;
    }

    if (!await checkResubmitScheduleConflicts(scheduledAt)) {
        return;
    }

    reviewProcessing.value = true;
    router.post(route('deliveries.requests.resubmit', props.record.id), {
        scheduled_at: scheduledAt,
    }, { preserveScroll: true, onFinish: () => { reviewProcessing.value = false; } });
};

watch(
    () => props.record?.proposed_scheduled_at,
    (v) => {
        if (v && !proposedScheduledAt.value) {
            proposedScheduledAt.value = serverUtcToLocalInput(v);
        }
    },
    { immediate: true },
);

watch(
    () => [props.record?.scheduled_at, isDeniedRequest.value],
    ([scheduledAt, denied]) => {
        if (denied && scheduledAt && !proposedScheduledAt.value) {
            proposedScheduledAt.value = serverUtcToLocalInput(scheduledAt);
        }
    },
    { immediate: true },
);

const travelComputeReady = computed(
    () => !!(props.record?.location_id && props.record?.scheduled_at && destinationCompleteForTravel.value),
);

const travelComputeDisabledTitle = computed(() => {
    if (travelComputeReady.value) return '';
    return 'Set a departure location, scheduled date and time, and a complete delivery address (or map coordinates) first.';
});

const computeTravelLoading = ref(false);
const computeTravel = () => {
    if (!travelComputeReady.value || computeTravelLoading.value) return;
    computeTravelLoading.value = true;
    router.post(
        route('deliveries.compute-travel', props.record.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => { computeTravelLoading.value = false; },
        },
    );
};

const showEnRouteModal = ref(false);
const enRouteDeliveryChoice = ref('no_sms');

const enRouteLoading = ref(false);
const submitEnRoute = (notifySms) => {
    enRouteLoading.value = true;
    router.post(
        route('deliveries.en-route', props.record.id),
        { notify_sms: notifySms },
        {
            preserveScroll: true,
            onFinish: () => { enRouteLoading.value = false; },
        },
    );
};

const goEnRoute = () => {
    if (!canMarkEnRoute.value) return;
    if (deliverySmsEnabledForEnRoute.value) {
        enRouteDeliveryChoice.value = 'no_sms';
        showEnRouteModal.value = true;
        return;
    }
    submitEnRoute(false);
};

const closeEnRouteModal = () => {
    showEnRouteModal.value = false;
};

const confirmEnRouteModal = () => {
    const wantsSms = enRouteDeliveryChoice.value === 'sms';
    if (wantsSms && !props.deliveryEnRouteSms?.offered) return;
    showEnRouteModal.value = false;
    submitEnRoute(wantsSms && !!props.deliveryEnRouteSms?.offered);
};

/* ─── Arrived at delivery (optional customer SMS) ─── */
const showArrivedModal = ref(false);
const arrivedDeliveryChoice = ref('no_sms');
const arrivedLoading = ref(false);

const deliveryDriverDisplayForArrived = computed(() => {
    const t = props.record?.technician;
    if (!t) return 'your delivery driver';
    return t.display_name || [t.first_name, t.last_name].filter(Boolean).join(' ').trim() || 'your delivery driver';
});

const canShowArrivedAtDelivery = computed(
    () => !isSigned.value
        && props.record?.status === 'en_route'
        && !props.record?.customer_arrived_notified_at,
);

const submitArrived = (notifySms) => {
    arrivedLoading.value = true;
    router.post(
        route('deliveries.notify-arrived', props.record.id),
        { notify_sms: notifySms },
        {
            preserveScroll: true,
            onFinish: () => { arrivedLoading.value = false; },
        },
    );
};

const goArrived = () => {
    if (!canShowArrivedAtDelivery.value) return;
    arrivedDeliveryChoice.value = 'no_sms';
    showArrivedModal.value = true;
};

const closeArrivedModal = () => {
    showArrivedModal.value = false;
};

const confirmArrivedModal = () => {
    const wantsSms = arrivedDeliveryChoice.value === 'sms';
    if (wantsSms && !props.deliveryArrivedSms?.offered) return;
    showArrivedModal.value = false;
    submitArrived(wantsSms && !!props.deliveryArrivedSms?.offered);
};

const confirmArrivedWithoutSms = () => {
    showArrivedModal.value = false;
    submitArrived(false);
};

/* ─── Actions ─── */
const markAsDelivered = () => {
    if (!canPerformDeliveryActions.value || props.record?.delivered_at) {
        return;
    }
    showMarkDeliveredModal.value = true;
};

const markDeliveredWithoutSignature = async () => {
    try {
        await axios.post(route('deliveries.mark-delivered', props.record.id));
        showMarkDeliveredModal.value = false;
        router.reload();
    } catch (error) {
        console.error(error);
        alert('Failed to mark delivery as completed. Please try again.');
    }
};

const sendSignatureRequest = () => {
    showMarkDeliveredModal.value = false;
    showPreview.value = true;
};

const viewSignatureRequest = () => {
    window.open(route('deliveries.review', props.record.uuid), '_blank');
};

const openPreview = () => {
    if (!canPerformDeliveryActions.value) {
        return;
    }
    showPreview.value = true;
};
const closePreview = () => { showPreview.value = false; };

const openPrint = () => {
    if (!canPerformDeliveryActions.value) {
        return;
    }
    const url = route('deliveries.print', props.record.id);
    if (externalLinkTarget.value === '_self') {
        window.location.assign(url);
        return;
    }
    window.open(url, '_blank', 'noopener,noreferrer');
};

const handleDelete = () => { showDeleteModal.value = true; };
const cancelDelete = () => { showDeleteModal.value = false; };
const confirmDelete = () => {
    isDeleting.value = true;
    const redirectTo = isRequestDelete.value
        ? route('deliveries.requests.index')
        : route('deliveries.index');
    router.delete(route('deliveries.destroy', recordIdentifier.value), {
        onSuccess: () => router.visit(redirectTo),
        onError: () => { isDeleting.value = false; },
        onFinish: () => { isDeleting.value = false; showDeleteModal.value = false; },
    });
};

const toggleItemDelivered = async (item) => {
    const delivered = !item.delivered_at;
    try {
        await axios.post(route('deliveries.items.mark-delivered', { delivery: props.record.id, item: item.id }), {
            delivered,
        });
        router.reload({ only: ['record', 'deliveryEnRouteSms', 'deliveryArrivedSms'] });
    } catch (error) {
        console.error(error);
        alert('Failed to update item.');
    }
};

/* ─── Checklist helpers (kept from original) ─── */
const CHECKLIST_CATEGORY_SORT_ORDER = ['Pre Delivery Checklist', 'Upon Delivery'];

const itemsByCategory = computed(() => {
    const grouped = {};
    (props.checklistItems || []).forEach((item) => {
        const catId = item.category_id ?? item.category?.id ?? 'uncategorized';
        const catName = item.category?.name ?? 'Other';
        if (!grouped[catId]) grouped[catId] = { id: catId, name: catName, items: [] };
        grouped[catId].items.push(item);
    });
    const list = Object.values(grouped);
    list.sort((a, b) => {
        const ia = CHECKLIST_CATEGORY_SORT_ORDER.indexOf(a.name);
        const ib = CHECKLIST_CATEGORY_SORT_ORDER.indexOf(b.name);
        if (ia !== -1 || ib !== -1) {
            if (ia === -1) return 1;
            if (ib === -1) return -1;
            return ia - ib;
        }
        return a.name.localeCompare(b.name);
    });
    return list;
});

const openChecklistModal = () => {
    showChecklistModal.value = true;
    checklistCreationMode.value = 'template';
    selectedTemplate.value = null;
    newChecklistItems.value = [];
};
const closeChecklistModal = () => { showChecklistModal.value = false; };
const selectChecklistMode = (mode) => {
    checklistCreationMode.value = mode;
    if (mode === 'scratch') {
        newChecklistItems.value = [{ label: '', category: firstChecklistCategoryName(), is_required: false, sort_order: 0 }];
    }
};
const addChecklistItem = () => newChecklistItems.value.push({
    label: '',
    category: firstChecklistCategoryName(),
    is_required: false,
    sort_order: newChecklistItems.value.length,
});
const removeChecklistItem = (idx) => newChecklistItems.value.splice(idx, 1);

const saveChecklist = async () => {
    isLoadingChecklist.value = true;
    try {
        if (checklistCreationMode.value === 'template' && selectedTemplate.value) {
            await axios.post(route('deliveries.checklist.store', { delivery: props.record.id }), { template_id: selectedTemplate.value.id });
        } else if (checklistCreationMode.value === 'scratch') {
            const valid = newChecklistItems.value.filter(i => i.label.trim());
            if (valid.length) {
                await axios.post(route('deliveries.checklist.store', { delivery: props.record.id }), { items: valid });
            }
        }
        router.reload();
        closeChecklistModal();
    } catch (e) { console.error(e); }
    finally { isLoadingChecklist.value = false; }
};

const addChecklistItemToDelivery = () => {
    editingChecklistItem.value = null;
    newItemLabel.value = '';
    newItemCategory.value = props.categories.length > 0 ? props.categories[0].name : '';
    newItemRequired.value = false;
    showAddItemModal.value = true;
};
const editChecklistItemOnDelivery = (item) => {
    editingChecklistItem.value = item;
    newItemLabel.value = item.label ?? '';
    newItemCategory.value = item.category?.name
        ?? (props.categories.length > 0 ? props.categories[0].name : '');
    newItemRequired.value = !!item.is_required;
    showAddItemModal.value = true;
};
const closeAddItemModal = () => {
    showAddItemModal.value = false;
    editingChecklistItem.value = null;
};
const saveNewChecklistItem = async () => {
    if (!newItemLabel.value.trim()) return;
    try {
        if (editingChecklistItem.value) {
            await axios.put(route('deliveries.checklist.update-item', {
                delivery: props.record.id,
                item: editingChecklistItem.value.id,
            }), {
                label: newItemLabel.value.trim(),
                category: newItemCategory.value,
                is_required: newItemRequired.value,
            });
        } else {
            await axios.post(route('deliveries.checklist.add-item', { delivery: props.record.id }), {
                label: newItemLabel.value.trim(),
                category: newItemCategory.value,
                is_required: newItemRequired.value,
            });
        }
        closeAddItemModal();
        router.reload();
    } catch (e) {
        console.error(e);
        alert(editingChecklistItem.value ? 'Failed to update item.' : 'Failed to add item.');
    }
};
const removeChecklistItemFromDelivery = async (item) => {
    if (!confirm(`Remove "${item.label}" from checklist?`)) return;
    try {
        await axios.delete(route('deliveries.checklist.remove-item', { delivery: props.record.id, item: item.id }));
        router.reload();
    } catch (e) { console.error(e); alert('Failed to remove item.'); }
};
const toggleChecklistItemCompleted = async (item) => {
    const next = !item.completed;
    item.completed = next;
    try {
        await axios.put(route('deliveries.checklist.update-item', { delivery: props.record.id, item: item.id }), { completed: next });
        router.reload({ only: ['checklistItems'] });
    } catch (e) {
        console.error(e);
        item.completed = !next;
    }
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: props.record?.display_name ?? 'Delivery' },
]);

const deliveryLabel = computed(() => props.record?.display_name ?? `Delivery #${props.record?.id ?? ''}`);

const isDelivered = computed(() => props.record?.status === 'delivered');

const transactionShowHref = computed(() => {
    const id = props.record?.transaction?.id ?? props.record?.transaction_id;
    return id ? route('transactions.show', id) : null;
});

const showDeliveredModal = ref(false);

const closeDeliveredModal = () => {
    showDeliveredModal.value = false;
};

onMounted(() => {
    if (isDelivered.value) {
        showDeliveredModal.value = true;
    }
});

const deliverToSummary = computed(() => {
    const r = props.record;
    if (!r) return null;
    if (r.delivery_to_type === 'delivery_location' && (r.delivery_location ?? r.deliveryLocation)) {
        const d = r.delivery_location ?? r.deliveryLocation;
        return { type: 'Common Location', name: d.display_name ?? d.name };
    }
    if (r.delivery_to_type === 'contact_address') {
        return { type: 'Customer Address', name: null };
    }
    return { type: 'Custom Address', name: null };
});

const addressSummary = computed(() => {
    const r = props.record;
    if (!r) return '—';
    const parts = [r.address_line_1, r.city, r.state, r.postal_code].filter(Boolean);
    return parts.join(', ') || '—';
});

const locationRecord = computed(() => props.record?.location ?? null);

const fleetTruckRecord = computed(() => {
    const r = props.record;
    const loaded = r?.fleet_truck ?? r?.fleetTruck;
    if (loaded) return loaded;
    const id = r?.fleet_truck_id;
    return id != null && id !== '' ? { id, display_name: null, license_plate: null } : null;
});
const fleetTrailerRecord = computed(() => {
    const r = props.record;
    const loaded = r?.fleet_trailer ?? r?.fleetTrailer;
    if (loaded) return loaded;
    const id = r?.fleet_trailer_id;
    return id != null && id !== '' ? { id, display_name: null, license_plate: null } : null;
});

const fleetUnitShowLabel = (unit) => {
    if (!unit) return null;
    const name = unit.display_name != null ? String(unit.display_name).trim() : '';
    if (name) return name;
    const plate = unit.license_plate != null ? String(unit.license_plate).trim() : '';
    if (plate) return plate;
    return unit.id != null ? `Unit #${unit.id}` : null;
};

const fleetTruckLabel = computed(() => fleetUnitShowLabel(fleetTruckRecord.value));
const fleetTrailerLabel = computed(() => fleetUnitShowLabel(fleetTrailerRecord.value));

const hasLocationAddress = computed(() => {
    const loc = locationRecord.value;
    if (!loc) return false;
    return !!(
        (loc.address_line_1 && String(loc.address_line_1).trim())
        || (loc.city && String(loc.city).trim())
    );
});

const formatAddressForMaps = (o) => {
    if (!o) return null;
    const parts = [o.address_line_1, o.address_line_2, o.city, o.state, o.postal_code, o.country]
        .map((p) => (p == null ? '' : String(p).trim()))
        .filter(Boolean);
    if (parts.length) {
        return parts.join(', ');
    }
    if (o.display_name && String(o.display_name).trim()) {
        return String(o.display_name).trim();
    }
    if (o.name && String(o.name).trim()) {
        return String(o.name).trim();
    }
    return null;
};

/** Prefer lat,lng; otherwise a full address or place name for Google to resolve. */
const mapsPointForLocation = (loc) => {
    if (!loc) return null;
    const lat = loc.latitude;
    const lng = loc.longitude;
    if (lat != null && lat !== '' && lng != null && lng !== '') {
        return `${String(lat).trim()},${String(lng).trim()}`;
    }
    return formatAddressForMaps(loc);
};

const mapsPointForDeliveryDestination = (r) => {
    if (!r) return null;
    const lat = r.latitude;
    const lng = r.longitude;
    if (lat != null && lat !== '' && lng != null && lng !== '') {
        return `${String(lat).trim()},${String(lng).trim()}`;
    }
    const fromSnapshot = formatAddressForMaps(r);
    if (fromSnapshot) {
        return fromSnapshot;
    }
    const dloc = r.delivery_location ?? r.deliveryLocation;
    if (dloc) {
        return formatAddressForMaps(dloc) ?? null;
    }
    return null;
};

const googleMapsDirectionsUrl = computed(() => {
    const origin = mapsPointForLocation(locationRecord.value);
    const dest = mapsPointForDeliveryDestination(props.record);
    if (!origin || !dest) return null;
    return `https://www.google.com/maps/dir/?${new URLSearchParams({
        api: '1',
        origin,
        destination: dest,
        travelmode: 'driving',
    }).toString()}`;
});
</script>

<template>
    <Head :title="`Delivery - ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full min-w-0 w-full max-w-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex min-w-0 max-w-full flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <h2 class="min-w-0 text-xl font-semibold text-gray-800 dark:text-gray-200 sm:max-w-[min(100%,20rem)] truncate">
                            {{ record?.display_name }}
                        </h2>
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span
                                v-if="isPendingRequest"
                                class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                            >
                                Pending request
                            </span>
                            <span
                                v-else-if="isDeniedRequest"
                                class="inline-flex shrink-0 items-center rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-900 dark:bg-red-950 dark:text-red-100"
                            >
                                Request denied
                            </span>
                            <span
                                v-else-if="isCancelled"
                                class="inline-flex shrink-0 items-center rounded-full bg-gray-200 px-3 py-1 text-sm font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200"
                            >
                                Cancelled
                            </span>
                            <template v-else>
                            <label for="delivery-status-select" class="sr-only">Delivery status</label>
                            <select
                                id="delivery-status-select"
                                :value="record.status"
                                :disabled="isSigned || statusUpdating"
                                class="input-style w-full min-w-0 max-w-full py-2 text-md sm:w-auto sm:max-w-[14rem] disabled:cursor-not-allowed disabled:opacity-60"
                                @change="updateDeliveryStatus"
                            >
                                <option
                                    v-for="opt in statusOptionsForSelect"
                                    :key="String(statusOptionValue(opt))"
                                    :value="statusOptionValue(opt)"
                                >
                                    {{ statusOptionLabel(opt) }}
                                </option>
                            </select>
                            <span
                                v-if="statusUpdating"
                                class="material-icons text-xl text-primary-600 animate-spin"
                                aria-hidden="true"
                            >sync</span>
                            <span
                                v-if="isSigned"
                                class="shrink-0 text-sm text-gray-500 dark:text-gray-400"
                            >Locked (signed)</span>
                            </template>
                        </div>
                    </div>
                    <div :class="['flex shrink-0 flex-wrap items-center gap-2', headerActionsClass]">
                        <template v-if="isPendingRequest">
                            <Link
                                v-if="canUpdateRequest"
                                :href="route('deliveries.requests.edit', record.id)"
                                class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
                            >
                                <span class="material-icons text-lg">edit</span>
                                Update request
                            </Link>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                                @click="handleDelete"
                            >
                                <span class="material-icons text-lg">delete</span>
                                Delete
                            </button>
                        </template>
                        <template v-else-if="isDeniedRequest">
                            <Link
                                v-if="canUpdateRequest"
                                :href="route('deliveries.requests.edit', record.id)"
                                class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
                            >
                                <span class="material-icons text-lg">edit</span>
                                Update request
                            </Link>
                            <button
                                v-if="canCancelDeniedRequest"
                                type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                                @click="showCancelDeniedModal = true"
                            >
                                <span class="material-icons text-lg">cancel</span>
                                Cancel delivery
                            </button>
                            <button
                                v-if="canUpdateRequest"
                                type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                                @click="handleDelete"
                            >
                                <span class="material-icons text-lg">delete</span>
                                Delete request
                            </button>
                        </template>
                        <template v-else-if="isCancelled">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                                @click="handleDelete"
                            >
                                <span class="material-icons text-lg">delete</span>
                                Delete
                            </button>
                        </template>
                        <template v-else-if="canPerformDeliveryActions">
                        <button
                            @click="openPreview"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-lg">visibility</span>
                            Preview
                        </button>
                        <a
                            :href="route('deliveries.print', record.id)"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-lg">print</span>
                            Print
                        </a>
                        <Link
                            :href="route('deliveries.edit', record.id)"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
                        >
                            <span class="material-icons text-lg">edit</span>
                            Edit
                        </Link>
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                        >
                            <span class="material-icons text-lg">delete</span>
                            Delete
                        </button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <MobileActionBar>
            <template #actions>
                <template v-if="isPendingRequest">
                    <MobileActionBarButton
                        v-if="canUpdateRequest"
                        label="Update request"
                        :href="route('deliveries.requests.edit', record.id)"
                    >
                        <span class="material-icons leading-none">edit</span>
                    </MobileActionBarButton>
                    <MobileActionBarButton
                        label="Delete request"
                        @click="handleDelete"
                    >
                        <span class="material-icons leading-none">delete</span>
                    </MobileActionBarButton>
                </template>
                <template v-else-if="isDeniedRequest">
                    <MobileActionBarButton
                        v-if="canUpdateRequest"
                        label="Update request"
                        :href="route('deliveries.requests.edit', record.id)"
                    >
                        <span class="material-icons leading-none">edit</span>
                    </MobileActionBarButton>
                    <MobileActionBarButton
                        v-if="canCancelDeniedRequest"
                        label="Cancel delivery"
                        @click="showCancelDeniedModal = true"
                    >
                        <span class="material-icons leading-none">cancel</span>
                    </MobileActionBarButton>
                    <MobileActionBarButton
                        v-if="canUpdateRequest"
                        label="Delete request"
                        @click="handleDelete"
                    >
                        <span class="material-icons leading-none">delete</span>
                    </MobileActionBarButton>
                </template>
                <template v-else-if="isCancelled">
                    <MobileActionBarButton
                        label="Delete delivery"
                        @click="handleDelete"
                    >
                        <span class="material-icons leading-none">delete</span>
                    </MobileActionBarButton>
                </template>
                <template v-else-if="canPerformDeliveryActions">
                <MobileActionBarButton
                    label="Preview delivery"
                    @click="openPreview"
                >
                    <span class="material-icons leading-none">visibility</span>
                </MobileActionBarButton>
                <MobileActionBarButton
                    label="Print delivery"
                    @click="openPrint"
                >
                    <span class="material-icons leading-none">print</span>
                </MobileActionBarButton>
                <MobileActionBarButton
                    label="Edit delivery"
                    :href="route('deliveries.edit', record.id)"
                >
                    <span class="material-icons leading-none">edit</span>
                </MobileActionBarButton>
                <MobileActionBarButton
                    label="Delete delivery"
                    @click="handleDelete"
                >
                    <span class="material-icons leading-none">delete</span>
                </MobileActionBarButton>
                </template>
            </template>

        <div class="grid min-w-0 max-w-full grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Main content -->
            <div class="min-w-0 space-y-6 lg:col-span-8">
                <!-- Pending request summary -->
                <div
                    v-if="isPendingRequest"
                    class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/30"
                >
                    <div class="border-b border-amber-200 px-6 py-4 dark:border-amber-800/50">
                        <h3 class="text-lg font-semibold text-amber-950 dark:text-amber-100">Pending delivery request</h3>
                        <p class="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                            Requested by {{ record.requested_by?.display_name ?? '—' }}
                            <span v-if="record.requested_at"> on {{ formatDateTime(record.requested_at) }}</span>
                        </p>
                    </div>
                    <dl class="divide-y divide-amber-200/80 dark:divide-amber-800/40">
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-amber-900/70 dark:text-amber-200/70">Customer</dt>
                            <dd class="sm:col-span-2 text-sm font-medium text-amber-950 dark:text-amber-50">
                                {{ record.customer?.display_name ?? record.customer?.contact?.display_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-amber-900/70 dark:text-amber-200/70">Depart from</dt>
                            <dd class="sm:col-span-2 text-sm text-amber-950 dark:text-amber-50">
                                {{ record.location?.display_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-amber-900/70 dark:text-amber-200/70">Deliver to</dt>
                            <dd class="sm:col-span-2 text-sm text-amber-950 dark:text-amber-50">
                                {{ deliverToSummary?.type ?? 'Custom address' }}
                                <span v-if="deliverToSummary?.name"> · {{ deliverToSummary.name }}</span>
                                <div v-if="record.address_line_1" class="mt-1 font-normal text-amber-900/80 dark:text-amber-200/80">
                                    {{ addressSummary }}
                                </div>
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-amber-900/70 dark:text-amber-200/70">Scheduled</dt>
                            <dd class="sm:col-span-2 text-sm font-semibold text-amber-950 dark:text-amber-50">
                                {{ formatDateTimeWithZoneId(record.scheduled_at) }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-amber-900/70 dark:text-amber-200/70">Driver</dt>
                            <dd class="sm:col-span-2 text-sm text-amber-950 dark:text-amber-50">
                                {{ record.technician?.display_name ?? record.technician?.name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-amber-900/70 dark:text-amber-200/70">Assets</dt>
                            <dd class="sm:col-span-2 text-sm text-amber-950 dark:text-amber-50">
                                <span v-if="!items.length">No assets listed</span>
                                <ul v-else class="space-y-1">
                                    <li v-for="item in items" :key="item.id">{{ itemName(item) }}</li>
                                </ul>
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="record.review_decision === 'reschedule_requested'"
                        class="border-t border-amber-200 px-6 py-4 dark:border-amber-800/50"
                    >
                        <p class="text-sm font-medium text-amber-950 dark:text-amber-100">Approver requested a new time</p>
                        <p v-if="record.proposed_scheduled_at" class="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                            Proposed: {{ formatDateTime(record.proposed_scheduled_at) }}
                        </p>
                        <p v-if="record.review_notes" class="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">{{ record.review_notes }}</p>
                    </div>
                </div>

                <!-- Denied request summary -->
                <div
                    v-if="isDeniedRequest"
                    class="overflow-hidden rounded-lg border border-red-200 bg-red-50 shadow-sm dark:border-red-800/50 dark:bg-red-950/30"
                >
                    <div class="border-b border-red-200 px-6 py-4 dark:border-red-800/50">
                        <h3 class="text-lg font-semibold text-red-950 dark:text-red-100">Delivery request denied</h3>
                        <p class="mt-1 text-sm text-red-900/80 dark:text-red-200/80">
                            Denied by {{ record.reviewed_by?.display_name ?? '—' }}
                            <span v-if="record.reviewed_at"> on {{ formatDateTime(record.reviewed_at) }}</span>
                        </p>
                        <p
                            v-if="record.status === 'cancelled'"
                            class="mt-2 text-sm text-red-900/90 dark:text-red-100/90"
                        >
                            This request is marked cancelled, but you can update the schedule, driver, or fleet assignments and resubmit for approval.
                        </p>
                    </div>
                    <div class="border-b border-red-200 px-6 py-4 dark:border-red-800/50">
                        <p class="text-sm font-medium text-red-950 dark:text-red-100">Reason</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-red-900/90 dark:text-red-100/90">
                            {{ record.review_notes || 'No reason provided.' }}
                        </p>
                    </div>
                    <dl class="divide-y divide-red-200/80 dark:divide-red-800/40">
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Customer</dt>
                            <dd class="sm:col-span-2 text-sm font-medium text-red-950 dark:text-red-50">
                                {{ record.customer?.display_name ?? record.customer?.contact?.display_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Depart from</dt>
                            <dd class="sm:col-span-2 text-sm text-red-950 dark:text-red-50">
                                {{ record.location?.display_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Deliver to</dt>
                            <dd class="sm:col-span-2 text-sm text-red-950 dark:text-red-50">
                                {{ deliverToSummary?.type ?? 'Custom address' }}
                                <span v-if="deliverToSummary?.name"> · {{ deliverToSummary.name }}</span>
                                <div v-if="record.address_line_1" class="mt-1 font-normal text-red-900/80 dark:text-red-200/80">
                                    {{ addressSummary }}
                                </div>
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Requested time</dt>
                            <dd class="sm:col-span-2 text-sm font-semibold text-red-950 dark:text-red-50">
                                {{ formatDateTimeWithZoneId(record.scheduled_at) }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Driver</dt>
                            <dd class="sm:col-span-2 text-sm text-red-950 dark:text-red-50">
                                {{ record.technician?.display_name ?? record.technician?.name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Truck</dt>
                            <dd class="sm:col-span-2 text-sm text-red-950 dark:text-red-50">
                                {{ fleetTruckLabel || '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Trailer</dt>
                            <dd class="sm:col-span-2 text-sm text-red-950 dark:text-red-50">
                                {{ fleetTrailerLabel || '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-red-900/70 dark:text-red-200/70">Assets</dt>
                            <dd class="sm:col-span-2 text-sm text-red-950 dark:text-red-50">
                                <span v-if="!items.length">No assets listed</span>
                                <ul v-else class="space-y-2">
                                    <li v-for="item in items" :key="item.id">
                                        <div class="font-medium">{{ itemName(item) }}</div>
                                        <div
                                            v-if="itemVariantLabel(item) || itemHullIdLabel(item)"
                                            class="mt-0.5 text-xs font-normal text-red-900/75 dark:text-red-200/75"
                                        >
                                            <span v-if="itemVariantLabel(item)">{{ itemVariantLabel(item) }}</span>
                                            <template v-if="itemVariantLabel(item) && itemHullIdLabel(item)"> · </template>
                                            <span v-if="itemHullIdLabel(item)">Hull ID: {{ itemHullIdLabel(item) }}</span>
                                        </div>
                                    </li>
                                </ul>
                            </dd>
                        </div>
                    </dl>
                    <div v-if="canResubmitRequest || canUpdateRequest || canCancelDeniedRequest" class="space-y-3 border-t border-red-200 px-6 py-4 dark:border-red-800/50">
                        <p class="text-sm font-medium text-red-950 dark:text-red-100">
                            Update the schedule, driver, truck, or trailer and resubmit for approval.
                        </p>
                        <Link
                            v-if="canUpdateRequest"
                            :href="route('deliveries.requests.edit', record.id)"
                            class="btn-primary inline-flex items-center justify-center gap-2"
                        >
                            <span class="material-icons text-lg">edit</span>
                            Update &amp; resubmit request
                        </Link>
                        <div v-if="canResubmitRequest" class="space-y-2 rounded-lg border border-red-200/80 bg-white/60 p-4 dark:border-red-800/50 dark:bg-red-950/20">
                            <p class="text-xs font-medium uppercase tracking-wide text-red-900/70 dark:text-red-200/70">
                                Quick resubmit — new time only
                            </p>
                            <input
                                v-model="proposedScheduledAt"
                                type="datetime-local"
                                class="input-style w-full text-sm"
                                placeholder="New proposed schedule"
                            />
                            <button
                                type="button"
                                class="btn-primary w-full sm:w-auto"
                                :disabled="reviewProcessing || !proposedScheduledAt"
                                @click="resubmitRequest"
                            >
                                Resubmit for approval
                            </button>
                        </div>
                        <button
                            v-if="canCancelDeniedRequest"
                            type="button"
                            class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:bg-red-950/40 dark:text-red-100 dark:hover:bg-red-900/40"
                            :disabled="reviewProcessing"
                            @click="showCancelDeniedModal = true"
                        >
                            Cancel delivery
                        </button>
                        <button
                            v-if="canUpdateRequest"
                            type="button"
                            class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:bg-red-950/40 dark:text-red-100 dark:hover:bg-red-900/40"
                            @click="handleDelete"
                        >
                            Delete request
                        </button>
                    </div>
                </div>

                <!-- Cancelled delivery summary -->
                <div
                    v-if="isCancelled"
                    class="overflow-hidden rounded-lg border border-gray-300 bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-gray-900/40"
                >
                    <div class="border-b border-gray-300 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delivery cancelled</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            This delivery is no longer active. Preview, print, and completion actions are unavailable.
                        </p>
                    </div>
                    <div
                        v-if="record.review_notes"
                        class="border-b border-gray-300 px-6 py-4 dark:border-gray-700"
                    >
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Notes</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ record.review_notes }}</p>
                    </div>
                    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Customer</dt>
                            <dd class="sm:col-span-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ record.customer?.display_name ?? record.customer?.contact?.display_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Scheduled</dt>
                            <dd class="sm:col-span-2 text-sm text-gray-900 dark:text-gray-100">
                                {{ formatDateTimeWithZoneId(record.scheduled_at) }}
                            </dd>
                        </div>
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Driver</dt>
                            <dd class="sm:col-span-2 text-sm text-gray-900 dark:text-gray-100">
                                {{ record.technician?.display_name ?? record.technician?.name ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <template v-if="!isRequestLimitedView">
                <!-- Summary card -->
                <div class="min-w-0 overflow-hidden divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">

<!-- Header: Customer -->
<div class="flex flex-col gap-3 bg-white px-4 py-5 dark:bg-gray-800 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-6">
  <div class="flex min-w-0 items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-950 flex items-center justify-center text-md font-medium text-blue-600 dark:text-blue-400 shrink-0">
      {{ record.customer?.display_name?.slice(0,2).toUpperCase() ?? '??' }}
    </div>
    <div class="min-w-0">
      <p class="m-0 truncate text-md font-semibold text-gray-900 dark:text-white">
        {{ record.customer?.display_name ?? record.customer?.contact?.display_name ?? '—' }}
      </p>
      <p class="min-w-0 break-words text-sm text-gray-500 dark:text-gray-400">
        <span v-if="record.customer?.contact?.email">{{ record.customer.contact.email }}</span>
        <span v-if="record.customer?.contact?.email && record.customer?.contact?.phone"> · </span>
        <span v-if="record.customer?.contact?.phone">{{ record.customer.contact.phone }}</span>
      </p>
    </div>
  </div>
  <span class="shrink-0 self-start text-sm font-medium tracking-wide text-green-700 dark:text-green-400 rounded-full bg-green-50 px-3 py-1 dark:bg-green-950">
    {{ recordStatusLabel }}
  </span>
</div>

<!-- Middle: location / from / subsidiary -->
<div class="grid grid-cols-1 divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800 md:grid-cols-2 md:divide-x md:divide-y-0 xl:grid-cols-3">

  <div class="min-w-0 px-4 py-5 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Deliver to</p>
    <p class="text-md font-semibold text-gray-900 dark:text-white mb-1">
      {{ deliverToSummary?.type ?? 'Custom Address' }}
      <span v-if="deliverToSummary?.name" class="font-normal text-gray-500"> · {{ deliverToSummary.name }}</span>
    </p>
    <div v-if="record.address_line_1" class="break-words text-sm leading-relaxed text-gray-500 dark:text-gray-400">
      <div>{{ record.address_line_1 }}</div>
      <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
      <div>
        <span v-if="record.city">{{ record.city }}</span><span v-if="record.state">, {{ record.state }}</span><span v-if="record.postal_code"> {{ record.postal_code }}</span>
      </div>
    </div>
    <div v-else class="text-sm text-gray-400 italic">No address on file</div>
  </div>

  <div class="min-w-0 px-4 py-5 sm:px-6 md:border-l md:border-gray-200 dark:md:border-gray-700">
    <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Delivered from</p>
    <div v-if="locationRecord">
      <p class="text-md font-semibold mb-1">
        <Link v-if="locationRecord.id" :href="route('locations.show', locationRecord.id)" class="text-primary-600 hover:text-primary-500 dark:text-primary-400">
          {{ locationRecord.display_name ?? `Location #${locationRecord.id}` }}
        </Link>
        <span v-else class="text-gray-900 dark:text-white">{{ locationRecord.display_name ?? '—' }}</span>
      </p>
      <div v-if="hasLocationAddress" class="break-words text-sm leading-relaxed text-gray-500 dark:text-gray-400">
        <div v-if="locationRecord.address_line_1">{{ locationRecord.address_line_1 }}</div>
        <div v-if="locationRecord.address_line_2">{{ locationRecord.address_line_2 }}</div>
        <div>
          <span v-if="locationRecord.city">{{ locationRecord.city }}</span><span v-if="locationRecord.state">, {{ locationRecord.state }}</span><span v-if="locationRecord.postal_code"> {{ locationRecord.postal_code }}</span>
        </div>
        <div v-if="locationRecord.country" class="text-gray-400">{{ locationRecord.country }}</div>
      </div>
      <div v-else class="text-sm text-gray-400 italic">No address on file</div>
    </div>
    <div v-else class="text-md text-gray-400">—</div>
  </div>

  <div class="min-w-0 border-t border-gray-200 px-4 py-5 dark:border-gray-700 sm:px-6 md:col-span-2 md:border-l-0 xl:col-span-1 xl:border-t-0 xl:border-l xl:border-gray-200 dark:xl:border-gray-700">
    <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Subsidiary</p>
    <div v-if="record.subsidiary?.id">
      <Link :href="route('subsidiaries.show', record.subsidiary.id)" class="text-md font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
        {{ record.subsidiary.display_name ?? `Subsidiary #${record.subsidiary.id}` }}
      </Link>
      <p class="text-sm text-gray-400 mt-0.5">Sub #{{ record.subsidiary.id }}</p>
    </div>
    <div v-else class="text-md text-gray-400">—</div>
  </div>

</div>

<!-- Bottom: Schedule tiles -->
<div class="min-w-0 bg-white px-4 py-5 dark:bg-gray-800 sm:px-6">
  <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Schedule &amp; routing</p>
  <div class="grid grid-cols-1 divide-y divide-gray-200 overflow-hidden rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700 sm:grid-cols-2 xl:grid-cols-3 sm:divide-x">

    <div class="min-w-0 px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Need to leave by</p>
      <p class="break-words text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTimeWithZoneId(record.time_to_leave_by) }}</p>
    </div>

    <div class="min-w-0 px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Scheduled arrive by</p>
      <p class="break-words text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTimeWithZoneId(record.scheduled_at) }}</p>
    </div>

    <div
      v-if="routingOutboundMin != null"
      class="col-span-1 border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30 sm:col-span-2 xl:col-span-3"
    >
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Driving estimates (Google)</p>
      <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Drive to customer</dt>
          <dd class="text-md font-semibold text-gray-900 dark:text-white">~{{ routingOutboundMin }} min</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">At location</dt>
          <dd class="text-md font-semibold text-gray-900 dark:text-white">{{ routingAtLocationMin }} min</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Drive back to base</dt>
          <dd class="text-md font-semibold text-gray-900 dark:text-white">~{{ routingReturnMin }} min</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Total driving</dt>
          <dd class="text-md font-semibold text-gray-900 dark:text-white">~{{ routingTotalDrivingMin }} min</dd>
        </div>
      </dl>
      <p
        v-if="!record.estimated_return_travel_duration_seconds && routingOutboundMin"
        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
      >
        Return leg not stored separately yet; fleet scheduling assumes the same drive time as outbound until you recalculate travel on the delivery form.
      </p>
    </div>

    <div v-if="record.en_route_at" class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Departed en route</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTime(record.en_route_at) }}</p>
    </div>

    <div class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Est. arrival</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTime(record.estimated_arrival_at) }}</p>
    </div>

    <div class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Delivered</p>
      <p class="text-md font-semibold text-green-600 dark:text-green-400">{{ formatDateTime(record.delivered_at) }}</p>
    </div>
  </div>

  <div v-if="googleMapsDirectionsUrl" class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
    <div class="flex flex-wrap items-center gap-3">
      <a
        :href="googleMapsDirectionsUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg bg-[#4285F4] px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-[#3367d6]"
      >
        <span class="material-icons text-xl" aria-hidden="true">map</span>
        Open delivery route in Google Maps
      </a>
      <button
        v-if="canRecalculateTravel && canPerformDeliveryActions"
        type="button"
        :title="travelComputeDisabledTitle"
        :disabled="!travelComputeReady || computeTravelLoading"
        class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-medium text-primary-700 shadow-sm transition-colors hover:bg-primary-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50"
        @click="computeTravel"
      >
        <span class="material-icons text-xl" :class="{ 'animate-spin': computeTravelLoading }" aria-hidden="true">
          {{ computeTravelLoading ? 'sync' : 'route' }}
        </span>
        {{ travelComputeButtonLabel }}
      </button>
    </div>
    <p class="mt-2 break-words text-xs text-gray-500 dark:text-gray-400">
      Opens Google Maps directions from your <strong>depart</strong> location to the <strong>delivery</strong> address (outbound leg). Estimated return time is computed separately for scheduling.
    </p>
  </div>
</div>

</div>

                <!-- Assets -->
                <div class="min-w-0 overflow-hidden bg-white shadow sm:rounded-lg dark:bg-gray-800">
                    <div class="flex flex-col gap-2 border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Assets to Deliver</h3>
                        <div v-if="items.length" class="shrink-0 text-md text-gray-500">
                            {{ items.filter(i => i.delivered_at).length }} / {{ items.length }} delivered
                        </div>
                    </div>

                    <div v-if="items.length === 0" class="px-6 py-10 text-center text-md text-gray-500">
                        No assets tied to this delivery yet. Edit the delivery to link a source or add assets.
                    </div>

                    <div v-else class="min-w-0 overflow-x-auto overscroll-x-contain -mx-4 sm:mx-0">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-sm font-semibold uppercase tracking-wide text-gray-500">Asset</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold uppercase tracking-wide text-gray-500">Variant</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold uppercase tracking-wide text-gray-500">Unit / Serial</th>
                                    <th class="px-4 py-2 text-center text-sm font-semibold uppercase tracking-wide text-gray-500">Delivered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="item in items" :key="item.id">
                                    <td class="px-4 py-3 text-md text-gray-900 dark:text-white">
                                        <div class="font-medium">{{ itemName(item) }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-md text-gray-600 dark:text-gray-300">{{ itemVariantLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-md text-gray-600 dark:text-gray-300">{{ itemUnitLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="!!item.delivered_at"
                                                :disabled="isSigned"
                                                @change="toggleItemDelivered(item)"
                                                class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                            />
                                            <span v-if="item.delivered_at" class="text-sm text-green-700 dark:text-green-300">
                                                {{ formatDate(item.delivered_at) }}
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Notes -->
                <div v-if="record.internal_notes || record.customer_notes" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
                    <div v-if="record.internal_notes">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Internal Notes</div>
                        <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.internal_notes }}</p>
                    </div>
                    <div v-if="record.customer_notes">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Customer Notes</div>
                        <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.customer_notes }}</p>
                    </div>
                </div>

                <!-- Checklist (existing) -->
                <div class="min-w-0 overflow-hidden bg-white shadow sm:rounded-lg dark:bg-gray-800">
                    <div class="flex flex-col gap-4 border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-6 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Delivery Checklist</h3>
                            <p class="text-md text-gray-500 dark:text-gray-400">
                                Items to complete before and during delivery
                                <span v-if="isSigned" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                    <span class="material-icons text-md mr-1">lock</span>
                                    Signed
                                </span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            <button
                                v-if="!isSigned"
                                type="button"
                                @click="addChecklistItemToDelivery"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-md font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg"
                            >
                                <span class="material-icons text-lg">add</span>
                                Add Item
                            </button>
                            <button
                                v-if="!isSigned"
                                type="button"
                                @click="openCategoryAdminModal"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-md font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg"
                            >
                                <span class="material-icons text-lg">folder_special</span>
                                Categories
                            </button>
                            <button
                                v-if="!isSigned && checklistItems.length === 0"
                                type="button"
                                @click="openChecklistModal"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-md font-medium text-green-600 bg-green-50 hover:bg-green-100 rounded-lg"
                            >
                                <span class="material-icons text-lg">description</span>
                                Use Template
                            </button>
                        </div>
                    </div>

                    <div v-if="checklistItems.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-for="category in itemsByCategory" :key="category.id" class="px-6 py-4">
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3 flex flex-wrap items-center gap-2">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0"></span>
                                    <span class="truncate">{{ category.name }}</span>
                                    <span class="text-gray-400 dark:text-gray-500 normal-case font-medium">
                                        ({{ category.items.filter(i => i.completed).length }}/{{ category.items.length }})
                                    </span>
                                </span>
                                <button
                                    v-if="!isSigned && typeof category.id === 'number'"
                                    type="button"
                                    class="ml-auto inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                    @click="openCategoryAdminModalForEdit(category.id)"
                                >
                                    <span class="material-icons text-sm">edit</span>
                                    Edit category
                                </button>
                            </h4>
                            <ul class="space-y-1.5">
                                <li
                                    v-for="item in category.items"
                                    :key="item.id"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <label class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="!!item.completed"
                                            :disabled="isSigned"
                                            @change="toggleChecklistItemCompleted(item)"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 disabled:opacity-60 disabled:cursor-not-allowed"
                                        />
                                        <span
                                            :class="[
                                                'text-md flex-1 min-w-0 truncate',
                                                item.completed
                                                    ? 'text-gray-400 dark:text-gray-500 line-through'
                                                    : 'text-gray-900 dark:text-white',
                                            ]"
                                        >
                                            {{ item.label }}
                                            <span v-if="item.is_required" class="ml-1 text-red-500">*</span>
                                        </span>
                                    </label>
                                    <div v-if="!isSigned" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            type="button"
                                            @click="editChecklistItemOnDelivery(item)"
                                            class="h-7 w-7 rounded flex items-center justify-center text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                                            aria-label="Edit item"
                                        >
                                            <span class="material-icons text-lg">edit</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="removeChecklistItemFromDelivery(item)"
                                            class="h-7 w-7 rounded flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                            aria-label="Remove item"
                                        >
                                            <span class="material-icons text-lg">delete</span>
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div v-else class="px-6 py-8 text-center text-md text-gray-500">
                        No checklist items.
                    </div>
                </div>

                </template>

            </div>

            <!-- Sidebar -->
            <div class="min-w-0 space-y-6 lg:col-span-4">
                <!-- Request approval -->
                <div
                    v-if="isPendingRequest"
                    class="rounded-lg border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/30"
                >
                    <h3 class="mb-4 text-lg font-semibold text-amber-950 dark:text-amber-100">Review request</h3>
                    <div v-if="record.review_decision === 'reschedule_requested'" class="mb-4 rounded-lg border border-amber-300 bg-white/70 p-3 text-sm dark:border-amber-700 dark:bg-gray-900/40">
                        <p class="font-medium text-amber-950 dark:text-amber-100">Approver requested a new time</p>
                        <p v-if="record.proposed_scheduled_at" class="mt-1">Proposed: {{ formatDateTime(record.proposed_scheduled_at) }}</p>
                        <p v-if="record.review_notes" class="mt-1 text-gray-700 dark:text-gray-300">{{ record.review_notes }}</p>
                    </div>
                    <div v-if="canApproveRequest" class="space-y-3">
                        <textarea
                            v-model="reviewNotes"
                            rows="2"
                            class="input-style w-full text-sm"
                            placeholder="Optional note to requester"
                        />
                        <template v-if="showRescheduleFields">
                            <div>
                                <label for="review-proposed-scheduled-at" class="mb-1 block text-sm font-medium text-amber-950 dark:text-amber-100">
                                    Proposed date &amp; time
                                </label>
                                <input
                                    id="review-proposed-scheduled-at"
                                    v-model="proposedScheduledAt"
                                    type="datetime-local"
                                    class="input-style w-full text-sm"
                                />
                                <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-200/80">
                                    Suggest a new departure time for the requester to accept or revise.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-amber-400 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-600 dark:text-amber-100 dark:hover:bg-amber-900/40"
                                :disabled="reviewProcessing || !proposedScheduledAt"
                                @click="proposeReschedule"
                            >
                                Send reschedule proposal
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                :disabled="reviewProcessing"
                                @click="cancelProposeReschedule"
                            >
                                Cancel
                            </button>
                        </template>
                        <template v-else>
                            <button
                                type="button"
                                class="btn-primary w-full"
                                :disabled="reviewProcessing"
                                @click="approveRequest"
                            >
                                Approve & schedule
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-amber-400 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-600 dark:text-amber-100 dark:hover:bg-amber-900/40"
                                :disabled="reviewProcessing"
                                @click="openProposeReschedule"
                            >
                                Propose reschedule
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-red-300 bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-800 dark:bg-red-700 dark:hover:bg-red-600"
                                :disabled="reviewProcessing"
                                @click="denyRequest"
                            >
                                Deny request
                            </button>
                        </template>
                    </div>
                    <div v-else-if="canResubmitRequest" class="space-y-3">
                        <input
                            v-model="proposedScheduledAt"
                            type="datetime-local"
                            class="input-style w-full text-sm"
                            :placeholder="record.proposed_scheduled_at ? serverUtcToLocalInput(record.proposed_scheduled_at) : 'Updated schedule'"
                        />
                        <button
                            type="button"
                            class="btn-primary w-full"
                            :disabled="reviewProcessing"
                            @click="resubmitRequest"
                        >
                            Resubmit for approval
                        </button>
                    </div>
                    <p v-else class="text-sm text-amber-800 dark:text-amber-200">Awaiting review by the location delivery approver.</p>
                </div>

                <!-- Denied request actions -->
                <div
                    v-if="isDeniedRequest && (canResubmitRequest || canUpdateRequest || canCancelDeniedRequest)"
                    class="rounded-lg border border-red-200 bg-red-50 p-6 shadow-sm dark:border-red-800/50 dark:bg-red-950/30"
                >
                    <h3 class="mb-4 text-lg font-semibold text-red-950 dark:text-red-100">Next steps</h3>
                    <div class="space-y-3">
                        <Link
                            v-if="canUpdateRequest"
                            :href="route('deliveries.requests.edit', record.id)"
                            class="btn-primary inline-flex w-full items-center justify-center gap-2"
                        >
                            <span class="material-icons text-lg">edit</span>
                            Update &amp; resubmit request
                        </Link>
                        <input
                            v-if="canResubmitRequest"
                            v-model="proposedScheduledAt"
                            type="datetime-local"
                            class="input-style w-full text-sm"
                            placeholder="New proposed schedule"
                        />
                        <button
                            v-if="canResubmitRequest"
                            type="button"
                            class="w-full rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-800 hover:bg-red-50 dark:border-red-700 dark:bg-red-950/40 dark:text-red-100 dark:hover:bg-red-900/40"
                            :disabled="reviewProcessing || !proposedScheduledAt"
                            @click="resubmitRequest"
                        >
                            Quick resubmit (time only)
                        </button>
                        <button
                            v-if="canCancelDeniedRequest"
                            type="button"
                            class="w-full rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:bg-red-950/40 dark:text-red-100 dark:hover:bg-red-900/40"
                            :disabled="reviewProcessing"
                            @click="showCancelDeniedModal = true"
                        >
                            Cancel delivery
                        </button>
                        <button
                            v-if="canUpdateRequest"
                            type="button"
                            class="w-full rounded-lg border border-red-400 bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:border-red-700 dark:bg-red-700 dark:hover:bg-red-600"
                            @click="handleDelete"
                        >
                            Delete request
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div v-if="canPerformDeliveryActions" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Actions</h3>
                    <div class="space-y-3">
                        <button
                            v-if="showTravelComputeButton"
                            type="button"
                            :title="travelComputeDisabledTitle"
                            :disabled="!travelComputeReady || computeTravelLoading"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700 hover:bg-primary-100 dark:hover:bg-primary-900/50 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg"
                            @click="computeTravel"
                        >
                            <span class="material-icons text-lg" :class="{ 'animate-spin': computeTravelLoading }">
                                {{ computeTravelLoading ? 'sync' : 'route' }}
                            </span>
                            Calculate drive times
                        </button>
                        <button
                            v-if="canMarkEnRoute"
                            type="button"
                            @click="goEnRoute"
                            :disabled="enRouteLoading || isSigned"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-lg" :class="{ 'animate-spin': enRouteLoading }">
                                {{ enRouteLoading ? 'sync' : 'local_shipping' }}
                            </span>
                            Mark en route
                        </button>
                        <button
                            v-if="canShowArrivedAtDelivery"
                            type="button"
                            @click="goArrived"
                            :disabled="arrivedLoading || isSigned"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-lg" :class="{ 'animate-spin': arrivedLoading }">
                                {{ arrivedLoading ? 'sync' : 'place' }}
                            </span>
                            Arrived at delivery
                        </button>
                        <button
                            v-if="!record.delivered_at"
                            @click="markAsDelivered"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-lg">check_circle</span>
                            Complete Delivery
                        </button>
                        <button
                            @click="viewSignatureRequest"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg"
                        >
                            <span class="material-icons text-lg">visibility</span>
                            View Signature Request
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div v-if="canPerformDeliveryActions" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Delivery Info</h3>
                    <dl class="text-md space-y-3">
                        <div>
                            <dt class="text-gray-500 mb-1.5">Status</dt>
                            <dd class="m-0">
                                <label for="delivery-status-select-sidebar" class="sr-only">Delivery status</label>
                                <select
                                    id="delivery-status-select-sidebar"
                                    :value="record.status"
                                    :disabled="isSigned || statusUpdating"
                                    class="input-style w-full text-md py-2 disabled:opacity-60 disabled:cursor-not-allowed"
                                    @change="updateDeliveryStatus"
                                >
                                    <option
                                        v-for="opt in statusOptionsForSelect"
                                        :key="`sb-${String(statusOptionValue(opt))}`"
                                        :value="statusOptionValue(opt)"
                                    >
                                        {{ statusOptionLabel(opt) }}
                                    </option>
                                </select>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Delivery Driver</dt>
                            <dd class="text-gray-900 dark:text-white text-right">{{ record.technician?.display_name ?? record.technician?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Truck</dt>
                            <dd class="text-gray-900 dark:text-white text-right min-w-0">
                                <Link
                                    v-if="fleetTruckRecord?.id && fleetTruckLabel"
                                    :href="route('fleet.show', fleetTruckRecord.id)"
                                    class="text-primary-600 hover:text-primary-500 dark:text-primary-400 break-words"
                                >
                                    {{ fleetTruckLabel }}
                                </Link>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Trailer</dt>
                            <dd class="text-gray-900 dark:text-white text-right min-w-0">
                                <Link
                                    v-if="fleetTrailerRecord?.id && fleetTrailerLabel"
                                    :href="route('fleet.show', fleetTrailerRecord.id)"
                                    class="text-primary-600 hover:text-primary-500 dark:text-primary-400 break-words"
                                >
                                    {{ fleetTrailerLabel }}
                                </Link>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Created</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</dd>
                        </div>
                        <div v-if="record.signed_at" class="flex justify-between gap-4">
                            <dt class="text-gray-500">Signed</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDateTime(record.signed_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Related records -->
                <div v-if="relatedRecords.length" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Related Records</h3>
                    <ul class="space-y-2">
                        <li v-for="rel in relatedRecords" :key="rel.label + rel.name" class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm uppercase tracking-wide text-gray-500">{{ rel.label }}</div>
                                <div class="text-md text-gray-900 dark:text-white">{{ rel.name }}</div>
                            </div>
                            <a :href="rel.href" class="text-primary-600 hover:text-primary-700 text-md inline-flex items-center gap-1">
                                <span class="material-icons text-md">open_in_new</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Related records (full width below main + sidebar) -->
            <div
                v-if="!isPendingRequest && !isDeniedRequest && visibleSublists.length > 0 && domainName"
                class="min-w-0 lg:col-span-12"
            >
                <Sublist
                    :parent-record="record"
                    :parent-domain="domainName"
                    :sublists="visibleSublists"
                />
            </div>
        </div>
        </MobileActionBar>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <h3 class="text-xl font-medium text-gray-900 dark:text-white">
                    {{ isRequestDelete ? 'Delete delivery request' : 'Delete delivery' }}
                </h3>
                <p class="mt-2 text-md text-gray-500 dark:text-gray-400">
                    <template v-if="isRequestDelete">
                        This will permanently delete the delivery request and the delivery record
                        for <strong class="font-medium text-gray-700 dark:text-gray-200">{{ record?.display_name }}</strong>.
                        This cannot be undone.
                    </template>
                    <template v-else>
                        Are you sure you want to delete {{ record?.display_name }}? This cannot be undone.
                    </template>
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="px-4 py-2 text-md font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Confirm' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        class="px-4 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Deny request modal -->
        <Modal :show="showDenyModal" max-width="md" @close="closeDenyModal">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Deny delivery request</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Tell the requester why this delivery cannot be approved as submitted.
                </p>
                <div class="mt-4">
                    <label for="deny-reason" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reason <span class="text-red-600">*</span>
                    </label>
                    <textarea
                        id="deny-reason"
                        v-model="denyReason"
                        rows="4"
                        class="input-style w-full text-sm"
                        placeholder="e.g. Need to reschedule — driver unavailable at that time."
                    />
                    <p v-if="denyReasonError" class="mt-2 text-sm text-red-600">{{ denyReasonError }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        @click="closeDenyModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-md font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50"
                        :disabled="reviewProcessing"
                        @click="confirmDenyRequest"
                    >
                        {{ reviewProcessing ? 'Denying...' : 'Deny request' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Cancel denied request modal -->
        <Modal :show="showCancelDeniedModal" max-width="md" @close="showCancelDeniedModal = false">
            <div class="p-6 text-center">
                <h3 class="text-xl font-medium text-gray-900 dark:text-white">Cancel delivery request</h3>
                <p class="mt-2 text-md text-gray-500 dark:text-gray-400">
                    This will cancel the delivery request. You can submit a new request later if needed.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-md font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50"
                        :disabled="reviewProcessing"
                        @click="cancelDeniedDelivery"
                    >
                        {{ reviewProcessing ? 'Cancelling...' : 'Cancel delivery' }}
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        @click="showCancelDeniedModal = false"
                    >
                        Keep request
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Driver schedule conflict (approve) -->
        <Modal :show="showDriverConflictModal" max-width="2xl" @close="showDriverConflictModal = false">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Driver schedule conflict</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ scheduleConflictMessage || 'This driver has overlapping deliveries at the proposed time.' }}
                </p>
                <ul class="mt-4 space-y-2">
                    <li
                        v-for="conflict in driverConflicts"
                        :key="conflict.id"
                        class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-700 dark:bg-amber-950/40"
                    >
                        <Link :href="route('deliveries.show', conflict.id)" class="font-medium text-primary-700 hover:underline dark:text-primary-300">
                            {{ conflict.display_name }}
                        </Link>
                        <span class="ml-2 capitalize text-gray-600 dark:text-gray-400">{{ conflict.status?.replace('_', ' ') }}</span>
                    </li>
                </ul>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <Link
                        :href="route('deliveries.requests.edit', record.id)"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="showDriverConflictModal = false"
                    >
                        Edit request schedule
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="showDriverConflictModal = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Fleet schedule conflict (approve) -->
        <Modal :show="showFleetConflictModal" max-width="2xl" @close="showFleetConflictModal = false">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Fleet scheduling conflict</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ scheduleConflictMessage || 'Truck or trailer is already booked for this window.' }}
                </p>
                <ul class="mt-4 space-y-2">
                    <li
                        v-for="conflict in fleetConflicts"
                        :key="conflict.id"
                        class="flex flex-col gap-1 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-700 dark:bg-amber-950/40 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ conflict.display_name }}
                            <span class="ml-2 font-normal capitalize text-gray-600 dark:text-gray-400">{{ conflict.status }}</span>
                        </span>
                        <Link
                            :href="route('deliveries.show', conflict.id)"
                            class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300"
                            @click="showFleetConflictModal = false"
                        >
                            View conflicting delivery
                        </Link>
                    </li>
                </ul>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <Link
                        :href="route('deliveries.requests.edit', record.id)"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="showFleetConflictModal = false"
                    >
                        Edit request to resolve
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="showFleetConflictModal = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Mark delivered modal -->
        <Modal :show="showMarkDeliveredModal" @close="showMarkDeliveredModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Complete Delivery</h3>
                <p class="text-md text-gray-600 dark:text-gray-400 mb-4">Choose how you want to complete this delivery:</p>
                <div class="space-y-3">
                    <button
                        @click="sendSignatureRequest"
                        class="w-full flex items-center gap-3 p-4 border border-blue-200 bg-blue-50 hover:bg-blue-100 rounded-lg text-left"
                    >
                        <span class="material-icons text-blue-600">send</span>
                        <div>
                            <div class="font-medium text-gray-900">Send Signature Request</div>
                            <div class="text-md text-gray-600">Open preview to send by email or SMS</div>
                        </div>
                    </button>
                    <button
                        @click="markDeliveredWithoutSignature"
                        class="w-full flex items-center gap-3 p-4 border border-green-200 bg-green-50 hover:bg-green-100 rounded-lg text-left"
                    >
                        <span class="material-icons text-green-600">check_circle</span>
                        <div>
                            <div class="font-medium text-gray-900">Mark as Delivered</div>
                            <div class="text-md text-gray-600">Complete without customer signature</div>
                        </div>
                    </button>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button
                        @click="showMarkDeliveredModal = false"
                        class="w-full px-4 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg"
                    >Cancel</button>
                </div>
            </div>
        </Modal>

        <!-- Mark en route: optional SMS -->
        <Modal :show="showEnRouteModal" max-width="md" @close="closeEnRouteModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Mark en route</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Confirm the driver is on the way. You can optionally send a text so the customer can track the delivery.
                </p>
                <p
                    v-if="page.props.tenant_sandbox_mode"
                    class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
                    <span>Sandbox mode sends SMS to your staff user profile phone (matched by login email), not the customer.</span>
                </p>

                <fieldset class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="enRouteDeliveryChoice" type="radio" name="en_route_notify" value="no_sms" class="mt-1 text-primary-600" />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Mark en route only</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">Do not send a text message.</span>
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-3 rounded-lg border p-3"
                        :class="
                            deliveryEnRouteSms.offered
                                ? 'cursor-pointer border-gray-200 dark:border-gray-600'
                                : 'cursor-not-allowed border-gray-100 opacity-60 dark:border-gray-700'
                        "
                    >
                        <input
                            v-model="enRouteDeliveryChoice"
                            type="radio"
                            name="en_route_notify"
                            value="sms"
                            class="mt-1 text-primary-600 disabled:cursor-not-allowed"
                            :disabled="!deliveryEnRouteSms.offered"
                        />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Mark en route and notify by SMS</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                                Send a short text with the tracking link.
                            </span>
                            <span
                                v-if="!deliveryEnRouteSms.offered && deliveryEnRouteSms.hint"
                                class="mt-1 block text-xs text-amber-800 dark:text-amber-200"
                            >
                                {{ deliveryEnRouteSms.hint }}
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        :disabled="enRouteLoading"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeEnRouteModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="enRouteLoading || (enRouteDeliveryChoice === 'sms' && !deliveryEnRouteSms.offered)"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50"
                        @click="confirmEnRouteModal"
                    >
                        <span v-if="enRouteLoading" class="material-icons animate-spin text-base">refresh</span>
                        {{ enRouteLoading ? 'Updating…' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Arrived at delivery: optional SMS to customer -->
        <Modal :show="showArrivedModal" max-width="md" @close="closeArrivedModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Arrived at delivery</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Confirm you are on site. You can optionally send a text so the customer knows that
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ deliveryDriverDisplayForArrived }}</span>
                    has arrived.
                </p>
                <p
                    v-if="page.props.tenant_sandbox_mode && deliveryArrivedSms.show_sms_choice"
                    class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
                    <span>Sandbox mode sends SMS to your staff user profile phone (matched by login email), not the customer.</span>
                </p>

                <fieldset v-if="deliveryArrivedSms.show_sms_choice" class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="arrivedDeliveryChoice" type="radio" name="arrived_notify" value="no_sms" class="mt-1 text-primary-600" />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Confirm arrival only</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">Do not send a text message.</span>
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-3 rounded-lg border p-3"
                        :class="
                            deliveryArrivedSms.offered
                                ? 'cursor-pointer border-gray-200 dark:border-gray-600'
                                : 'cursor-not-allowed border-gray-100 opacity-60 dark:border-gray-700'
                        "
                    >
                        <input
                            v-model="arrivedDeliveryChoice"
                            type="radio"
                            name="arrived_notify"
                            value="sms"
                            class="mt-1 text-primary-600 disabled:cursor-not-allowed"
                            :disabled="!deliveryArrivedSms.offered"
                        />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Confirm and notify customer by SMS</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                                Send a short text that the delivery driver has arrived.
                            </span>
                            <span
                                v-if="!deliveryArrivedSms.offered && deliveryArrivedSms.hint"
                                class="mt-1 block text-xs text-amber-800 dark:text-amber-200"
                            >
                                {{ deliveryArrivedSms.hint }}
                            </span>
                        </span>
                    </label>
                </fieldset>
                <p v-else class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <template v-if="deliveryArrivedSms.category_enabled && deliveryArrivedSms.hint">
                        {{ deliveryArrivedSms.hint }}
                    </template>
                    <template v-else>
                        Delivery SMS is turned off in account settings or SMS cannot be sent. You can still confirm arrival without texting the customer.
                    </template>
                </p>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        :disabled="arrivedLoading"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeArrivedModal"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="deliveryArrivedSms.show_sms_choice"
                        type="button"
                        :disabled="arrivedLoading || (arrivedDeliveryChoice === 'sms' && !deliveryArrivedSms.offered)"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
                        @click="confirmArrivedModal"
                    >
                        <span v-if="arrivedLoading" class="material-icons animate-spin text-base">refresh</span>
                        {{ arrivedLoading ? 'Saving…' : 'Confirm' }}
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="arrivedLoading"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
                        @click="confirmArrivedWithoutSms"
                    >
                        <span v-if="arrivedLoading" class="material-icons animate-spin text-base">refresh</span>
                        {{ arrivedLoading ? 'Saving…' : 'Confirm arrival' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Checklist template modal -->
        <Modal :show="showChecklistModal" @close="closeChecklistModal" max-width="2xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Add delivery checklist</h3>
                        <p class="text-md text-gray-500 dark:text-gray-400 mt-1">Start from a template or define items manually.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeChecklistModal"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition-colors"
                        aria-label="Close"
                    >
                        <span class="material-icons text-xl">close</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                    <button
                        type="button"
                        @click="selectChecklistMode('template')"
                        :class="[
                            'p-4 rounded-xl border-2 text-left transition-colors',
                            checklistCreationMode === 'template'
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/25 dark:border-primary-400'
                                : 'border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-500',
                        ]"
                    >
                        <p class="font-semibold text-gray-900 dark:text-white">From template</p>
                        <p class="text-md text-gray-500 dark:text-gray-400 mt-0.5">Use an existing checklist template</p>
                    </button>
                    <button
                        type="button"
                        @click="selectChecklistMode('scratch')"
                        :class="[
                            'p-4 rounded-xl border-2 text-left transition-colors',
                            checklistCreationMode === 'scratch'
                                ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-400'
                                : 'border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-500',
                        ]"
                    >
                        <p class="font-semibold text-gray-900 dark:text-white">From scratch</p>
                        <p class="text-md text-gray-500 dark:text-gray-400 mt-0.5">Build a custom checklist for this delivery</p>
                    </button>
                </div>

                <div v-if="checklistCreationMode === 'template'" class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Template</label>
                    <select v-model="selectedTemplate" class="input-style">
                        <option :value="null">Choose a template…</option>
                        <option v-for="t in checklistTemplates" :key="t.id" :value="t">{{ t.name }}</option>
                    </select>
                </div>

                <div v-if="checklistCreationMode === 'scratch'" class="space-y-3">
                    <div
                        v-for="(item, idx) in newChecklistItems"
                        :key="idx"
                        class="flex flex-col sm:flex-row sm:items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-900/30 p-3"
                    >
                        <input
                            v-model="item.label"
                            type="text"
                            placeholder="Item label…"
                            class="input-style flex-1 min-w-0"
                        />
                        <select v-model="item.category" class="input-style w-full sm:w-52 shrink-0">
                            <template v-if="categories.length">
                                <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
                            </template>
                            <template v-else>
                                <option value="Pre Delivery Checklist">Pre Delivery Checklist</option>
                                <option value="Upon Delivery">Upon Delivery</option>
                            </template>
                        </select>
                        <button
                            type="button"
                            @click="removeChecklistItem(idx)"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40 shrink-0 self-end sm:self-auto"
                            aria-label="Remove item"
                        >
                            <span class="material-icons text-lg">delete</span>
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="addChecklistItem"
                        class="text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        + Add item
                    </button>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button
                        type="button"
                        @click="closeChecklistModal"
                        class="px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveChecklist"
                        :disabled="isLoadingChecklist"
                        class="px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ isLoadingChecklist ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Checklist categories (add / update) -->
        <Modal :show="showCategoryAdminModal" max-width="md" @close="closeCategoryAdminModal">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Checklist categories</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Add categories or rename existing ones. Categories used by checklist items cannot be deleted from here.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        aria-label="Close"
                        @click="closeCategoryAdminModal"
                    >
                        <span class="material-icons text-xl">close</span>
                    </button>
                </div>

                <ul
                    v-if="categories.length"
                    class="mb-5 max-h-40 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-600"
                >
                    <li
                        v-for="c in categories"
                        :key="c.id"
                        class="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                    >
                        <span class="font-medium text-gray-900 dark:text-white truncate">{{ c.name }}</span>
                        <button
                            v-if="!isSigned"
                            type="button"
                            class="shrink-0 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                            @click="openCategoryAdminModalForEdit(c.id)"
                        >
                            Edit
                        </button>
                    </li>
                </ul>

                <div class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-900/40 p-4">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ categoryAdminEditingId != null ? 'Update category' : 'New category' }}
                    </p>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Name</label>
                        <input v-model="categoryAdminName" type="text" class="input-style w-full" maxlength="255" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Color tag</label>
                        <select v-model="categoryAdminColor" class="input-style w-full">
                            <option v-for="opt in categoryColorOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                            @click="categoryAdminEditingId != null ? openCategoryAdminModal() : closeCategoryAdminModal()"
                        >
                            {{ categoryAdminEditingId != null ? 'Switch to add new' : 'Cancel' }}
                        </button>
                        <button
                            type="button"
                            :disabled="categoryAdminSaving || !categoryAdminName.trim()"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="saveCategoryAdminModal"
                        >
                            {{ categoryAdminSaving ? 'Saving…' : (categoryAdminEditingId != null ? 'Save changes' : 'Create category') }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Single-item modal -->
        <Modal :show="showAddItemModal" @close="closeAddItemModal" max-width="md">
            <form @submit.prevent="saveNewChecklistItem" class="p-6 space-y-4 text-gray-900 dark:text-gray-100">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ editingChecklistItem ? 'Edit checklist item' : 'Add checklist item' }}
                </h3>
                <div>
                    <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">Label</label>
                    <input v-model="newItemLabel" required class="input-style" />
                </div>
                <div>
                    <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                    <select v-model="newItemCategory" class="input-style w-full">
                        <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-md text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input
                        v-model="newItemRequired"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                    />
                    Required
                </label>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="closeAddItemModal"
                        class="px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="!newItemLabel.trim()"
                        class="px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ editingChecklistItem ? 'Save' : 'Add' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeliveredModal" max-width="md" @close="closeDeliveredModal">
            <div class="p-6">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                    <span class="material-icons text-2xl text-green-600 dark:text-green-300">check_circle</span>
                </div>
                <h3 class="text-center text-xl font-semibold text-gray-900 dark:text-white">
                    Delivery complete
                </h3>
                <p class="mt-2 text-center text-md text-gray-600 dark:text-gray-400">
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ deliveryLabel }}</span>
                    has been delivered
                    <template v-if="record.delivered_at">
                        on <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatDate(record.delivered_at) }}</span>
                    </template>.
                </p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link
                        v-if="transactionShowHref"
                        :href="transactionShowHref"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700"
                        @click="closeDeliveredModal"
                    >
                        <span class="material-icons text-base">handshake</span>
                        View deal
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="closeDeliveredModal"
                    >
                        Close and continue to delivery
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Preview -->
        <Teleport to="body">
            <div v-if="showPreview && canPerformDeliveryActions" class="delivery-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <DeliveryPreview
                    :record="record"
                    :account="account"
                    :logo-url="effectiveLogoUrl"
                    :enum-options="enumOptions"
                    :checklist-items="checklistItems"
                    :delivery-signature-sms="deliverySignatureSms"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>
