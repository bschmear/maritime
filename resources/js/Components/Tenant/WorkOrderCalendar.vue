<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  workOrders: {
    type: Array,
    required: true
  },
  enumOptions: {
    type: Object,
    default: () => ({})
  }
})

// Current week state
const currentWeekStart = ref(getStartOfWeek(new Date()))

// Get start of week (Sunday)
function getStartOfWeek(date) {
  const d = new Date(date)
  const day = d.getDay()
  const diff = d.getDate() - day
  return new Date(d.setDate(diff))
}

// Generate week days
const weekDays = computed(() => {
  const days = []
  const start = new Date(currentWeekStart.value)

  for (let i = 0; i < 7; i++) {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    days.push({
      date: date,
      dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
      dayNumber: date.getDate(),
      month: date.toLocaleDateString('en-US', { month: 'short' }),
      isToday: isToday(date)
    })
  }

  return days
})

// Check if date is today
function isToday(date) {
  const today = new Date()
  return date.toDateString() === today.toDateString()
}

// Check if date matches
function isSameDay(date1, date2) {
  if (!date1 || !date2) return false
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

// Get work orders for a specific day
function getWorkOrdersForDay(date) {
  return props.workOrders.filter(wo => {
    const scheduled = wo.scheduled_start_at ? new Date(wo.scheduled_start_at) : null
    const due = wo.due_at ? new Date(wo.due_at) : null

    // Show if scheduled on this day OR due on this day OR spans across this day
    if (scheduled && due) {
      const dayStart = new Date(date)
      dayStart.setHours(0, 0, 0, 0)
      const dayEnd = new Date(date)
      dayEnd.setHours(23, 59, 59, 999)

      return (scheduled <= dayEnd && due >= dayStart)
    }

    return isSameDay(scheduled, date) || isSameDay(due, date)
  })
}

// Get status color
function getStatusColor(statusId) {
  const status = props.enumOptions['App\\Enums\\WorkOrder\\Status']?.find(s => s.id === statusId)
  return status?.color || 'blue'
}

// Get status label
function getStatusLabel(statusId) {
  const status = props.enumOptions['App\\Enums\\WorkOrder\\Status']?.find(s => s.id === statusId)
  return status?.name || 'Unknown'
}

// Navigate work order
function viewWorkOrder(id) {
  router.visit(`/workorders/${id}`)
}

// Week navigation
function previousWeek() {
  const newStart = new Date(currentWeekStart.value)
  newStart.setDate(newStart.getDate() - 7)
  currentWeekStart.value = newStart
}

function nextWeek() {
  const newStart = new Date(currentWeekStart.value)
  newStart.setDate(newStart.getDate() + 7)
  currentWeekStart.value = newStart
}

function goToToday() {
  currentWeekStart.value = getStartOfWeek(new Date())
}

// Format week range for header
const weekRangeLabel = computed(() => {
  const start = weekDays.value[0]?.date
  const end = weekDays.value[6]?.date

  if (!start || !end) return ''

  const startStr = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  const endStr = end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })

  return `${startStr} - ${endStr}`
})

// Format time
function formatTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ weekRangeLabel }}
        </h2>
        <div class="flex gap-2">
          <button
            @click="previousWeek"
            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
          >
            <span class="material-icons text-sm">chevron_left</span>
          </button>
          <button
            @click="goToToday"
            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
          >
            Today
          </button>
          <button
            @click="nextWeek"
            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
          >
            <span class="material-icons text-sm">chevron_right</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Week Grid -->
    <div class="grid grid-cols-7 divide-x divide-gray-200 dark:divide-gray-700">
      <div
        v-for="day in weekDays"
        :key="day.date.toISOString()"
        class="min-h-[400px] flex flex-col"
      >
        <!-- Day Header -->
        <div
          :class="[
            'p-3 text-center border-b border-gray-200 dark:border-gray-700',
            day.isToday ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-900/20'
          ]"
        >
          <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
            {{ day.dayName }}
          </div>
          <div
            :class="[
              'text-lg font-semibold mt-1',
              day.isToday
                ? 'inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white'
                : 'text-gray-900 dark:text-white'
            ]"
          >
            {{ day.dayNumber }}
          </div>
        </div>

        <!-- Work Orders for this day -->
<!-- Work Orders for this day -->
<div class="flex-1 p-2 space-y-2 overflow-y-auto">
  <div
    v-for="wo in getWorkOrdersForDay(day.date)"
    :key="wo.id"
    @click="viewWorkOrder(wo.id)"
    :class="[
      'p-2 rounded-lg border-l-4 cursor-pointer transition-all hover:shadow-md',
      'bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700',
      `border-${getStatusColor(wo.status)}-500`
    ]"
  >
    <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mb-1">
      WO-{{ wo.work_order_number }}
    </div>
    <div class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2 mb-1">
      {{ wo.display_name }}
    </div>
    <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300 mb-1">
      <span class="material-icons text-xs">schedule</span>
      <span>{{ formatTime(wo.scheduled_start_at) }}</span>
    </div>
    <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300 mb-1">
      <span class="material-icons text-xs">person</span>
      <span class="truncate">{{ wo.assigned_user?.display_name || 'Unassigned' }}</span>
    </div>
    <div class="mt-1">
      <span
        :class="[
          'inline-block px-2 py-0.5 rounded text-xs font-medium',
          `bg-${getStatusColor(wo.status)}-100 dark:bg-${getStatusColor(wo.status)}-900/30`,
          `text-${getStatusColor(wo.status)}-700 dark:text-${getStatusColor(wo.status)}-300`
        ]"
      >
        {{ getStatusLabel(wo.status) }}
      </span>
    </div>
  </div>

  <!-- Empty State -->
  <div
    v-if="getWorkOrdersForDay(day.date).length === 0"
    class="flex items-center justify-center h-20 text-xs text-gray-400 dark:text-gray-500"
  >
    No work orders
  </div>
</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Tailwind doesn't generate dynamic border colors, so we need to include them */
.border-red-500 { border-left-color: #ef4444 !important; }
.border-blue-500 { border-left-color: #3b82f6 !important; }
.border-green-500 { border-left-color: #22c55e !important; }
.border-yellow-500 { border-left-color: #f59e0b !important; }
.border-orange-500 { border-left-color: #f97316 !important; }
.border-gray-500 { border-left-color: #6b7280 !important; }
.border-indigo-500 { border-left-color: #6366f1 !important; }
.border-slate-500 { border-left-color: #64748b !important; }
.border-purple-500 { border-left-color: #a855f7 !important; }

/* Status badge colors */
.bg-red-100 { background-color: #fee2e2; }
.bg-red-900\/30 { background-color: rgba(127, 29, 29, 0.3); }
.text-red-700 { color: #b91c1c; }
.text-red-300 { color: #fca5a5; }

.bg-blue-100 { background-color: #dbeafe; }
.bg-blue-900\/30 { background-color: rgba(30, 58, 138, 0.3); }
.text-blue-700 { color: #1d4ed8; }
.text-blue-300 { color: #93c5fd; }

.bg-green-100 { background-color: #dcfce7; }
.bg-green-900\/30 { background-color: rgba(20, 83, 45, 0.3); }
.text-green-700 { color: #15803d; }
.text-green-300 { color: #86efac; }

.bg-yellow-100 { background-color: #fef3c7; }
.bg-yellow-900\/30 { background-color: rgba(120, 53, 15, 0.3); }
.text-yellow-700 { color: #a16207; }
.text-yellow-300 { color: #fcd34d; }

.bg-orange-100 { background-color: #ffedd5; }
.bg-orange-900\/30 { background-color: rgba(124, 45, 18, 0.3); }
.text-orange-700 { color: #c2410c; }
.text-orange-300 { color: #fdba74; }

.bg-gray-100 { background-color: #f3f4f6; }
.bg-gray-900\/30 { background-color: rgba(17, 24, 39, 0.3); }
.text-gray-700 { color: #374151; }
.text-gray-300 { color: #d1d5db; }

.bg-indigo-100 { background-color: #e0e7ff; }
.bg-indigo-900\/30 { background-color: rgba(49, 46, 129, 0.3); }
.text-indigo-700 { color: #4338ca; }
.text-indigo-300 { color: #a5b4fc; }

.bg-slate-100 { background-color: #f1f5f9; }
.bg-slate-900\/30 { background-color: rgba(15, 23, 42, 0.3); }
.text-slate-700 { color: #334155; }
.text-slate-300 { color: #cbd5e1; }

.bg-purple-100 { background-color: #f3e8ff; }
.bg-purple-900\/30 { background-color: rgba(88, 28, 135, 0.3); }
.text-purple-700 { color: #7e22ce; }
.text-purple-300 { color: #d8b4fe; }
</style>
