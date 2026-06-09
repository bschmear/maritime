<template>
    <div class="wo-approval-checklist">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ checklist.name }}</h3>
            <div v-if="canManageChecklistStructure" class="flex items-center gap-2">
                <button
                    v-if="checklist.items.length === 0"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    @click="showTemplatePicker = true"
                >
                    <span class="material-icons text-[14px]">library_books</span>
                    From Template
                </button>
                <button
                    v-if="checklist.items.length > 0"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    @click="openSaveTemplateModal"
                >
                    <span class="material-icons text-[14px]">bookmark_add</span>
                    Save as Template
                </button>
            </div>
        </div>

        <div v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
            {{ errorMessage }}
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ answeredCount }} of {{ checklist.items.length }} answered
                    </span>
                    <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ answeredPct }}%</span>
                </div>
                <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full bg-primary-500 transition-all duration-500" :style="{ width: answeredPct + '%' }" />
                </div>
            </div>
            <div v-if="approvalRecord.approval_state === 'pending_manager' || approvalRecord.approval_state === 'approved'">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ managerApprovedCount }} of {{ requiredCount }} required manager-approved
                    </span>
                    <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ managerApprovedPct }}%</span>
                </div>
                <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="managerApprovedPct === 100 ? 'bg-green-500' : 'bg-amber-400'"
                        :style="{ width: managerApprovedPct + '%' }"
                    />
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <div
                v-for="(item, index) in checklist.items"
                :key="item.id || index"
                class="rounded-lg border px-3 py-3 transition-all"
                :class="item.manager_approved
                    ? 'border-green-200 bg-green-50/50 dark:border-green-800 dark:bg-green-900/10'
                    : 'border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-700/40'"
            >
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-base text-gray-800 dark:text-gray-100">{{ item.label || 'Untitled item' }}</span>
                            <span
                                v-if="item.required"
                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                Required
                            </span>
                        </div>
                    </div>

                    <div v-if="canEditResponses" class="flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 p-0.5 dark:border-gray-600">
                        <button
                            v-for="opt in responseOptions"
                            :key="opt.value"
                            type="button"
                            class="rounded-md px-2.5 py-1 text-xs font-semibold transition-colors"
                            :class="item.response === opt.value
                                ? opt.activeClass
                                : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-600'"
                            :disabled="saving"
                            @click="setResponse(item, opt.value)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <div v-else class="shrink-0">
                        <span
                            class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold"
                            :class="responseBadgeClass(item.response)"
                        >
                            {{ responseLabel(item.response) }}
                        </span>
                    </div>

                    <div v-if="canManagerApprove && item.response" class="shrink-0">
                        <button
                            v-if="!item.manager_approved"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="approvingLineId === item.id"
                            @click="approveLine(item)"
                        >
                            <span v-if="approvingLineId === item.id" class="material-icons animate-spin text-[14px]">autorenew</span>
                            <span v-else class="material-icons text-[14px]">verified</span>
                            Approve
                        </button>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-lg bg-green-100 px-2.5 py-1.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300"
                        >
                            <span class="material-icons text-[14px]">check_circle</span>
                            Approved
                        </span>
                    </div>
                    <div v-else-if="item.manager_approved" class="shrink-0">
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-600 dark:text-green-400">
                            <span class="material-icons text-[14px]">check_circle</span>
                            Manager approved
                        </span>
                    </div>
                </div>

                <div v-if="canManageChecklistStructure" class="mt-2 flex items-center gap-2">
                    <button
                        v-if="editingItem !== (item.id ?? getItemKey(item))"
                        type="button"
                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        @click="startEditing(item)"
                    >
                        Edit label
                    </button>
                    <button
                        type="button"
                        class="text-xs text-gray-400 hover:text-red-500"
                        @click="removeItem(index)"
                    >
                        Remove
                    </button>
                </div>

                <div v-if="editingItem === (item.id ?? getItemKey(item))" class="mt-2 flex items-center gap-2">
                    <input
                        v-model="editingLabel"
                        type="text"
                        class="flex-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        @keydown.enter="commitEdit(item)"
                        @keydown.escape="cancelEdit"
                    />
                    <button type="button" class="text-xs font-medium text-primary-600" @click="commitEdit(item)">Save</button>
                    <button type="button" class="text-xs text-gray-400" @click="cancelEdit">Cancel</button>
                </div>
            </div>

            <div v-if="checklist.items.length === 0" class="py-8 text-center">
                <span class="material-icons text-3xl text-gray-200 dark:text-gray-600 block mb-2">checklist</span>
                <p class="text-sm text-gray-400 dark:text-gray-500">No checklist items yet. Add one below or load a template.</p>
            </div>
        </div>

        <div v-if="canManageChecklistStructure" class="mt-3">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition-colors"
                @click="addItem"
            >
                <span class="material-icons text-[16px]">add</span>
                Add item
            </button>
        </div>

        <div
            v-if="canSubmitForApproval"
            class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
        >
            <label
                class="flex items-start gap-3"
                :class="allItemsAnswered ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
            >
                <input
                    v-model="technicianConfirm"
                    type="checkbox"
                    class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
                    :disabled="!allItemsAnswered"
                />
                <span class="text-sm text-gray-700 dark:text-gray-200">
                    I confirm work is complete and pending approval.
                </span>
            </label>
            <p v-if="!allItemsAnswered" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                Answer True, False, or N/A on every checklist line before submitting.
            </p>
            <button
                type="button"
                class="mt-3 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                :disabled="!technicianConfirm || !allItemsAnswered || submitting"
                @click="submitForApproval"
            >
                <span v-if="submitting" class="material-icons animate-spin text-[16px]">autorenew</span>
                Submit for manager approval
            </button>
        </div>

        <div
            v-if="canManagerSignoff"
            class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <label class="flex items-start gap-3 cursor-pointer">
                <input v-model="managerConfirm" type="checkbox" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                <span class="text-sm text-gray-700 dark:text-gray-200">
                    I reviewed final checklist and everything looks complete.
                </span>
            </label>
            <button
                type="button"
                class="mt-3 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                :disabled="!managerConfirm || !allRequiredManagerApproved || signingOff"
                @click="managerSignoff"
            >
                <span v-if="signingOff" class="material-icons animate-spin text-[16px]">autorenew</span>
                Complete manager sign-off
            </button>
            <p v-if="!allRequiredManagerApproved" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                All required lines must be manager-approved before final sign-off.
            </p>
        </div>

        <div v-if="approvalRecord.approval_state === 'approved'" class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
            Manager sign-off complete. This work order may be closed.
        </div>

        <TemplatePicker
            v-if="showTemplatePicker"
            :templates="templates"
            @close="showTemplatePicker = false"
            @select="applyTemplate"
        />

        <Teleport to="body">
            <div v-if="showSaveModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="!savingTemplate && (showSaveModal = false)" />
                <div class="relative w-full max-w-md rounded-xl border border-gray-100 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800" @click.stop>
                    <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Save as Template</h3>
                    <input
                        v-model="templateName"
                        type="text"
                        placeholder="Template name…"
                        :disabled="savingTemplate"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-base text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        @keydown.enter.prevent="submitSaveTemplate"
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-gray-500" :disabled="savingTemplate" @click="showSaveModal = false">Cancel</button>
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="savingTemplate || !templateName.trim()"
                            @click="submitSaveTemplate"
                        >
                            {{ savingTemplate ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import TemplatePicker from './ChecklistTemplatePicker.vue'

const page = usePage()

const props = defineProps({
    modelValue: { type: Object, required: true },
    templates: { type: Array, default: () => [] },
    workOrderId: { type: [Number, String], required: true },
    approvalRecord: { type: Object, required: true },
    currentUserId: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue', 'approval-updated'])

const deepClone = (val) => JSON.parse(JSON.stringify(val))

const checklist = ref(deepClone(props.modelValue))
const errorMessage = ref(null)
const saving = ref(false)
const submitting = ref(false)
const signingOff = ref(false)
const approvingLineId = ref(null)
const technicianConfirm = ref(false)
const managerConfirm = ref(false)
const showTemplatePicker = ref(false)
const showSaveModal = ref(false)
const templateName = ref('')
const savingTemplate = ref(false)

const editingItem = ref(null)
const editingLabel = ref('')
let tempKeyCounter = 0
const tempKeys = new WeakMap()
const getItemKey = (item) => {
    if (!tempKeys.has(item)) tempKeys.set(item, `__new_${++tempKeyCounter}`)
    return tempKeys.get(item)
}

const responseOptions = [
    { value: 'true', label: 'T', activeClass: 'bg-green-600 text-white' },
    { value: 'false', label: 'F', activeClass: 'bg-red-600 text-white' },
    { value: 'na', label: 'N/A', activeClass: 'bg-gray-600 text-white' },
]

watch(
    () => props.modelValue,
    (next) => {
        checklist.value = deepClone(next)
        if (!Array.isArray(checklist.value.items)) checklist.value.items = []
    },
    { deep: true },
)

watch(
    () => props.approvalRecord,
    () => {
        errorMessage.value = null
    },
    { deep: true },
)

const isTechnician = computed(() => {
    return props.currentUserId && Number(props.approvalRecord.assigned_user_id) === Number(props.currentUserId)
})

const isManager = computed(() => {
    return props.currentUserId && Number(props.approvalRecord.manager_user_id) === Number(props.currentUserId)
})

const isLocked = computed(() => !!props.approvalRecord.manager_signed_off_at)

const tenantRoleSlug = computed(() => page.props.tenant_role_slug ?? null)

const canManageChecklistStructure = computed(() => {
    if (isLocked.value || props.approvalRecord.approval_state !== 'in_progress') {
        return false
    }

    return ['admin', 'manager'].includes(tenantRoleSlug.value)
})

const canEditResponses = computed(() => {
    return !isLocked.value && props.approvalRecord.approval_state === 'in_progress' && isTechnician.value
})

const canSubmitForApproval = computed(() => {
    return props.approvalRecord.approval_state === 'in_progress' && isTechnician.value && checklist.value.items.length > 0
})

const canManagerApprove = computed(() => {
    return props.approvalRecord.approval_state === 'pending_manager' && isManager.value
})

const canManagerSignoff = computed(() => {
    return props.approvalRecord.approval_state === 'pending_manager' && isManager.value
})

const answeredCount = computed(() => checklist.value.items.filter((i) => i.response).length)
const answeredPct = computed(() => {
    const total = checklist.value.items.length
    return total === 0 ? 0 : Math.round((answeredCount.value / total) * 100)
})

const allItemsAnswered = computed(() => {
    return checklist.value.items.length > 0
        && checklist.value.items.every((item) => !!item.response)
})

watch(allItemsAnswered, (answered) => {
    if (!answered) {
        technicianConfirm.value = false
    }
})

const requiredCount = computed(() => checklist.value.items.filter((i) => i.required).length)
const managerApprovedCount = computed(() => checklist.value.items.filter((i) => i.required && i.manager_approved).length)
const managerApprovedPct = computed(() => {
    const total = requiredCount.value
    return total === 0 ? 100 : Math.round((managerApprovedCount.value / total) * 100)
})

const allRequiredManagerApproved = computed(() => {
    return checklist.value.items.every((i) => !i.required || i.manager_approved)
})

function responseLabel(value) {
    if (value === 'true') return 'True'
    if (value === 'false') return 'False'
    if (value === 'na') return 'N/A'
    return '—'
}

function responseBadgeClass(value) {
    if (value === 'true') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
    if (value === 'false') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
    if (value === 'na') return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
    return 'bg-gray-100 text-gray-500'
}

function emitUpdate() {
    emit('update:modelValue', checklist.value)
}

async function persistChecklist() {
    saving.value = true
    errorMessage.value = null
    try {
        const { data } = await axios.put(route('workorders.checklist.update', props.workOrderId), {
            name: checklist.value.name,
            checklist_template_id: checklist.value.checklist_template_id ?? null,
            items: checklist.value.items.map((i) => ({
                id: i.id ?? null,
                label: i.label,
                required: !!i.required,
                response: i.response ?? null,
            })),
        })
        if (data.success && data.checklist) {
            checklist.value = data.checklist
            emitUpdate()
            return true
        }
        errorMessage.value = data.message || 'Failed to save checklist.'
        return false
    } catch (err) {
        errorMessage.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to save checklist.'
        return false
    } finally {
        saving.value = false
    }
}

async function setResponse(item, value) {
    item.response = item.response === value ? null : value
    emitUpdate()
    await persistChecklist()
}

function startEditing(item) {
    editingItem.value = item.id ?? getItemKey(item)
    editingLabel.value = item.label
}

function cancelEdit() {
    editingItem.value = null
    editingLabel.value = ''
}

async function commitEdit(item) {
    const trimmed = editingLabel.value.trim()
    if (!trimmed) return
    item.label = trimmed
    emitUpdate()
    editingItem.value = null
    editingLabel.value = ''
    await persistChecklist()
}

async function addItem() {
    checklist.value.items.push({ label: 'New checklist item', required: true, response: null, manager_approved: false })
    emitUpdate()
    await persistChecklist()
    const item = checklist.value.items[checklist.value.items.length - 1]
    nextTick(() => startEditing(item))
}

async function removeItem(index) {
    checklist.value.items.splice(index, 1)
    emitUpdate()
    await persistChecklist()
}

function applyTemplate(template) {
    checklist.value.items = (template.items ?? []).map((item) => ({
        label: item.label,
        required: !!item.required,
        response: null,
        manager_approved: false,
    }))
    checklist.value.checklist_template_id = template.id ?? null
    showTemplatePicker.value = false
    emitUpdate()
    persistChecklist()
}

function openSaveTemplateModal() {
    templateName.value = checklist.value.name || ''
    showSaveModal.value = true
}

async function submitSaveTemplate() {
    const name = templateName.value.trim()
    if (!name) return
    savingTemplate.value = true
    errorMessage.value = null
    try {
        await axios.post(route('workorders.checklist-templates.store'), {
            name,
            items: checklist.value.items.map((i) => ({
                label: i.label,
                required: !!i.required,
            })),
        })
        showSaveModal.value = false
        await router.reload({ only: ['checklistTemplates'] })
    } catch (err) {
        errorMessage.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to save template.'
    } finally {
        savingTemplate.value = false
    }
}

async function submitForApproval() {
    if (!technicianConfirm.value) return
    submitting.value = true
    errorMessage.value = null
    try {
        const { data } = await axios.post(route('workorders.checklist.submit-for-approval', props.workOrderId))
        if (data.success) {
            emit('approval-updated', data.record)
            technicianConfirm.value = false
            await router.reload({ only: ['record', 'checklist'] })
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to submit for approval.'
    } finally {
        submitting.value = false
    }
}

async function approveLine(item) {
    if (!item.id) return
    approvingLineId.value = item.id
    errorMessage.value = null
    try {
        const { data } = await axios.post(route('workorders.checklist.manager-approve-line', props.workOrderId), {
            item_id: item.id,
        })
        if (data.success && data.checklist) {
            checklist.value = data.checklist
            emitUpdate()
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to approve line.'
    } finally {
        approvingLineId.value = null
    }
}

async function managerSignoff() {
    if (!managerConfirm.value) return
    signingOff.value = true
    errorMessage.value = null
    try {
        const { data } = await axios.post(route('workorders.checklist.manager-signoff', props.workOrderId))
        if (data.success) {
            emit('approval-updated', data.record)
            managerConfirm.value = false
            await router.reload({ only: ['record', 'checklist'] })
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to complete sign-off.'
    } finally {
        signingOff.value = false
    }
}
</script>
