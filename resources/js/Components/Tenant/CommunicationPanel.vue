<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    recid: { type: [Number, String], required: true },
    parentDomain: { type: String, required: true },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const records = ref([]);
const loading = ref(false);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const enums = reactive({
    communicationTypes: [],
    nextActionTypes: [],
    outcomeActions: [],
    channelTypes: [],
    priorityLevels: [],
    statusTypes: [],
});

const banner = ref({ type: '', text: '' });
const showModal = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const deleting = ref(false);
const currentRecordId = ref(null);

const createForm = reactive({
    communication_type_id: null,
    direction: 'outbound',
    subject: '',
    notes: '',
    is_private: false,
    status_id: 1,
    channel_id: null,
    priority_id: 2,
    tagsString: '',
    outcome_id: null,
    next_action_type_id: null,
    next_action_at: '',
    date_contacted: '',
    assigned_to: null,
});

function notify(type, text) {
    banner.value = { type, text: text || '' };
    if (text) {
        window.setTimeout(() => {
            banner.value = { type: '', text: '' };
        }, 5000);
    }
}

function formatDateTimeLocal(d) {
    if (!d) return '';
    const x = d instanceof Date ? d : new Date(d);
    if (Number.isNaN(x.getTime())) return '';
    const y = x.getFullYear();
    const m = String(x.getMonth() + 1).padStart(2, '0');
    const day = String(x.getDate()).padStart(2, '0');
    const h = String(x.getHours()).padStart(2, '0');
    const min = String(x.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day}T${h}:${min}`;
}

function toIsoOrNull(v) {
    if (!v) return null;
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? null : d.toISOString();
}

function formatDisplay(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString();
}

function enumLabel(options, id) {
    if (id == null) return '—';
    const n = typeof id === 'string' ? parseInt(id, 10) : id;
    const opt = options.find((o) => o.id === n);
    return opt?.name ?? '—';
}

function enumBadgeClass(options, id) {
    const n = typeof id === 'string' ? parseInt(id, 10) : id;
    const opt = options.find((o) => o.id === n);
    return opt?.bgClass || 'bg-gray-200 dark:bg-gray-700';
}

async function fetchRecords(pageNum = 1) {
    loading.value = true;
    try {
        const { data } = await axios.get(route('communications.recorditems'), {
            params: {
                type: props.parentDomain,
                id: props.recid,
                page: pageNum,
            },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const p = data.communications;
        records.value = p?.data ?? [];
        pagination.value = {
            current_page: p?.current_page ?? 1,
            last_page: p?.last_page ?? 1,
            per_page: p?.per_page ?? 15,
            total: p?.total ?? records.value.length,
        };
        enums.communicationTypes = data.communication_types ?? [];
        enums.nextActionTypes = data.next_action_types ?? [];
        enums.outcomeActions = data.outcome ?? [];
        enums.channelTypes = data.channel ?? [];
        enums.priorityLevels = data.priority ?? [];
        enums.statusTypes = data.status ?? [];
    } catch (e) {
        console.error(e);
        notify('error', e.response?.data?.message || 'Failed to load communications.');
        records.value = [];
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    createForm.communication_type_id = null;
    createForm.direction = 'outbound';
    createForm.subject = '';
    createForm.notes = '';
    createForm.is_private = false;
    createForm.status_id = 1;
    createForm.channel_id = null;
    createForm.priority_id = 2;
    createForm.tagsString = '';
    createForm.outcome_id = null;
    createForm.next_action_type_id = null;
    createForm.next_action_at = '';
    createForm.date_contacted = formatDateTimeLocal(new Date());
    createForm.assigned_to = currentUserId.value;
    currentRecordId.value = null;
    isEditing.value = false;
}

function openCreate() {
    resetForm();
    showModal.value = true;
}

function openEdit(row) {
    isEditing.value = true;
    currentRecordId.value = row.id;
    createForm.communication_type_id = row.communication_type_id;
    createForm.direction = row.direction || 'outbound';
    createForm.subject = row.subject || '';
    createForm.notes = row.notes || '';
    createForm.is_private = Boolean(row.is_private);
    createForm.status_id = row.status_id ?? 1;
    createForm.channel_id = row.channel_id;
    createForm.priority_id = row.priority_id ?? 2;
    createForm.tagsString = Array.isArray(row.tags) ? row.tags.join(', ') : '';
    createForm.outcome_id = row.outcome_id;
    createForm.next_action_type_id = row.next_action_type_id;
    createForm.next_action_at = formatDateTimeLocal(row.next_action_at);
    createForm.date_contacted = formatDateTimeLocal(row.date_contacted) || formatDateTimeLocal(new Date());
    createForm.assigned_to = row.assigned_to ?? currentUserId.value;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    resetForm();
}

async function submitCreate() {
    saving.value = true;
    try {
        const tags = createForm.tagsString
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean);
        const payload = {
            communicable_type: props.parentDomain,
            communicable_id: Number(props.recid),
            communication_type_id: createForm.communication_type_id,
            direction: createForm.direction || null,
            subject: createForm.subject || null,
            notes: createForm.notes || null,
            is_private: createForm.is_private,
            status_id: createForm.status_id,
            channel_id: createForm.channel_id,
            priority_id: createForm.priority_id,
            next_action_type_id: createForm.next_action_type_id,
            tags,
            outcome_id: createForm.outcome_id,
            next_action_at: toIsoOrNull(createForm.next_action_at),
            date_contacted: toIsoOrNull(createForm.date_contacted),
            assigned_to: createForm.assigned_to,
        };
        const { data } = await axios.post(route('communications.store'), payload, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        notify('success', data.message || 'Communication logged.');
        closeModal();
        await fetchRecords(pagination.value.current_page);
        router.reload({ only: ['record'], preserveScroll: true, preserveState: true });
    } catch (e) {
        notify('error', e.response?.data?.message || 'Error saving communication.');
    } finally {
        saving.value = false;
    }
}

async function submitUpdate() {
    saving.value = true;
    try {
        const tags = createForm.tagsString
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean);
        const payload = {
            id: currentRecordId.value,
            communicable_type: props.parentDomain,
            communicable_id: Number(props.recid),
            communication_type_id: createForm.communication_type_id,
            direction: createForm.direction || null,
            subject: createForm.subject || null,
            notes: createForm.notes || null,
            is_private: createForm.is_private,
            status_id: createForm.status_id,
            channel_id: createForm.channel_id,
            priority_id: createForm.priority_id,
            next_action_type_id: createForm.next_action_type_id,
            tags,
            outcome_id: createForm.outcome_id,
            next_action_at: toIsoOrNull(createForm.next_action_at),
            date_contacted: toIsoOrNull(createForm.date_contacted),
            assigned_to: createForm.assigned_to,
        };
        const { data } = await axios.put(route('communications.update'), payload, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        notify('success', data.message || 'Communication updated.');
        closeModal();
        await fetchRecords(pagination.value.current_page);
        router.reload({ only: ['record'], preserveScroll: true, preserveState: true });
    } catch (e) {
        notify('error', e.response?.data?.message || 'Error updating communication.');
    } finally {
        saving.value = false;
    }
}

async function deleteRecord() {
    if (!currentRecordId.value) return;
    if (!window.confirm('Delete this communication? This cannot be undone.')) return;
    deleting.value = true;
    try {
        const { data } = await axios.delete(route('communications.destroy'), {
            params: { id: currentRecordId.value },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        notify('success', data.message || 'Deleted.');
        closeModal();
        await fetchRecords(pagination.value.current_page);
        router.reload({ only: ['record'], preserveScroll: true, preserveState: true });
    } catch (e) {
        notify('error', e.response?.data?.message || 'Error deleting communication.');
    } finally {
        deleting.value = false;
    }
}

onMounted(() => {
    resetForm();
    fetchRecords(1);
});

watch(
    () => [props.recid, props.parentDomain],
    () => fetchRecords(1),
);
</script>

<template>
    <div class="communication-panel space-y-4">
        <div
            v-if="banner.text"
            class="rounded-lg px-4 py-2 text-sm"
            :class="
                banner.type === 'error'
                    ? 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200'
                    : 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200'
            "
        >
            {{ banner.text }}
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ pagination.total }} total
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="openCreate"
                >
                    Log activity
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12 text-gray-500 dark:text-gray-400">
            Loading…
        </div>

        <div v-else-if="records.length === 0" class="rounded-lg border border-dashed border-gray-300 py-12 text-center text-gray-500 dark:border-gray-600 dark:text-gray-400">
            No communications logged yet.
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[720px] text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Direction</th>
                        <th class="px-3 py-2">Contacted</th>
                        <th class="px-3 py-2">Subject</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Priority</th>
                        <th class="px-3 py-2">Next</th>
                        <th class="px-3 py-2">Private</th>
                        <th class="px-3 py-2">Outcome</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in records"
                        :key="row.id"
                        class="cursor-pointer border-t border-gray-100 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50"
                        @click="openEdit(row)"
                    >
                        <td class="px-3 py-2">
                            {{ enumLabel(enums.communicationTypes, row.communication_type_id) }}
                        </td>
                        <td class="px-3 py-2 capitalize">{{ row.direction || '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ formatDisplay(row.date_contacted) }}</td>
                        <td class="px-3 py-2 max-w-[200px] truncate">{{ row.subject || '—' }}</td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded px-2 py-0.5 text-xs font-medium text-gray-900 dark:text-white"
                                :class="enumBadgeClass(enums.statusTypes, row.status_id)"
                            >
                                {{ enumLabel(enums.statusTypes, row.status_id) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded px-2 py-0.5 text-xs font-medium text-gray-900 dark:text-white"
                                :class="enumBadgeClass(enums.priorityLevels, row.priority_id)"
                            >
                                {{ enumLabel(enums.priorityLevels, row.priority_id) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs">
                            {{ enumLabel(enums.nextActionTypes, row.next_action_type_id) }}
                            <div class="text-gray-500 dark:text-gray-400">{{ formatDisplay(row.next_action_at) }}</div>
                        </td>
                        <td class="px-3 py-2 text-center">{{ row.is_private ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded px-2 py-0.5 text-xs font-medium text-gray-900 dark:text-white"
                                :class="enumBadgeClass(enums.outcomeActions, row.outcome_id)"
                            >
                                {{ enumLabel(enums.outcomeActions, row.outcome_id) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="pagination.last_page > 1"
            class="flex items-center justify-center gap-3 text-sm text-gray-600 dark:text-gray-400"
        >
            <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:hover:bg-gray-800"
                :disabled="pagination.current_page <= 1"
                @click="fetchRecords(pagination.current_page - 1)"
            >
                Previous
            </button>
            <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:hover:bg-gray-800"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="fetchRecords(pagination.current_page + 1)"
            >
                Next
            </button>
        </div>
    </div>

    <Modal :show="showModal" max-width="3xl" @close="closeModal">
        <div class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ isEditing ? 'Edit communication' : 'Log activity' }}
            </h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Type *</label>
                    <select
                        v-model.number="createForm.communication_type_id"
                        required
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option disabled :value="null">Select…</option>
                        <option v-for="opt in enums.communicationTypes" :key="opt.id" :value="opt.id">
                            {{ opt.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Direction</label>
                    <select
                        v-model="createForm.direction"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="inbound">Inbound</option>
                        <option value="outbound">Outbound</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                    <input
                        v-model="createForm.subject"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <textarea
                        v-model="createForm.notes"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select
                        v-model.number="createForm.status_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option v-for="opt in enums.statusTypes" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                    <select
                        v-model.number="createForm.priority_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option v-for="opt in enums.priorityLevels" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Channel</label>
                    <select
                        v-model.number="createForm.channel_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option :value="null">—</option>
                        <option v-for="opt in enums.channelTypes" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Outcome</label>
                    <select
                        v-model.number="createForm.outcome_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option :value="null">—</option>
                        <option v-for="opt in enums.outcomeActions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Next action type</label>
                    <select
                        v-model.number="createForm.next_action_type_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                        <option :value="null">—</option>
                        <option v-for="opt in enums.nextActionTypes" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Next action at</label>
                    <input
                        v-model="createForm.next_action_at"
                        type="datetime-local"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Date contacted</label>
                    <input
                        v-model="createForm.date_contacted"
                        type="datetime-local"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Assigned to (user id)</label>
                    <input
                        v-model.number="createForm.assigned_to"
                        type="number"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tags (comma-separated)</label>
                    <input
                        v-model="createForm.tagsString"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input v-model="createForm.is_private" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        Private
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                <button
                    v-if="isEditing"
                    type="button"
                    class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                    :disabled="deleting"
                    @click="deleteRecord"
                >
                    {{ deleting ? 'Deleting…' : 'Delete' }}
                </button>
                <div class="ml-auto flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="closeModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="saving || !createForm.communication_type_id"
                        @click="isEditing ? submitUpdate() : submitCreate()"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
