<template>
    <div class="checklist">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ checklist.name }}</h3>
            <div class="flex items-center gap-2">
                <button
                    v-if="checklist.items.length === 0"
                    @click="openTemplatePicker"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                >
                    <span class="material-icons text-[14px]">library_books</span>
                    From Template
                </button>
                <button
                    @click="openSaveTemplateModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                >
                    <span class="material-icons text-[14px]">bookmark_add</span>
                    Save as Template
                </button>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ progress.completed }} of {{ progress.total }} completed
                </span>
                <span
                    class="text-xs font-semibold"
                    :class="progress.pct === 100 ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                >
                    {{ progress.pct }}%
                </span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="progressBarColor"
                    :style="{ width: progress.pct + '%' }"
                />
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="flex items-center gap-1 mb-3 border-b border-gray-100 dark:border-gray-700">
            <button
                v-for="tab in filterTabs"
                :key="tab.value"
                @click="activeFilter = tab.value"
                :class="[
                    'px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors',
                    activeFilter === tab.value
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300'
                ]"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count !== null"
                    class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold"
                    :class="activeFilter === tab.value
                        ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-300'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                >{{ tab.count }}</span>
            </button>
        </div>

        <!-- Items list -->
        <div class="space-y-1">
            <div
                v-for="(item, index) in filteredItems"
                :key="item.id || index"
                :class="[
                    'group flex items-center gap-3 px-3 py-2.5 rounded-lg border transition-all',
                    item.completed
                        ? 'bg-gray-50 dark:bg-gray-700/30 border-gray-100 dark:border-gray-700/50'
                        : 'bg-white dark:bg-gray-700/50 border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500',
                ]"
            >
                <!-- Checkbox -->
                <button
                    @click="toggleItem(item)"
                    :disabled="togglingItem === (item.id ?? getItemKey(item))"
                    class="shrink-0 w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all disabled:opacity-50"
                    :class="item.completed
                        ? 'bg-green-500 border-green-500 text-white'
                        : 'border-gray-300 dark:border-gray-500 hover:border-primary-400 dark:hover:border-primary-400'"
                >
                    <span v-if="togglingItem === (item.id ?? getItemKey(item))" class="material-icons text-[11px] leading-none animate-spin">autorenew</span>
                    <span v-else-if="item.completed" class="material-icons text-[13px] leading-none">check</span>
                </button>

                <!-- Label: view mode -->
                <template v-if="editingItem !== (item.id ?? getItemKey(item))">
                    <span
                        :class="[
                            'flex-1 text-sm min-w-0 truncate',
                            item.completed
                                ? 'line-through text-gray-400 dark:text-gray-500'
                                : 'text-gray-800 dark:text-gray-100',
                            !item.label ? 'italic text-gray-300 dark:text-gray-600' : '',
                        ]"
                    >
                        {{ item.label || 'Untitled item' }}
                    </span>

                    <!-- Edit button -->
                    <button
                        @click="startEditing(item)"
                        class="shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-gray-300 hover:text-gray-600 dark:text-gray-600 dark:hover:text-gray-300 transition-all"
                        title="Edit"
                    >
                        <span class="material-icons text-[15px]">edit</span>
                    </button>
                </template>

                <!-- Label: edit mode -->
                <template v-else>
                    <input
                        :ref="el => { if (el) editInputRef = el }"
                        v-model="editingLabel"
                        @keydown.enter="commitEdit(item)"
                        @keydown.escape="cancelEdit"
                        placeholder="Checklist item…"
                        class="flex-1 bg-transparent text-sm outline-none text-gray-800 dark:text-gray-100 placeholder-gray-300 dark:placeholder-gray-600 min-w-0"
                    />
                    <!-- Save edit -->
                    <button
                        @click="commitEdit(item)"
                        :disabled="savingItem === (item.id ?? getItemKey(item))"
                        class="shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 rounded-md transition-colors"
                    >
                        <span class="material-icons text-[13px]">
                            {{ savingItem === (item.id ?? getItemKey(item)) ? 'hourglass_empty' : 'save' }}
                        </span>
                        {{ savingItem === (item.id ?? getItemKey(item)) ? '' : 'Save' }}
                    </button>
                    <!-- Cancel edit -->
                    <button
                        @click="cancelEdit"
                        class="shrink-0 p-1 rounded text-gray-300 hover:text-gray-600 dark:text-gray-600 dark:hover:text-gray-300 transition-all"
                        title="Cancel"
                    >
                        <span class="material-icons text-[15px]">close</span>
                    </button>
                </template>

                <!-- Remove button (only in view mode) -->
                <button
                    v-if="editingItem !== (item.id ?? getItemKey(item))"
                    @click="removeItem(realIndex(item))"
                    class="shrink-0 opacity-0 group-hover:opacity-100 p-0.5 rounded text-gray-300 hover:text-red-400 dark:text-gray-600 dark:hover:text-red-400 transition-all"
                    aria-label="Remove item"
                >
                    <span class="material-icons text-[16px]">delete_outline</span>
                </button>
            </div>

            <!-- Empty states -->
            <div
                v-if="filteredItems.length === 0 && checklist.items.length > 0"
                class="py-6 text-center text-sm text-gray-400 dark:text-gray-500"
            >
                No {{ activeFilter === 'pending' ? 'pending' : 'completed' }} items.
            </div>
            <div v-if="checklist.items.length === 0" class="py-8 text-center">
                <span class="material-icons text-3xl text-gray-200 dark:text-gray-600 block mb-2">checklist</span>
                <p class="text-sm text-gray-400 dark:text-gray-500">No items yet. Add one below or load a template.</p>
            </div>
        </div>

        <!-- Add item -->
        <div class="mt-3">
            <button
                @click="addItem"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition-colors"
            >
                <span class="material-icons text-[16px]">add</span>
                Add item
            </button>
        </div>

        <!-- Template Picker modal -->
        <TemplatePicker
            v-if="showTemplatePicker"
            :templates="templates"
            @close="showTemplatePicker = false"
            @select="applyTemplate"
        />
    </div>

    <Teleport to="body">
        <div
            v-if="showSaveModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                @click="!savingTemplate && (showSaveModal = false)"
            />

            <div
                class="relative w-full max-w-md rounded-xl border border-gray-100 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                @click.stop
            >
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                    Save as Template
                </h3>

                <label class="sr-only" for="checklist-template-name">Template name</label>
                <input
                    id="checklist-template-name"
                    v-model="templateName"
                    type="text"
                    placeholder="Template name…"
                    :disabled="savingTemplate"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500"
                    @keydown.enter.prevent="submitSaveTemplate"
                />

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        :disabled="savingTemplate"
                        class="rounded-lg px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 disabled:opacity-50 dark:hover:text-gray-300"
                        @click="showSaveModal = false"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        :disabled="savingTemplate || !templateName.trim()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        @click="submitSaveTemplate"
                    >
                        <span
                            v-if="savingTemplate"
                            class="material-icons animate-spin text-[14px]"
                        >autorenew</span>
                        <span
                            v-else-if="saveSuccess"
                            class="material-icons text-[14px]"
                        >check</span>
                        <span>{{
                            savingTemplate ? 'Saving…' : saveSuccess ? 'Saved!' : 'Save'
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import TemplatePicker from './ChecklistTemplatePicker.vue'

const props = defineProps({
    modelValue: { type: Object, required: true },
    templates:  { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'save-template', 'save-item'])

const deepClone = (val) => JSON.parse(JSON.stringify(val))

const checklist      = ref(deepClone(props.modelValue))
const showTemplatePicker = ref(false)
const activeFilter   = ref('all')
const savingItem     = ref(null)   // item being label-saved
const togglingItem   = ref(null)   // item being checkbox-saved

// Edit state
const editingItem  = ref(null)   // key of item currently being edited
const editingLabel = ref('')
const editInputRef = ref(null)

const showSaveModal = ref(false)
const templateName = ref('')
const savingTemplate = ref(false)
const saveSuccess = ref(false)


// Stable key for new (unsaved) items
let tempKeyCounter = 0
const tempKeys = new WeakMap()
const getItemKey = (item) => {
    if (!tempKeys.has(item)) tempKeys.set(item, `__new_${++tempKeyCounter}`)
    return tempKeys.get(item)
}

watch(
    () => props.modelValue,
    (next) => {
        if (next === checklist.value) return
        const c = deepClone(next)
        if (!Array.isArray(c.items)) c.items = []
        checklist.value = c
        editingItem.value = null
    }
)

// ── Filter tabs ──────────────────────────────────────────────────
const filterTabs = computed(() => [
    { label: 'All',     value: 'all',     count: checklist.value.items.length },
    { label: 'Pending', value: 'pending', count: checklist.value.items.filter(i => !i.completed).length },
    { label: 'Done',    value: 'done',    count: checklist.value.items.filter(i =>  i.completed).length },
])

const filteredItems = computed(() => {
    if (activeFilter.value === 'pending') return checklist.value.items.filter(i => !i.completed)
    if (activeFilter.value === 'done')    return checklist.value.items.filter(i =>  i.completed)
    return checklist.value.items
})

const realIndex = (item) => checklist.value.items.indexOf(item)

// ── Progress ─────────────────────────────────────────────────────
const progress = computed(() => {
    const total     = checklist.value.items.length
    const completed = checklist.value.items.filter(i => i.completed).length
    const pct       = total === 0 ? 0 : Math.round((completed / total) * 100)
    return { total, completed, pct }
})

const progressBarColor = computed(() => {
    const p = progress.value.pct
    if (p === 100) return 'bg-green-500'
    if (p >= 60)   return 'bg-primary-500'
    if (p >= 30)   return 'bg-amber-400'
    return 'bg-gray-300 dark:bg-gray-600'
})

// ── Helpers ──────────────────────────────────────────────────────
function update() {
    emit('update:modelValue', checklist.value)
}

function callSaveItem(item) {
    return new Promise((resolve) => {
        emit('save-item', { item: { ...item }, resolve })
    })
}

// ── Editing ──────────────────────────────────────────────────────
function startEditing(item) {
    editingItem.value  = item.id ?? getItemKey(item)
    editingLabel.value = item.label
    nextTick(() => editInputRef.value?.focus())
}

function cancelEdit() {
    editingItem.value  = null
    editingLabel.value = ''
}

async function commitEdit(item) {
    const trimmed = editingLabel.value.trim()
    if (!trimmed) return
    item.label = trimmed
    update()

    const key = item.id ?? getItemKey(item)
    savingItem.value = key
    await callSaveItem(item)
    savingItem.value  = null
    editingItem.value = null
    editingLabel.value = ''
}

// ── Toggle (auto-save) ───────────────────────────────────────────
async function toggleItem(item) {
    item.completed = !item.completed
    update()

    const key = item.id ?? getItemKey(item)
    togglingItem.value = key
    await callSaveItem(item)
    togglingItem.value = null
}

// ── Add / remove ─────────────────────────────────────────────────
function addItem() {
    const newItem = { label: '', completed: false }
    checklist.value.items.push(newItem)
    update()
    // Open the new item immediately in edit mode
    nextTick(() => {
        const item = checklist.value.items[checklist.value.items.length - 1]
        startEditing(item)
    })
}

function removeItem(index) {
    checklist.value.items.splice(index, 1)
    update()
}

// ── Templates ────────────────────────────────────────────────────
function openTemplatePicker() { showTemplatePicker.value = true }

function applyTemplate(template) {
    checklist.value.items = (template.items ?? []).map(item => ({
        label:     item.label,
        completed: false,
        required:  !!item.required,
    }))
    checklist.value.checklist_template_id = template.id ?? null
    showTemplatePicker.value = false
    update()
}

function openSaveTemplateModal() {
    templateName.value = checklist.value.name || ''
    saveSuccess.value = false
    showSaveModal.value = true
}

async function submitSaveTemplate() {
    const name = templateName.value.trim()
    if (!name) return

    savingTemplate.value = true
    saveSuccess.value = false

    let ok = false
    try {
        ok = await new Promise((resolvePromise) => {
            emit('save-template', {
                name,
                items: checklist.value.items,
                resolve: (success) => resolvePromise(success === true),
            })
        })
    } finally {
        savingTemplate.value = false
    }

    if (ok) {
        saveSuccess.value = true
        setTimeout(() => {
            showSaveModal.value = false
            saveSuccess.value = false
            templateName.value = ''
        }, 1200)
    }
}
</script>