<script>
function getWeekStart(date) {
  const d = new Date(date)
  const day = d.getDay()
  d.setDate(d.getDate() - day + (day === 0 ? -6 : 1))
  d.setHours(0, 0, 0, 0)
  return d
}
function addDays(date, n) {
  const d = new Date(date)
  d.setDate(d.getDate() + n)
  return d
}
function fmt(date) {
  return new Date(date).toISOString().slice(0, 10)
}
function fmtTime(decHour) {
  const h = Math.floor(decHour)
  const m = Math.round((decHour - h) * 60)
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`
}
function fmtDateShort(ds) {
  return new Date(ds + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
function isSameDay(a, b) {
  return fmt(a) === fmt(b)
}
</script>

<script setup>
import axios from 'axios'
import { ref, computed, watch, getCurrentInstance } from 'vue'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
  /**
   * Technician list.
   * Shape: { id: number, name: string, location: string }
   */
  technicians: {
    type: Array,
    default: () => [],
  },

  /**
   * Work orders / deliveries.
   * NOTE: end_date is NOT used — computed from start_date + planned_hours + hoursPerDay.
   * Shape: { id, title, type, technician_id, start_date, scheduled_at_local (Y-m-d\\TH:i), status, planned_hours, record_id, record_type }
   * id may be a string (e.g. wo-12, dlv-4) to avoid collisions between domains.
   */
  workOrders: {
    type: Array,
    default: () => [],
  },

  /** Location filter options. First entry treated as "show all". */
  locations: {
    type: Array,
    default: () => ['All Locations'],
  },

  /** Defaults from account_settings (workday_hours, workday_start_hour 6–9, allow_overlap). */
  scheduleDefaults: {
    type: Object,
    default: () => ({
      workday_hours: 6,
      workday_start_hour: 8,
      allow_overlap: false,
    }),
  },
})

const inertiaApp = getCurrentInstance()

function showToast(type, message) {
  if (!message) return
  const root = inertiaApp?.appContext?.app?._instance?.proxy
  if (typeof root?.createToast === 'function') {
    root.createToast(type, String(message))
  }
}

// ─── State ────────────────────────────────────────────────────────────────────
const localOrders = ref([])

watch(
  () => props.workOrders,
  (rows) => {
    localOrders.value = Array.isArray(rows) ? rows.map((o) => ({ ...o })) : []
  },
  { deep: true, immediate: true }
)

const weekStart      = ref(getWeekStart(new Date()))
const hoursPerDay    = ref(props.scheduleDefaults?.workday_hours ?? 6)
const workdayStart   = ref(props.scheduleDefaults?.workday_start_hour ?? 8)
const allowOverlap   = ref(!!props.scheduleDefaults?.allow_overlap)
const locationFilter = ref(props.locations[0] ?? 'All Locations')

watch(
  () => props.scheduleDefaults,
  (d) => {
    if (!d || typeof d !== 'object') return
    if (d.workday_hours != null) hoursPerDay.value = Number(d.workday_hours)
    if (d.workday_start_hour != null) workdayStart.value = Number(d.workday_start_hour)
    if (d.allow_overlap != null) allowOverlap.value = !!d.allow_overlap
  },
  { deep: true }
)

watch(
  () => props.locations,
  (locs) => {
    const list = Array.isArray(locs) && locs.length ? locs : ['All Locations']
    if (!list.includes(locationFilter.value)) {
      locationFilter.value = list[0]
    }
  },
  { deep: true }
)

const selectedWo = ref(null)
const modalScheduledAtLocal = ref('')
const isSavingSchedule = ref(false)
const dragging       = ref(null)
const dragOverCell   = ref(null)

watch(
  () => selectedWo.value,
  (w) => {
    if (w) {
      modalScheduledAtLocal.value =
        w.scheduled_at_local || `${w.start_date}T08:00`
    }
  }
)

const recordShowUrl = computed(() => {
  const w = selectedWo.value
  if (!w?.record_id) return null
  return w.record_type === 'delivery'
    ? route('deliveries.show', w.record_id)
    : route('workorders.show', w.record_id)
})

function mergeDayAndTime(dayStr, scheduledAtLocal) {
  const timePart =
    scheduledAtLocal && scheduledAtLocal.includes('T')
      ? scheduledAtLocal.split('T')[1]
      : '08:00'
  const t = timePart.length === 5 ? `${timePart}:00` : timePart
  return `${dayStr}T${t}`
}

function applyServerItem(item) {
  const idx = localOrders.value.findIndex((row) => row.id === item.id)
  if (idx !== -1) {
    localOrders.value[idx] = { ...item }
  }
}

function schedulePayload(row) {
  return mergeDayAndTime(
    row.start_date,
    row.scheduled_at_local || `${row.start_date}T08:00`
  )
}

let scheduleDefaultsSaveTimer = null

function persistScheduleDefaults() {
  clearTimeout(scheduleDefaultsSaveTimer)
  scheduleDefaultsSaveTimer = setTimeout(async () => {
    scheduleDefaultsSaveTimer = null
    try {
      const { data } = await axios.post(route('scheduling.update-defaults'), {
        workday_hours: hoursPerDay.value,
        workday_start_hour: workdayStart.value,
        allow_overlap: allowOverlap.value,
      })
      if (!data.success) {
        throw new Error(data.message || 'Could not save schedule settings.')
      }
      showToast('success', 'Schedule preferences saved.')
    } catch (e) {
      const d = e.response?.data
      let msg = d?.message || e.message || 'Could not save schedule settings.'
      if (d?.errors && typeof d.errors === 'object') {
        const first = Object.values(d.errors).flat()[0]
        if (first) msg = first
      }
      showToast('error', msg)
    }
  }, 300)
}

async function persistSchedule(row) {
  const { data } = await axios.post(route('scheduling.update-item'), {
    record_type: row.record_type,
    record_id: row.record_id,
    technician_id: row.technician_id,
    scheduled_at: schedulePayload(row),
  })

  if (!data.success || !data.item) {
    throw new Error(data.message || 'Save failed')
  }
  applyServerItem(data.item)
  showToast(
    'success',
    row.record_type === 'delivery'
      ? 'Delivery schedule updated.'
      : 'Work order schedule updated.'
  )
  return data.item
}

async function saveScheduleFromModal() {
  if (!selectedWo.value || isSavingSchedule.value) return
  isSavingSchedule.value = true
  try {
    const w = { ...selectedWo.value }
    w.scheduled_at_local = modalScheduledAtLocal.value
    w.start_date = modalScheduledAtLocal.value.split('T')[0]
    const item = await persistSchedule(w)
    selectedWo.value = { ...item }
    modalScheduledAtLocal.value = item.scheduled_at_local
  } catch (e) {
    window.alert(e.response?.data?.message || e.message || 'Could not save schedule.')
  } finally {
    isSavingSchedule.value = false
  }
}

// ─── Week helpers ─────────────────────────────────────────────────────────────
const weekDays = computed(() =>
  Array.from({ length: 7 }, (_, i) => addDays(weekStart.value, i))
)

const weekLabel = computed(() => {
  const s = weekStart.value
  const e = addDays(s, 6)
  return s.getMonth() === e.getMonth()
    ? `${s.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })} – ${e.getDate()}, ${e.getFullYear()}`
    : `${s.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${e.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${e.getFullYear()}`
})

const prevWeek  = () => { weekStart.value = addDays(weekStart.value, -7) }
const nextWeek  = () => { weekStart.value = addDays(weekStart.value,  7) }
const goToToday = () => { weekStart.value = getWeekStart(new Date()) }
const isToday   = day => isSameDay(day, new Date())

// ─── Filtered technicians ─────────────────────────────────────────────────────
// Backend sends comma-separated location names per technician; match membership, not ===.
const allLocationsLabel = computed(() => props.locations[0] ?? 'All Locations')

function technicianMatchesLocationFilter(tech, selectedLoc) {
  if (selectedLoc === allLocationsLabel.value) {
    return true
  }
  const raw = tech.location
  if (!raw || raw === '—') {
    return false
  }
  return raw.split(',').map((s) => s.trim()).filter(Boolean).includes(selectedLoc)
}

const filteredTechs = computed(() => {
  const selected = locationFilter.value
  return props.technicians.filter((t) => technicianMatchesLocationFilter(t, selected))
})

// ─── Computed end date ────────────────────────────────────────────────────────
// end_date = start_date + ceil(planned_hours / hoursPerDay) - 1
function computedEndDate(wo) {
  const span = Math.ceil(wo.planned_hours / hoursPerDay.value)
  return fmt(addDays(new Date(wo.start_date + 'T00:00:00'), span - 1))
}
function woSpan(wo) {
  return Math.ceil(wo.planned_hours / hoursPerDay.value)
}
// Hours worked per day (evenly spread across computed span)
function hpd(wo) {
  return wo.planned_hours / woSpan(wo)
}
function woCoversDay(wo, dayStr) {
  return dayStr >= wo.start_date && dayStr <= computedEndDate(wo)
}

// ─── Capacity helpers ─────────────────────────────────────────────────────────
function usedHours(techId, dayStr, excludeId = null) {
  return localOrders.value
    .filter(w => w.technician_id === techId && w.id !== excludeId && woCoversDay(w, dayStr))
    .reduce((sum, w) => sum + hpd(w), 0)
}

function hasOverlapOnDay(techId, dayStr) {
  const items = localOrders.value.filter(w => w.technician_id === techId && woCoversDay(w, dayStr))
  return items.length > 1 && usedHours(techId, dayStr) > hoursPerDay.value + 0.001
}

function canDrop(techId, newStartDate, wo) {
  if (allowOverlap.value) return true
  for (let i = 0; i < woSpan(wo); i++) {
    const d = fmt(addDays(new Date(newStartDate + 'T00:00:00'), i))
    if (usedHours(techId, d, wo.id) + hpd(wo) > hoursPerDay.value + 0.001) return false
  }
  return true
}

function capacityPct(techId, dayStr) {
  return Math.min(usedHours(techId, dayStr) / hoursPerDay.value, 1) * 100
}

function capFillClass(pct) {
  if (pct >= 100) return 'bg-red-500'
  if (pct >= 75)  return 'bg-amber-400'
  return 'bg-secondary-500'
}

// Stacking offset: hours used by lower-id WOs on the same tech+day
function startTimeOnDay(wo, dayStr) {
  const offset = localOrders.value
    .filter(w => w.technician_id === wo.technician_id && w.id < wo.id && woCoversDay(w, dayStr))
    .reduce((sum, w) => sum + hpd(w), 0)
  return workdayStart.value + offset
}
function endTimeOnDay(wo, dayStr) {
  return startTimeOnDay(wo, dayStr) + hpd(wo)
}

function ordersForTechOnDay(techId, dayStr) {
  return localOrders.value.filter(w => w.technician_id === techId && woCoversDay(w, dayStr))
}

function isClippedLeft(wo) {
  return wo.start_date < fmt(weekStart.value)
}
function isClippedRight(wo) {
  return computedEndDate(wo) > fmt(addDays(weekStart.value, 6))
}

function initials(name) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

const STATUS_COLORS = {
  // Legacy / generic
  pending: '#F59E0B',
  in_progress: '#60A5FA',
  completed: '#34D399',
  cancelled: '#9CA3AF',
  // Work orders (App\Enums\WorkOrder\Status)
  draft: '#9CA3AF',
  open: '#93C5FD',
  scheduled: '#818CF8',
  waiting: '#A8A29E',
  blocked: '#F87171',
  closed: '#64748B',
  // Deliveries (App\Enums\Deliveries\Status)
  confirmed: '#A78BFA',
  en_route: '#38BDF8',
  delivered: '#34D399',
  rescheduled: '#FBBF24',
}

// ─── Drag & drop ──────────────────────────────────────────────────────────────
function onDragStart(wo, event) {
  dragging.value = { wo }
  const ghost = document.createElement('div')
  ghost.style.cssText = 'position:absolute;top:-9999px;opacity:0'
  document.body.appendChild(ghost)
  event.dataTransfer.setDragImage(ghost, 0, 0)
  setTimeout(() => document.body.removeChild(ghost), 0)
}
function onDragEnd() {
  dragging.value     = null
  dragOverCell.value = null
}
function onCellDragOver(techId, dayStr, event) {
  event.preventDefault()
  if (!dragging.value) return
  const ok = canDrop(techId, dayStr, dragging.value.wo)
  dragOverCell.value = { techId, dayStr, ok }
  event.dataTransfer.dropEffect = ok ? 'move' : 'none'
}
function onCellDragLeave(event) {
  if (!event.currentTarget.contains(event.relatedTarget))
    dragOverCell.value = null
}
async function onDrop(techId, dayStr, event) {
  event.preventDefault()
  if (!dragging.value) return
  const { wo } = dragging.value
  if (!canDrop(techId, dayStr, wo)) {
    dragging.value     = null
    dragOverCell.value = null
    return
  }
  const idx = localOrders.value.findIndex((w) => w.id === wo.id)
  if (idx === -1) {
    dragging.value     = null
    dragOverCell.value = null
    return
  }

  const mergedLocal = mergeDayAndTime(dayStr, wo.scheduled_at_local)
  const prev = { ...localOrders.value[idx] }
  const optimistic = {
    ...wo,
    technician_id: techId,
    start_date: dayStr,
    scheduled_at_local: mergedLocal,
  }
  localOrders.value[idx] = optimistic

  try {
    await persistSchedule(optimistic)
  } catch (e) {
    localOrders.value[idx] = prev
    window.alert(e.response?.data?.message || e.message || 'Could not save schedule.')
  }

  dragging.value     = null
  dragOverCell.value = null
}

function cellDragClass(techId, dayStr) {
  if (!dragOverCell.value) return ''
  if (dragOverCell.value.techId !== techId || dragOverCell.value.dayStr !== dayStr) return ''
  return dragOverCell.value.ok
    ? 'bg-primary-100 dark:bg-primary-900/30'
    : 'bg-red-100 dark:bg-red-900/30'
}
</script>

<template>
  <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden grow flex flex-col">

    <!-- ── Toolbar ─────────────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between flex-wrap gap-2">
      <!-- Week nav -->
      <div class="flex items-center gap-2">
        <button
          @click="prevWeek"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-lg leading-none"
        >‹</button>

        <span class="text-sm font-semibold text-gray-900 dark:text-white min-w-[210px] text-center">
          {{ weekLabel }}
        </span>

        <button
          @click="nextWeek"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-lg leading-none"
        >›</button>

        <button
          @click="goToToday"
          class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
        >Today</button>
      </div>

      <!-- Controls -->
      <div class="flex items-center flex-wrap gap-4">
        <div class="flex items-center gap-1.5">
          <span class="text-sm text-gray-500 dark:text-gray-400">Location</span>
          <select
            v-model="locationFilter"
            class="text-sm px-2 pr-6 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
          </select>
        </div>

        <div class="flex items-center gap-1.5">
          <span class="text-sm text-gray-500 dark:text-gray-400">Workday</span>
          <select
            v-model.number="hoursPerDay"
            class="text-sm px-2 pr-6 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500"
            @change="persistScheduleDefaults"
          >
            <option v-for="h in [4,5,6,7,8,9,10]" :key="h" :value="h">{{ h }} hrs</option>
          </select>
        </div>

        <div class="flex items-center gap-1.5">
          <span class="text-sm text-gray-500 dark:text-gray-400">Start</span>
          <select
            v-model.number="workdayStart"
            class="text-sm px-2 pr-6 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500"
            @change="persistScheduleDefaults"
          >
            <option v-for="h in [6,7,8,9]" :key="h" :value="h">{{ h }}:00 AM</option>
          </select>
        </div>

        <label
          class="flex items-center gap-2 cursor-pointer select-none"
          title="When on, items can be dragged onto days that are already full"
        >
          <span class="text-sm text-gray-500 dark:text-gray-400">Allow overlap</span>
          <button
            type="button"
            role="switch"
            :aria-checked="allowOverlap"
            @click="allowOverlap = !allowOverlap; persistScheduleDefaults()"
            :class="[
              'relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1',
              allowOverlap ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-600',
            ]"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200',
                allowOverlap ? 'translate-x-4' : 'translate-x-0',
              ]"
            />
          </button>
        </label>
      </div>
      </div>

      <!-- Legend (own row) -->
      <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 dark:border-gray-600 pt-3 mt-2">
        <div class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-sm bg-primary-600 dark:bg-primary-500 shrink-0"></span>
          <span class="text-sm text-gray-500 dark:text-gray-400">Work Order</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-sm bg-secondary-500 dark:bg-secondary-400 shrink-0"></span>
          <span class="text-sm text-gray-500 dark:text-gray-400">Delivery</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-sm shrink-0 border-2 border-dashed border-amber-400"></span>
          <span class="text-sm text-gray-500 dark:text-gray-400">Overlapping</span>
        </div>
      </div>
    </div>

    <!-- ── Grid ───────────────────────────────────────────────────────────── -->
    <div class="overflow-x-auto grow">
      <table class="w-full border-collapse table-fixed">

        <thead class="sticky top-0 z-10">
          <tr>
            <th class="w-36 px-3 py-3 text-left bg-gray-50 dark:bg-gray-700/80 border-b border-r border-gray-100 dark:border-gray-700">
              <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Technician</span>
            </th>
            <th
              v-for="day in weekDays"
              :key="fmt(day)"
              class="px-1 py-3 text-center border-b border-r border-gray-100 dark:border-gray-700 last:border-r-0"
              :class="isToday(day) ? 'bg-primary-50 dark:bg-primary-900/20' : 'bg-gray-50 dark:bg-gray-700/80'"
            >
              <p
                class="text-[11px] font-semibold uppercase tracking-wide"
                :class="isToday(day) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400'"
              >
                {{ day.toLocaleDateString('en-US', { weekday: 'short' }) }}
              </p>
              <p
                class="text-base font-bold mt-0.5"
                :class="isToday(day) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white'"
              >
                {{ day.getDate() }}
              </p>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="tech in filteredTechs"
            :key="tech.id"
            class="border-b border-gray-50 dark:border-gray-700/60 "
          >
            <!-- Technician label -->
            <td class="w-36 px-3 py-2 border-r border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 align-middle">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center shrink-0">
                  <span class="text-[11px] font-bold text-primary-700 dark:text-primary-300">
                    {{ initials(tech.name) }}
                  </span>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ tech.name }}</p>
                  <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ tech.location }}</p>
                </div>
              </div>
            </td>

            <!-- Day cells -->
            <td
              v-for="day in weekDays"
              :key="fmt(day)"
              class="border-r border-gray-100 dark:border-gray-700 last:border-r-0 transition-colors align-top p-1.5 pb-2"
              :class="[
                isToday(day) ? 'bg-primary-50/40 dark:bg-primary-900/10' : '',
                cellDragClass(tech.id, fmt(day)),
              ]"
              @dragover="onCellDragOver(tech.id, fmt(day), $event)"
              @dragleave="onCellDragLeave($event)"
              @drop="onDrop(tech.id, fmt(day), $event)"
            >
              <!-- WO cards stacked vertically -->
              <div
                v-for="wo in ordersForTechOnDay(tech.id, fmt(day))"
                :key="wo.id"
                class="flex flex-col gap-0.5 px-2 py-1.5 mb-1 last:mb-0 rounded select-none cursor-grab active:cursor-grabbing border transition-opacity"
                :class="[
                  wo.type === 'delivery'
                    ? 'bg-secondary-500 hover:bg-secondary-600 dark:bg-secondary-400 dark:hover:bg-secondary-300'
                    : 'bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-400',
                  dragging?.wo?.id === wo.id ? 'opacity-30' : 'opacity-100',
                  hasOverlapOnDay(tech.id, fmt(day))
                    ? '!border-2 !border-dashed !border-amber-400/80'
                    : 'border-black/10',
                ]"
                draggable="true"
                :title="`${wo.title} · ${fmtTime(startTimeOnDay(wo, fmt(day)))}–${fmtTime(endTimeOnDay(wo, fmt(day)))}${hasOverlapOnDay(tech.id, fmt(day)) ? ' ⚠ Overlapping' : ''}`"
                @dragstart="onDragStart(wo, $event)"
                @dragend="onDragEnd"
                @dblclick.stop="selectedWo = wo"
              >
                <!-- Title row -->
                <div class="flex items-center gap-1 min-w-0">
                  <span
                    v-if="isClippedLeft(wo) && fmt(day) === wo.start_date"
                    class="text-[10px] text-white/70 shrink-0"
                  >◀</span>
                  <span
                    class="w-1.5 h-1.5 rounded-full shrink-0"
                    :style="{ background: STATUS_COLORS[wo.status] || '#9CA3AF' }"
                  ></span>
                  <span class="text-xs font-semibold text-white truncate leading-tight flex-1 min-w-0">
                    {{ wo.title }}
                  </span>
                  <span
                    v-if="isClippedRight(wo) && fmt(day) === computedEndDate(wo)"
                    class="text-[10px] text-white/70 shrink-0"
                  >▶</span>
                </div>
                <!-- Time row -->
                <div class="text-[11px] text-white/75 leading-tight pl-2.5">
                  {{ fmtTime(startTimeOnDay(wo, fmt(day))) }} – {{ fmtTime(endTimeOnDay(wo, fmt(day))) }}
                </div>
              </div>

              <!-- Overlap banner -->
              <div
                v-if="hasOverlapOnDay(tech.id, fmt(day))"
                class="inline-flex items-center gap-1 mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700"
              >
                ⚠ Over capacity
              </div>

              <!-- Capacity bar -->
              <div class="mt-1.5 h-1 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-200"
                  :class="capFillClass(capacityPct(tech.id, fmt(day)))"
                  :style="{ width: capacityPct(tech.id, fmt(day)).toFixed(1) + '%' }"
                ></div>
              </div>

            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Detail Modal ───────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="selectedWo"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="selectedWo = null"
      >
        <div class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-2xl overflow-hidden">

          <!-- Header -->
          <div
            class="flex items-start justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-700"
            :class="selectedWo.type === 'delivery' ? 'bg-secondary-50 dark:bg-secondary-900/20' : 'bg-primary-50 dark:bg-primary-900/20'"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-9 h-9 rounded-lg flex items-center justify-center text-xl shrink-0"
                :class="selectedWo.type === 'delivery' ? 'bg-secondary-100 dark:bg-secondary-900/40' : 'bg-primary-100 dark:bg-primary-900/40'"
              >
                {{ selectedWo.type === 'delivery' ? '🚚' : '🔧' }}
              </div>
              <div>
                <p
                  class="text-[10px] font-bold uppercase tracking-widest mb-0.5"
                  :class="selectedWo.type === 'delivery' ? 'text-secondary-600 dark:text-secondary-400' : 'text-primary-600 dark:text-primary-400'"
                >
                  {{ selectedWo.type === 'delivery' ? 'Delivery' : 'Work Order' }}
                </p>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedWo.title }}</h3>
              </div>
            </div>
            <button
              @click="selectedWo = null"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors text-xl leading-none mt-0.5"
            >×</button>
          </div>

          <!-- Body -->
          <div class="grid grid-cols-2 gap-2 p-4">

            <div class="col-span-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <label class="block text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1.5">
                Scheduled start
              </label>
              <input
                v-model="modalScheduledAtLocal"
                type="datetime-local"
                class="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                Saved to the {{ selectedWo.record_type === 'delivery' ? 'delivery' : 'work order' }} when you click Save.
              </p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">End date</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ fmtDateShort(computedEndDate(selectedWo)) }}
              </p>
              <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                start + {{ woSpan(selectedWo) }} day{{ woSpan(selectedWo) !== 1 ? 's' : '' }} @ {{ hoursPerDay }} hrs/day
              </p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Technician</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ technicians.find(t => t.id === selectedWo.technician_id)?.name ?? '—' }}
              </p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Status</p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <span
                  class="w-2 h-2 rounded-full shrink-0"
                  :style="{ background: STATUS_COLORS[selectedWo.status] || '#9CA3AF' }"
                ></span>
                <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                  {{ (selectedWo.status || '').replace('_', ' ') }}
                </p>
              </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Planned hours</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedWo.planned_hours }} hrs total</p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Hrs / day</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ hpd(selectedWo).toFixed(1) }} hrs × {{ woSpan(selectedWo) }} day{{ woSpan(selectedWo) !== 1 ? 's' : '' }}
              </p>
              <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                ceil({{ selectedWo.planned_hours }} ÷ {{ hoursPerDay }})
              </p>
            </div>

            <div class="col-span-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Schedule on start day</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ fmtTime(startTimeOnDay(selectedWo, selectedWo.start_date)) }}
                –
                {{ fmtTime(endTimeOnDay(selectedWo, selectedWo.start_date)) }}
              </p>
            </div>

            <!-- Overlap warning -->
            <div
              v-if="hasOverlapOnDay(selectedWo.technician_id, selectedWo.start_date)"
              class="col-span-2 flex items-start gap-2 px-3 py-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700"
            >
              <span class="text-amber-500 text-base leading-snug mt-0.5">⚠</span>
              <p class="text-xs text-amber-700 dark:text-amber-300">
                This work order overlaps with others on its start day. Total assigned hours exceed the {{ hoursPerDay }}-hour workday.
              </p>
            </div>

          </div>

          <!-- Footer -->
          <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
            <a
              v-if="recordShowUrl"
              :href="recordShowUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
            >
              Open record in new tab
            </a>
            <span v-else class="text-xs text-gray-400"> </span>
            <div class="flex items-center gap-2 ms-auto">
              <button
                type="button"
                :disabled="isSavingSchedule"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                @click="saveScheduleFromModal"
              >
                {{ isSavingSchedule ? 'Saving…' : 'Save' }}
              </button>
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                @click="selectedWo = null"
              >
                Close
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>
