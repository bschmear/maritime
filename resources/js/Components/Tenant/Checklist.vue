<template>
    <div class="checklist">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ checklist.name }}</h3>

            <div class="flex items-center gap-2">
                <button
                    @click="openTemplatePicker"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                >
                    <span class="material-icons text-[14px]">library_books</span>
                    From Template
                </button>
                <button
                    @click="saveAsTemplate"
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
                    class="shrink-0 w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                    :class="item.completed
                        ? 'bg-green-500 border-green-500 text-white'
                        : 'border-gray-300 dark:border-gray-500 hover:border-primary-400 dark:hover:border-primary-400'"
                >
                    <span v-if="item.completed" class="material-icons text-[13px] leading-none">check</span>
                </button>

                <!-- Label input -->
                <input
                    v-model="item.label"
                    @input="update"
                    @keydown.enter="focusNextOrAdd(index)"
                    placeholder="Checklist item…"
                    :class="[
                        'flex-1 bg-transparent text-sm outline-none placeholder-gray-300 dark:placeholder-gray-600 transition-colors min-w-0',
                        item.completed
                            ? 'line-through text-gray-400 dark:text-gray-500'
                            : 'text-gray-800 dark:text-gray-100',
                    ]"
                />

                <!-- Remove button -->
                <button
                    @click="removeItem(realIndex(item))"
                    class="shrink-0 opacity-0 group-hover:opacity-100 p-0.5 rounded text-gray-300 hover:text-red-400 dark:text-gray-600 dark:hover:text-red-400 transition-all"
                    aria-label="Remove item"
                >
                    <span class="material-icons text-[16px]">close</span>
                </button>
            </div>

            <!-- Empty state for filtered view -->
            <div
                v-if="filteredItems.length === 0 && checklist.items.length > 0"
                class="py-6 text-center text-sm text-gray-400 dark:text-gray-500"
            >
                No {{ activeFilter === 'pending' ? 'pending' : 'completed' }} items.
            </div>

            <!-- Empty state: no items at all -->
            <div
                v-if="checklist.items.length === 0"
                class="py-8 text-center"
            >
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
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import TemplatePicker from './ChecklistTemplatePicker.vue'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    templates: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['update:modelValue', 'save-template'])

// JSON round-trip safely strips Vue's reactive proxies before cloning
const deepClone = (val) => JSON.parse(JSON.stringify(val))

const checklist = ref(deepClone(props.modelValue))
const showTemplatePicker = ref(false)
const activeFilter = ref('all')

/*
|--------------------------------------------------------------------------
| Filter tabs
|--------------------------------------------------------------------------
*/
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

// Resolve the real index in the full items array (needed when a filter is active)
const realIndex = (item) => checklist.value.items.indexOf(item)

/*
|--------------------------------------------------------------------------
| Progress
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/
function update() {
    emit('update:modelValue', checklist.value)
}

function addItem() {
    checklist.value.items.push({ label: '', completed: false })
    update()
    nextTick(() => {
        const inputs = document.querySelectorAll('.checklist input[placeholder="Checklist item…"]')
        if (inputs.length) inputs[inputs.length - 1].focus()
    })
}

function removeItem(index) {
    checklist.value.items.splice(index, 1)
    update()
}

function toggleItem(item) {
    item.completed = !item.completed
    update()

    // optional API call:
    // axios.patch(`/checklist-items/${item.id}`, { completed: item.completed })
}

function focusNextOrAdd(index) {
    const inputs = document.querySelectorAll('.checklist input[placeholder="Checklist item…"]')
    if (inputs[index + 1]) {
        inputs[index + 1].focus()
    } else {
        addItem()
    }
}

/*
|--------------------------------------------------------------------------
| Templates
|--------------------------------------------------------------------------
*/
function openTemplatePicker() {
    showTemplatePicker.value = true
}

function applyTemplate(template) {
    checklist.value.items = template.items.map(item => ({
        label: item.label,
        completed: false,
    }))
    showTemplatePicker.value = false
    update()
}

function saveAsTemplate() {
    emit('save-template', {
        name: checklist.value.name,
        items: checklist.value.items,
    })
}
</script>
