<script setup>
import Modal from '@/Components/Modal.vue';
import { useTimezone } from '@/composables/useTimezone';
import axios from 'axios';
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

dayjs.extend(utc);
dayjs.extend(timezone);

const props = defineProps({
    show: { type: Boolean, default: false },
    recordType: { type: String, required: true },
    recordId: { type: Number, required: true },
    recipientEmail: { type: String, default: '' },
    recipientName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'sent']);

const page = usePage();
const { accountTimezone, accountTimezoneLabel } = useTimezone();

const sandboxMode = computed(() => !!page.props.tenant_sandbox_mode);
const staffEmail = computed(() => page.props.auth?.user?.email ?? '');

const step = ref('type');
const selectedType = ref('');
const viewAll = ref(false);
const surveys = ref([]);
const loadingSurveys = ref(false);
const selectedSurvey = ref(null);
const sendMode = ref('now');
const sendAtLocal = ref('');
const includeSms = ref(false);
const smsOffered = ref(false);
const smsHint = ref('');
const emailSandbox = ref({ sandbox_mode: false, intended_recipient: '', delivery_recipient: '' });
const sending = ref(false);
const errorMessage = ref('');
const invitations = ref([]);
const loadingInvitations = ref(false);
const cancellingId = ref(null);
const deletingId = ref(null);
const pollTimer = ref(null);
const pollStartedAt = ref(null);
const POLL_INTERVAL_MS = 1000;
const POLL_TIMEOUT_MS = 90_000;

const surveyTypes = [
    { value: 'lead', label: 'Lead Generation', icon: 'person_add', description: 'Capture interest and contact details' },
    { value: 'feedback', label: 'Feedback', icon: 'rate_review', description: 'Collect satisfaction and service feedback' },
    { value: 'followup', label: 'Follow Up', icon: 'follow_the_signs', description: 'Check in after a sale or visit' },
];

const canSend = computed(() => !!props.recipientEmail?.trim());
const hasScheduledTime = computed(() => sendMode.value === 'later' && sendAtLocal.value.trim() !== '');

function resetState() {
    step.value = 'type';
    selectedType.value = '';
    viewAll.value = false;
    surveys.value = [];
    selectedSurvey.value = null;
    sendMode.value = 'now';
    sendAtLocal.value = '';
    includeSms.value = false;
    errorMessage.value = '';
}

function close() {
    stopInvitationPolling();
    emit('close');
}

/** Default scheduled time: tomorrow 9:00 in account timezone (`datetime-local` wall clock). */
function defaultScheduleDatetimeLocal() {
    return dayjs()
        .tz(accountTimezone.value)
        .add(1, 'day')
        .hour(9)
        .minute(0)
        .second(0)
        .millisecond(0)
        .format('YYYY-MM-DDTHH:mm');
}

/**
 * `datetime-local` values are wall clock in the account timezone (not the browser zone).
 * Convert to UTC ISO for the API / Laravel.
 */
function accountDatetimeLocalToUtcIso(localStr) {
    if (!localStr?.trim()) {
        return null;
    }
    const m = dayjs.tz(String(localStr).trim(), 'YYYY-MM-DDTHH:mm', accountTimezone.value);
    if (!m.isValid()) {
        return null;
    }
    return m.utc().toISOString();
}

async function loadSendOptions() {
    try {
        const { data } = await axios.get(route('surveysSendOptions'), {
            params: { record_type: props.recordType, record_id: props.recordId },
        });
        smsOffered.value = !!data?.sms?.offered;
        smsHint.value = data?.sms?.hint ?? '';
        emailSandbox.value = {
            sandbox_mode: !!data?.email?.sandbox_mode,
            intended_recipient: data?.email?.intended_recipient ?? '',
            delivery_recipient: data?.email?.delivery_recipient ?? '',
        };
    } catch {
        smsOffered.value = false;
        smsHint.value = '';
        emailSandbox.value = { sandbox_mode: false, intended_recipient: '', delivery_recipient: '' };
    }
}

async function loadInvitations(silent = false) {
    if (!silent) {
        loadingInvitations.value = true;
    }
    try {
        const { data } = await axios.get(route('surveysInvitations'), {
            params: { record_type: props.recordType, record_id: props.recordId },
        });
        invitations.value = data?.invitations ?? [];
        syncInvitationPolling();
    } catch {
        if (!silent) {
            invitations.value = [];
        }
    } finally {
        if (!silent) {
            loadingInvitations.value = false;
        }
    }
}

function isPendingSend(inv) {
    if (inv?.status !== 'scheduled') {
        return false;
    }
    if (!inv.scheduled_at) {
        return true;
    }
    const at = dayjs(inv.scheduled_at);
    return at.isValid() && at.isBefore(dayjs());
}

function isFutureScheduled(inv) {
    if (inv?.status !== 'scheduled' || !inv.scheduled_at) {
        return false;
    }
    const at = dayjs(inv.scheduled_at);
    return at.isValid() && at.isAfter(dayjs());
}

function invitationStatusLabel(inv) {
    if (isPendingSend(inv)) {
        return 'Sending';
    }
    if (isFutureScheduled(inv)) {
        return 'Scheduled';
    }
    const labels = {
        sent: 'Sent',
        failed: 'Failed',
        cancelled: 'Cancelled',
        scheduled: 'Scheduled',
    };
    return labels[inv?.status] ?? inv?.status ?? '—';
}

function stopInvitationPolling() {
    if (pollTimer.value !== null) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
    pollStartedAt.value = null;
}

function syncInvitationPolling() {
    const hasPending = invitations.value.some(isPendingSend);
    if (hasPending && pollTimer.value === null) {
        pollStartedAt.value = Date.now();
        pollTimer.value = setInterval(() => {
            if (pollStartedAt.value && Date.now() - pollStartedAt.value > POLL_TIMEOUT_MS) {
                stopInvitationPolling();
                return;
            }
            loadInvitations(true);
        }, POLL_INTERVAL_MS);
    } else if (!hasPending) {
        stopInvitationPolling();
    }
}

async function loadSurveys() {
    loadingSurveys.value = true;
    errorMessage.value = '';
    try {
        const params = {};
        if (!viewAll.value && selectedType.value) {
            params.type = selectedType.value;
        }
        const { data } = await axios.get(route('surveysGetActive'), { params });
        surveys.value = data?.surveys ?? [];
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not load surveys.';
        surveys.value = [];
    } finally {
        loadingSurveys.value = false;
    }
}

function pickType(type) {
    selectedType.value = type;
    viewAll.value = false;
    step.value = 'survey';
    loadSurveys();
}

function pickSurvey(survey) {
    selectedSurvey.value = survey;
    step.value = 'delivery';
}

function goBack() {
    if (step.value === 'delivery') {
        step.value = 'survey';
        return;
    }
    if (step.value === 'survey') {
        step.value = 'type';
        return;
    }
    close();
}

async function submitSend() {
    if (!selectedSurvey.value || !canSend.value) {
        return;
    }
    if (sendMode.value === 'later' && !hasScheduledTime.value) {
        errorMessage.value = 'Choose a date and time for the scheduled send.';
        return;
    }

    sending.value = true;
    errorMessage.value = '';
    try {
        const payload = {
            survey_uuid: selectedSurvey.value.uuid,
            record_type: props.recordType,
            record_id: props.recordId,
            delivery: includeSms.value && smsOffered.value ? 'email_sms' : 'email',
        };
        if (sendMode.value === 'later') {
            const iso = accountDatetimeLocalToUtcIso(sendAtLocal.value);
            if (!iso) {
                errorMessage.value = 'Invalid schedule date/time.';
                sending.value = false;
                return;
            }
            payload.send_at = iso;
        }

        const { data } = await axios.post(route('surveysSendToRecord'), payload);
        emit('sent', data?.message ?? 'Survey sent.');
        await loadInvitations();
        resetState();
        step.value = 'type';
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Failed to send survey.';
    } finally {
        sending.value = false;
    }
}

async function cancelInvitation(inv) {
    if (!inv?.id || !isFutureScheduled(inv)) {
        return;
    }
    cancellingId.value = inv.id;
    try {
        await axios.post(route('surveysInvitationCancel', inv.id));
        await loadInvitations();
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not cancel invitation.';
    } finally {
        cancellingId.value = null;
    }
}

async function deleteInvitation(inv) {
    if (!inv?.id || inv.status !== 'cancelled') {
        return;
    }
    deletingId.value = inv.id;
    try {
        await axios.delete(route('surveysInvitationDestroy', inv.id));
        await loadInvitations();
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not delete invitation.';
    } finally {
        deletingId.value = null;
    }
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    const m = dayjs(iso);
    if (!m.isValid()) {
        return iso;
    }
    return m.tz(accountTimezone.value).format('MMM D, YYYY h:mm A');
}

watch(
    () => props.show,
    (open) => {
        if (open) {
            resetState();
            loadSendOptions();
            loadInvitations();
        } else {
            stopInvitationPolling();
        }
    },
);

watch(viewAll, () => {
    if (step.value === 'survey') {
        loadSurveys();
    }
});

watch(sendMode, (mode) => {
    if (mode === 'later' && !sendAtLocal.value.trim()) {
        sendAtLocal.value = defaultScheduleDatetimeLocal();
    }
});

onUnmounted(stopInvitationPolling);
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Send survey</h3>
                    <p v-if="recipientName || recipientEmail" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        To {{ recipientName || recipientEmail }}
                        <span v-if="recipientName && recipientEmail"> ({{ recipientEmail }})</span>
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    @click="close"
                >
                    <span class="material-icons">close</span>
                </button>
            </div>

            <p v-if="!canSend" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                Add an email address to this record before sending a survey.
            </p>

            <p v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                {{ errorMessage }}
            </p>

            <!-- Pending invitations -->
            <div v-if="invitations.length" class="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    Scheduled &amp; recent sends
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li
                        v-for="inv in invitations"
                        :key="inv.id"
                        class="flex items-center justify-between gap-3 bg-white px-4 py-3 text-sm dark:bg-gray-900"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white truncate">{{ inv.survey?.title ?? 'Survey' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span
                                    v-if="isPendingSend(inv)"
                                    class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-400"
                                >
                                    <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Sending…
                                </span>
                                <span v-else>{{ invitationStatusLabel(inv) }}</span>
                                <span v-if="isFutureScheduled(inv)"> · {{ formatWhen(inv.scheduled_at) }}</span>
                                <span v-else-if="inv.status === 'sent' && inv.sent_at"> · {{ formatWhen(inv.sent_at) }}</span>
                                <span v-else-if="inv.status === 'failed' && inv.error_message"> · {{ inv.error_message }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <button
                                v-if="isFutureScheduled(inv)"
                                type="button"
                                class="text-xs text-red-600 hover:text-red-700 hover:underline dark:text-red-400 dark:hover:text-red-300"
                                :disabled="cancellingId === inv.id"
                                @click="cancelInvitation(inv)"
                            >
                                Cancel
                            </button>
                            <button
                                v-if="inv.status === 'cancelled'"
                                type="button"
                                class="text-xs text-gray-600 hover:text-red-600 hover:underline dark:text-gray-400 dark:hover:text-red-400"
                                :disabled="deletingId === inv.id"
                                @click="deleteInvitation(inv)"
                            >
                                {{ deletingId === inv.id ? 'Deleting…' : 'Delete' }}
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
            <p v-else-if="loadingInvitations" class="mb-4 text-sm text-gray-500 dark:text-gray-400">Loading invitations…</p>

            <!-- Step: type -->
            <div v-if="step === 'type'" class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">What type of survey?</p>
                <button
                    v-for="t in surveyTypes"
                    :key="t.value"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 text-left transition-colors hover:border-primary-400 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-gray-800/80"
                    :disabled="!canSend"
                    @click="pickType(t.value)"
                >
                    <span class="material-icons text-primary-600 dark:text-primary-400">{{ t.icon }}</span>
                    <span>
                        <span class="block font-medium text-gray-900 dark:text-white">{{ t.label }}</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">{{ t.description }}</span>
                    </span>
                </button>
            </div>

            <!-- Step: survey -->
            <div v-else-if="step === 'survey'" class="space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Choose a survey</p>
                    <button
                        type="button"
                        class="text-sm text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                        @click="viewAll = !viewAll; loadSurveys()"
                    >
                        {{ viewAll ? 'Filter by type' : 'View all' }}
                    </button>
                </div>
                <div v-if="loadingSurveys" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Loading surveys…</div>
                <div
                    v-else-if="surveys.length === 0"
                    class="rounded-lg border border-dashed border-gray-200 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
                >
                    No active surveys found.
                </div>
                <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                    <button
                        v-for="s in surveys"
                        :key="s.uuid"
                        type="button"
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                        @click="pickSurvey(s)"
                    >
                        <span class="font-medium text-gray-900 dark:text-white">{{ s.title }}</span>
                        <span v-if="s.description" class="block text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ s.description }}</span>
                    </button>
                </div>
            </div>

            <!-- Step: delivery -->
            <div v-else-if="step === 'delivery'" class="space-y-4">
                <div
                    v-if="sandboxMode || emailSandbox.sandbox_mode"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <strong class="font-semibold">Sandbox mode is on.</strong>
                    Email will be sent to you
                    <span v-if="emailSandbox.delivery_recipient || staffEmail">
                        at <strong>{{ emailSandbox.delivery_recipient || staffEmail }}</strong>
                    </span>
                    <span v-if="recipientEmail">, not {{ recipientEmail }}</span>.
                    SMS also routes to your staff profile when enabled.
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Send <strong class="font-semibold text-gray-900 dark:text-white">{{ selectedSurvey?.title }}</strong>
                </p>
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="sendMode" type="radio" value="now" class="accent-primary-600 dark:accent-primary-500" />
                        Send now
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="sendMode" type="radio" value="later" class="accent-primary-600 dark:accent-primary-500" />
                        Send later
                    </label>
                </div>
                <div v-if="sendMode === 'later'">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Schedule for
                        <span class="font-normal text-gray-500 dark:text-gray-400">({{ accountTimezoneLabel }})</span>
                    </label>
                    <input
                        v-model="sendAtLocal"
                        type="datetime-local"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm [color-scheme:light] focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:[color-scheme:dark]"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Times are in your account timezone, not your browser&apos;s local time.
                    </p>
                </div>
                <div v-if="smsOffered" class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/60">
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="includeSms" type="checkbox" class="mt-0.5 accent-primary-600 dark:accent-primary-500" />
                        <span>Also send SMS with survey link</span>
                    </label>
                </div>
                <p v-else-if="smsHint" class="text-xs text-gray-500 dark:text-gray-400">{{ smsHint }}</p>
            </div>

            <div class="mt-6 flex justify-between gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-transparent px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:border-gray-700 dark:hover:bg-gray-800"
                    @click="goBack"
                >
                    {{ step === 'type' ? 'Close' : 'Back' }}
                </button>
                <button
                    v-if="step === 'delivery'"
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50"
                    :disabled="sending || !canSend"
                    @click="submitSend"
                >
                    {{ sending ? 'Sending…' : (sendMode === 'later' ? 'Schedule send' : 'Send now') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
