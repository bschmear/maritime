<script>
function getWeekStart(date) {
  const d = new Date(date)
  const day = d.getDay()
  d.setDate(d.getDate() - day + (day === 0 ? -6 : 1))
  d.setHours(0, 0, 0, 0)
  return d
}
function addDays(date, n) {
  const d = new Date(date); d.setDate(d.getDate() + n); return d
}
function fmt(date) { return new Date(date).toISOString().slice(0, 10) }
function fmtTime(decHour) {
  const h = Math.floor(decHour), m = Math.round((decHour - h) * 60)
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`
}
function fmtDateShort(ds) {
  return new Date(ds + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
function isSameDay(a, b) { return fmt(a) === fmt(b) }
</script>

<script setup>
import axios from 'axios'
import { ref, computed, watch, getCurrentInstance, onMounted, onUnmounted } from 'vue'
import ScheduleDayPicker from '@/Components/Tenant/ScheduleDayPicker.vue'
import { useTimezone } from '@/composables/useTimezone'

const { accountTimezone } = useTimezone()

const SNAP_MINUTES   = 15
const MIN_MINUTES    = 15
const MAX_MINUTES    = 480
const EDGE_PX        = 10   // pixels from left/right edge that trigger resize cursor
const DRAG_THRESHOLD = 5    // pixels before a drag is confirmed

const props = defineProps({
  /** Service yard scheduler: work orders only (deliveries use DeliveryScheduler). */
  workOrdersOnly:   { type: Boolean, default: false },
  technicians:      { type: Array,  default: () => [] },
  workOrders:       { type: Array,  default: () => [] },
  locations:        { type: Array,  default: () => ['All Locations'] },
  scheduleDefaults: { type: Object, default: () => ({ workday_hours: 6, workday_start_hour: 8, allow_overlap: false }) },
})

function normalizeScheduleRows(rows) {
  const list = Array.isArray(rows) ? rows : []
  if (!props.workOrdersOnly) {
    return list.map((o) => ({ ...o }))
  }
  return list
    .filter((o) => o.type !== 'delivery' && o.record_type !== 'delivery')
    .map((o) => ({ ...o }))
}

const inertiaApp = getCurrentInstance()
function showToast(type, message) {
  if (!message) return
  const root = inertiaApp?.appContext?.app?._instance?.proxy
  if (typeof root?.createToast === 'function') root.createToast(type, String(message))
}

// ─── State ────────────────────────────────────────────────────────────────────
const localOrders    = ref([])
const weekStart      = ref(getWeekStart(new Date()))
const hoursPerDay    = ref(props.scheduleDefaults?.workday_hours      ?? 6)
const workdayStart   = ref(props.scheduleDefaults?.workday_start_hour ?? 8)
const allowOverlap   = ref(!!props.scheduleDefaults?.allow_overlap)
const locationFilter = ref(props.locations[0] ?? 'All Locations')
const selectedWo     = ref(null)
const modalScheduledAtLocal = ref('')
const isSavingSchedule      = ref(false)

// ─── Interaction state ────────────────────────────────────────────────────────
// One unified "active gesture" at a time.
// mode: 'drag' | 'resize-left' | 'resize-right' | 'resize-bottom' | null
const gesture = ref(null)
/*
  gesture = {
    mode,
    wo,                   // snapshot at gesture start
    techId, dayStr,       // source cell
    startX, startY,       // pointer coords at mousedown
    currentX, currentY,   // live pointer coords
    hasMoved,             // true once threshold crossed

    // drag-specific
    overCell,             // { techId, dayStr, ok } | null

    // resize-left / resize-right / resize-bottom -specific
    startMinutes,         // delivery_duration_minutes at start (resize-bottom / resize-right)
    startHour,            // scheduledDecimalHour at start (resize-left / resize-right start)

    // live computed values (updated in mousemove handler)
    liveMinutes,          // for resize-bottom / resize-right
    liveHour,             // for resize-left / resize-right start
    blocked,              // true if new position would cause overlap
  }
*/

// Hover state — which edge the mouse is near on a specific card
const hoverEdge = ref(null)  // { woId, edge: 'left'|'right'|'bottom'|'body' }

function cancelAll() {
  gesture.value  = null
  hoverEdge.value = null
}

watch(() => props.workOrders, (rows) => {
  cancelAll()
  localOrders.value = normalizeScheduleRows(rows)
}, { deep: true, immediate: true })

watch(weekStart,      () => cancelAll())
watch(locationFilter, () => cancelAll())

watch(() => props.scheduleDefaults, (d) => {
  if (!d) return
  if (d.workday_hours      != null) hoursPerDay.value  = Number(d.workday_hours)
  if (d.workday_start_hour != null) workdayStart.value = Number(d.workday_start_hour)
  if (d.allow_overlap      != null) allowOverlap.value = !!d.allow_overlap
}, { deep: true })

watch(() => props.locations, (locs) => {
  const list = Array.isArray(locs) && locs.length ? locs : ['All Locations']
  if (!list.includes(locationFilter.value)) locationFilter.value = list[0]
}, { deep: true })

watch(() => selectedWo.value, (w) => {
  if (w) modalScheduledAtLocal.value = w.scheduled_at_local || `${w.start_date}T08:00`
})

// ─── Week helpers ─────────────────────────────────────────────────────────────
const weekDays = computed(() => Array.from({ length: 7 }, (_, i) => addDays(weekStart.value, i)))
const weekLabel = computed(() => {
  const s = weekStart.value, e = addDays(s, 6)
  return s.getMonth() === e.getMonth()
    ? `${s.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })} – ${e.getDate()}, ${e.getFullYear()}`
    : `${s.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${e.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${e.getFullYear()}`
})
const prevWeek  = () => { weekStart.value = addDays(weekStart.value, -7) }
const nextWeek  = () => { weekStart.value = addDays(weekStart.value,  7) }
const goToToday = () => { weekStart.value = getWeekStart(new Date()) }
const isToday   = day => isSameDay(day, new Date())
const weekAnchorYmd = computed(() => fmt(weekStart.value))

function jumpToWeekContaining(ymd) {
  if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(String(ymd))) return
  gesture.value = null
  weekStart.value = getWeekStart(new Date(`${ymd}T12:00:00`))
}

// ─── Filtered techs ───────────────────────────────────────────────────────────
const allLocationsLabel = computed(() => props.locations[0] ?? 'All Locations')
function technicianMatchesLocationFilter(tech, sel) {
  if (sel === allLocationsLabel.value) return true
  const raw = tech.location
  if (!raw || raw === '—') return false
  return raw.split(',').map(s => s.trim()).filter(Boolean).includes(sel)
}
const filteredTechs = computed(() => props.technicians.filter(t => technicianMatchesLocationFilter(t, locationFilter.value)))

// ─── WO helpers ───────────────────────────────────────────────────────────────
function computedEndDate(wo) {
  return fmt(addDays(new Date(wo.start_date + 'T00:00:00'), Math.ceil(wo.planned_hours / hoursPerDay.value) - 1))
}
function woSpan(wo) { return Math.ceil(wo.planned_hours / hoursPerDay.value) }
function hpd(wo)    { return wo.planned_hours / woSpan(wo) }
function woCoversDay(wo, dayStr) { return dayStr >= wo.start_date && dayStr <= computedEndDate(wo) }
function isDeliveryOnDay(wo, dayStr) { return wo.type === 'delivery' && dayStr === wo.start_date }

function scheduledDecimalHour(wo) {
  const local = wo.scheduled_at_local || `${wo.start_date}T08:00`
  const tp = local.includes('T') ? local.split('T')[1] : '08:00'
  const [h, m] = tp.split(':').map(n => parseInt(n, 10) || 0)
  return h + m / 60
}

function deliveryAtLocationMinutes(wo) {
  // Use live gesture value if we're actively resizing this WO
  if (gesture.value && gesture.value.wo.id === wo.id) {
    const { mode, liveMinutes } = gesture.value
    if ((mode === 'resize-bottom' || mode === 'resize-right') && liveMinutes != null) return liveMinutes
  }
  const n = Number(wo.delivery_duration_minutes)
  if (!Number.isFinite(n) || n < MIN_MINUTES) return MIN_MINUTES
  return Math.min(MAX_MINUTES, n)
}

function startTimeOnDay(wo, dayStr) {
  if (isDeliveryOnDay(wo, dayStr)) {
    if (gesture.value?.wo?.id === wo.id && gesture.value?.liveHour != null &&
        (gesture.value.mode === 'resize-left' || gesture.value.mode === 'drag-time')) {
      return gesture.value.liveHour
    }
    return scheduledDecimalHour(wo)
  }
  const offset = localOrders.value
    .filter(w => w.technician_id === wo.technician_id && w.id < wo.id && woCoversDay(w, dayStr))
    .reduce((sum, w) => sum + hpd(w), 0)
  return workdayStart.value + offset
}

function endTimeOnDay(wo, dayStr) {
  if (isDeliveryOnDay(wo, dayStr)) {
    return startTimeOnDay(wo, dayStr) + deliveryAtLocationMinutes(wo) / 60
  }
  return startTimeOnDay(wo, dayStr) + hpd(wo)
}

function deliveryCardMinHeight(wo) {
  const blocks = deliveryAtLocationMinutes(wo) / SNAP_MINUTES
  return `${Math.max(2.75, 2.25 + blocks * 0.35)}rem`
}

function ordersForTechOnDay(techId, dayStr) {
  return localOrders.value.filter(w => w.technician_id === techId && woCoversDay(w, dayStr))
}

// ─── Capacity ─────────────────────────────────────────────────────────────────
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

// ─── Cell width → hours ───────────────────────────────────────────────────────
const gridTableRef = ref(null)
function getDayCellWidth() {
  if (!gridTableRef.value) return 120
  const cell = gridTableRef.value.querySelector('tbody td:not(:first-child)')
  return cell ? cell.getBoundingClientRect().width : 120
}
function pxToHours(px) { return (px / getDayCellWidth()) * hoursPerDay.value }

// ─── Snap helpers ─────────────────────────────────────────────────────────────
function snapMins(m) { return Math.min(MAX_MINUTES, Math.max(MIN_MINUTES, Math.round(m / SNAP_MINUTES) * SNAP_MINUTES)) }
function snapHour(h) { return Math.round(h * 4) / 4 }  // nearest 15 min

// ─── Overlap check for time adjustments ──────────────────────────────────────
function hourOverlapsExisting(newStartHour, durationMins, wo) {
  const newEnd = newStartHour + durationMins / 60
  return localOrders.value
    .filter(w => w.id !== wo.id && w.technician_id === wo.technician_id && w.type === 'delivery' && w.start_date === wo.start_date)
    .some(other => {
      const os = scheduledDecimalHour(other)
      const oe = os + deliveryAtLocationMinutes(other) / 60
      return newStartHour < oe && newEnd > os
    })
}

// ─── Edge detection ───────────────────────────────────────────────────────────
// Returns 'left' | 'right' | 'bottom' | 'body'
function detectEdge(event, el) {
  const r = el.getBoundingClientRect()
  const x = event.clientX - r.left
  const y = event.clientY - r.top
  const h = r.height
  const w = r.width
  if (y > h - 8)       return 'bottom'
  if (x <= EDGE_PX)    return 'left'
  if (x >= w - EDGE_PX) return 'right'
  return 'body'
}

// Cursor CSS per edge
function edgeCursor(edge) {
  if (edge === 'left' || edge === 'right') return 'cursor-ew-resize'
  if (edge === 'bottom')                   return 'cursor-ns-resize'
  return 'cursor-grab'
}

// ─── Card mousemove — update hover edge for cursor ───────────────────────────
function onCardMouseMove(wo, dayStr, event) {
  if (gesture.value) return  // don't update hover during active gesture
  if (!isDeliveryOnDay(wo, dayStr)) {
    hoverEdge.value = { woId: wo.id, edge: 'body' }
    return
  }
  const edge = detectEdge(event, event.currentTarget)
  hoverEdge.value = { woId: wo.id, edge }
}
function onCardMouseLeave(wo) {
  if (gesture.value?.wo?.id === wo.id) return
  hoverEdge.value = null
}

function cardCursor(wo, dayStr) {
  if (!isDeliveryOnDay(wo, dayStr)) return 'cursor-grab'
  if (gesture.value?.wo?.id === wo.id) {
    const m = gesture.value.mode
    if (m === 'resize-left' || m === 'resize-right') return 'cursor-ew-resize'
    if (m === 'resize-bottom') return 'cursor-ns-resize'
    return 'cursor-grabbing'
  }
  if (hoverEdge.value?.woId === wo.id) return edgeCursor(hoverEdge.value.edge)
  return 'cursor-grab'
}

// ─── Mousedown — decide gesture mode ─────────────────────────────────────────
function onCardMouseDown(wo, techId, dayStr, event) {
  if (event.button !== 0) return
  event.preventDefault()

  let mode = 'drag'
  if (isDeliveryOnDay(wo, dayStr)) {
    const edge = detectEdge(event, event.currentTarget)
    if (edge === 'left')   mode = 'resize-left'
    else if (edge === 'right')  mode = 'resize-right'
    else if (edge === 'bottom') mode = 'resize-bottom'
  }

  gesture.value = {
    mode,
    wo: { ...wo },  // snapshot
    techId, dayStr,
    startX: event.clientX,
    startY: event.clientY,
    currentX: event.clientX,
    currentY: event.clientY,
    hasMoved: false,
    overCell: null,
    // resize-specific
    startMinutes: deliveryAtLocationMinutes(wo),
    startHour:    scheduledDecimalHour(wo),
    liveMinutes:  deliveryAtLocationMinutes(wo),
    liveHour:     scheduledDecimalHour(wo),
    blocked: false,
  }
}

// ─── Global mousemove ─────────────────────────────────────────────────────────
function onGlobalMouseMove(event) {
  if (!gesture.value) return
  const g = gesture.value
  g.currentX = event.clientX
  g.currentY = event.clientY

  const dx = event.clientX - g.startX
  const dy = event.clientY - g.startY
  if (!g.hasMoved && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
    g.hasMoved = true
  }
  if (!g.hasMoved) return

  if (g.mode === 'drag') {
    // Find which cell the mouse is over using elementFromPoint
    // Temporarily hide the ghost so elementFromPoint hits the cell
    const ghost = document.getElementById('sched-ghost')
    if (ghost) ghost.style.display = 'none'
    const el = document.elementFromPoint(event.clientX, event.clientY)
    if (ghost) ghost.style.display = ''

    const cellEl = el?.closest('[data-sched-cell]')
    if (cellEl) {
      const techId = cellEl.dataset.techId
      const dayStr = cellEl.dataset.dayStr
      const ok = canDrop(techId, dayStr, g.wo)
      g.overCell = { techId, dayStr, ok }
    } else {
      g.overCell = null
    }
  }

  else if (g.mode === 'resize-bottom') {
    // Vertical drag → change duration in minutes (1px = ~1min, snap 15)
    g.liveMinutes = snapMins(g.startMinutes + dy)
  }

  else if (g.mode === 'resize-right') {
    // Horizontal drag right → extend duration
    const deltaHours = pxToHours(dx)
    const deltaMins  = deltaHours * 60
    const newMins    = snapMins(g.startMinutes + deltaMins)
    const blocked    = hourOverlapsExisting(g.startHour, newMins, g.wo)
    g.liveMinutes    = newMins
    g.blocked        = blocked
  }

  else if (g.mode === 'resize-left') {
    // Horizontal drag left → shift start time, duration stays the same
    const deltaHours = pxToHours(dx)  // negative when dragging left
    const rawHour    = g.startHour + deltaHours
    const snapped    = snapHour(rawHour)
    const clamped    = Math.max(workdayStart.value, Math.min(workdayStart.value + hoursPerDay.value - g.startMinutes / 60, snapped))
    const blocked    = hourOverlapsExisting(clamped, g.startMinutes, g.wo)
    g.liveHour       = clamped
    g.blocked        = blocked
  }
}

// ─── Global mouseup — commit ──────────────────────────────────────────────────
async function onGlobalMouseUp(event) {
  if (!gesture.value) return
  const g = { ...gesture.value }
  gesture.value = null
  hoverEdge.value = null

  if (!g.hasMoved) return  // was a click, not a drag

  if (g.mode === 'drag') {
    if (g.overCell?.ok) await commitDrop(g.wo, g.overCell.techId, g.overCell.dayStr)
    return
  }

  const idx = localOrders.value.findIndex(w => w.id === g.wo.id)
  if (idx === -1) return

  if (g.blocked) return  // revert — live display snaps back automatically

  const prev = { ...localOrders.value[idx] }

  function deliveryPlannedHoursFromParts(row, atLocMins) {
    const out  = Math.max(0, Number(row.travel_out_minutes)  || 0)
    const back = Math.max(0, Number(row.travel_back_minutes ?? out) || 0)
    return Math.max(0.25, (out + atLocMins + back) / 60)
  }

  let optimistic

  if (g.mode === 'resize-bottom' || g.mode === 'resize-right') {
    const mins = g.liveMinutes ?? g.startMinutes
    optimistic = { ...prev, delivery_duration_minutes: mins, planned_hours: deliveryPlannedHoursFromParts(prev, mins) }
  }

  else if (g.mode === 'resize-left') {
    const hour    = g.liveHour ?? g.startHour
    const timeStr = decimalHourToTimeStr(hour)
    const newLocal = `${g.dayStr}T${timeStr}`
    optimistic = { ...prev, scheduled_at_local: newLocal }
  }

  if (!optimistic) return
  localOrders.value[idx] = optimistic

  try {
    await persistSchedule(optimistic)
  } catch (e) {
    localOrders.value[idx] = prev
    window.alert(e.response?.data?.message || e.message || 'Could not save.')
  }
}

async function commitDrop(wo, techId, dayStr) {
  const idx = localOrders.value.findIndex(w => w.id === wo.id)
  if (idx === -1) return
  const mergedLocal = mergeDayAndTime(dayStr, wo.scheduled_at_local)
  const prev = { ...localOrders.value[idx] }
  const optimistic = { ...wo, technician_id: techId, start_date: dayStr, scheduled_at_local: mergedLocal }
  localOrders.value[idx] = optimistic
  try {
    await persistSchedule(optimistic)
  } catch (e) {
    localOrders.value[idx] = prev
    window.alert(e.response?.data?.message || e.message || 'Could not save schedule.')
  }
}

onMounted(() => {
  window.addEventListener('mousemove', onGlobalMouseMove)
  window.addEventListener('mouseup',   onGlobalMouseUp)
})
onUnmounted(() => {
  window.removeEventListener('mousemove', onGlobalMouseMove)
  window.removeEventListener('mouseup',   onGlobalMouseUp)
})

// ─── Persistence helpers ──────────────────────────────────────────────────────
function mergeDayAndTime(dayStr, scheduledAtLocal) {
  const tp = scheduledAtLocal?.includes('T') ? scheduledAtLocal.split('T')[1] : '08:00'
  return `${dayStr}T${tp.length === 5 ? tp + ':00' : tp}`
}
function decimalHourToTimeStr(dec) {
  const h = Math.floor(dec), m = Math.round((dec - h) * 60)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}
function applyServerItem(item) {
  const idx = localOrders.value.findIndex(r => r.id === item.id)
  if (idx !== -1) localOrders.value[idx] = { ...item }
}
function scheduleUpdateBody(row, extra = {}) {
  const recordType = props.workOrdersOnly ? 'work_order' : row.record_type
  const body = {
    record_type: recordType,
    record_id: row.record_id,
    technician_id: row.technician_id,
    scheduled_at: mergeDayAndTime(row.start_date, row.scheduled_at_local),
    ...extra,
  }
  if (!props.workOrdersOnly && row.record_type === 'delivery' && row.delivery_duration_minutes != null) {
    body.delivery_duration_minutes = row.delivery_duration_minutes
  }
  return body
}
async function persistSchedule(row, extra = {}) {
  const { data } = await axios.post(route('scheduling.update-item'), scheduleUpdateBody(row, extra))
  if (!data.success || !data.item) throw new Error(data.message || 'Save failed')
  applyServerItem(data.item)
  showToast('success', props.workOrdersOnly ? 'Work order schedule updated.' : (row.record_type === 'delivery' ? 'Delivery schedule updated.' : 'Work order schedule updated.'))
  return data.item
}
let scheduleDefaultsSaveTimer = null
function persistScheduleDefaults() {
  clearTimeout(scheduleDefaultsSaveTimer)
  scheduleDefaultsSaveTimer = setTimeout(async () => {
    scheduleDefaultsSaveTimer = null
    try {
      const { data } = await axios.post(route('scheduling.update-defaults'), { workday_hours: hoursPerDay.value, workday_start_hour: workdayStart.value, allow_overlap: allowOverlap.value })
      if (!data.success) throw new Error(data.message || 'Could not save schedule settings.')
      showToast('success', 'Schedule preferences saved.')
    } catch (e) {
      const d = e.response?.data
      let msg = d?.message || e.message || 'Could not save schedule settings.'
      if (d?.errors) { const first = Object.values(d.errors).flat()[0]; if (first) msg = first }
      showToast('error', msg)
    }
  }, 300)
}
async function saveScheduleFromModal() {
  if (!selectedWo.value || isSavingSchedule.value) return
  isSavingSchedule.value = true
  try {
    const w = { ...selectedWo.value, scheduled_at_local: modalScheduledAtLocal.value, start_date: modalScheduledAtLocal.value.split('T')[0] }
    const item = await persistSchedule(w)
    selectedWo.value = { ...item }
    modalScheduledAtLocal.value = item.scheduled_at_local
  } catch (e) {
    window.alert(e.response?.data?.message || e.message || 'Could not save schedule.')
  } finally {
    isSavingSchedule.value = false
  }
}
const recordShowUrl = computed(() => {
  const w = selectedWo.value
  if (!w?.record_id) return null
  return w.record_type === 'delivery' ? route('deliveries.show', w.record_id) : route('workorders.show', w.record_id)
})

// ─── Ghost computed ───────────────────────────────────────────────────────────
const ghostStyle = computed(() => {
  if (!gesture.value?.hasMoved || gesture.value.mode !== 'drag') return null
  return { left: `${gesture.value.currentX + 14}px`, top: `${gesture.value.currentY + 14}px` }
})

// ─── Display helpers ──────────────────────────────────────────────────────────
function isClippedLeft(wo)  { return wo.start_date < fmt(weekStart.value) }
function isClippedRight(wo) { return computedEndDate(wo) > fmt(addDays(weekStart.value, 6)) }
function initials(name)     { return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) }

const STATUS_COLORS = {
  pending: '#F59E0B', in_progress: '#60A5FA', completed: '#34D399', cancelled: '#9CA3AF',
  draft: '#9CA3AF', open: '#93C5FD', scheduled: '#818CF8', waiting: '#A8A29E', blocked: '#F87171', closed: '#64748B',
  confirmed: '#A78BFA', en_route: '#38BDF8', delivered: '#34D399', rescheduled: '#FBBF24',
}

function woCardBaseClass(wo) {
  return wo.type === 'delivery'
    ? 'bg-secondary-500 hover:bg-secondary-600 dark:bg-secondary-400 dark:hover:bg-secondary-300'
    : 'bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-400'
}

function cellOverClass(techId, dayStr) {
  const g = gesture.value
  if (!g?.hasMoved || g.mode !== 'drag' || !g.overCell) return ''
  if (g.overCell.techId !== techId || g.overCell.dayStr !== dayStr) return ''
  return g.overCell.ok ? 'bg-primary-100 dark:bg-primary-900/30' : 'bg-red-100 dark:bg-red-900/30'
}

function isActiveGesture(wo) { return gesture.value?.wo?.id === wo.id }

function cardRingClass(wo, dayStr) {
  if (!isActiveGesture(wo)) return ''
  const m = gesture.value.mode
  if ((m === 'resize-left' || m === 'resize-right' || m === 'resize-bottom') && gesture.value.blocked) return 'ring-2 ring-red-400'
  if (m !== 'drag') return 'ring-2 ring-white/80'
  return ''
}
</script>

<template>
  <!-- Prevent text selection while gesturing -->
  <div
    class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden grow flex flex-col"
    :class="gesture?.hasMoved ? 'select-none' : ''"
  >

    <!-- ── Toolbar ────────────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <button @click="prevWeek" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-lg leading-none">‹</button>
          <span class="text-sm font-semibold text-gray-900 dark:text-white min-w-[210px] text-center">{{ weekLabel }}</span>
          <button @click="nextWeek" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-lg leading-none">›</button>
          <button @click="goToToday" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Today</button>
          <ScheduleDayPicker
            :model-value="weekAnchorYmd"
            :timezone="accountTimezone"
            aria-label="Jump to week"
            @update:model-value="jumpToWeekContaining"
          />
        </div>
        <div class="flex items-center flex-wrap gap-4">
          <div class="flex items-center gap-1.5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Location</span>
            <select v-model="locationFilter" class="text-sm px-2 pr-6 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
            </select>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Workday</span>
            <select v-model.number="hoursPerDay" class="text-sm px-2 pr-6 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500" @change="persistScheduleDefaults">
              <option v-for="h in [4,5,6,7,8,9,10]" :key="h" :value="h">{{ h }} hrs</option>
            </select>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Start</span>
            <select v-model.number="workdayStart" class="text-sm px-2 pr-6 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500" @change="persistScheduleDefaults">
              <option v-for="h in [6,7,8,9]" :key="h" :value="h">{{ h }}:00 AM</option>
            </select>
          </div>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <span class="text-sm text-gray-500 dark:text-gray-400">Allow overlap</span>
            <button type="button" role="switch" :aria-checked="allowOverlap"
              @click="allowOverlap = !allowOverlap; persistScheduleDefaults()"
              :class="['relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500', allowOverlap ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-600']"
            >
              <span :class="['pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200', allowOverlap ? 'translate-x-4' : 'translate-x-0']" />
            </button>
          </label>
        </div>
      </div>
      <!-- Legend -->
      <div class="flex flex-wrap items-center gap-4 border-t border-gray-200 dark:border-gray-600 pt-3 mt-1 text-sm text-gray-500 dark:text-gray-400">
        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-primary-600 dark:bg-primary-500 shrink-0"></span>Work order</div>
        <template v-if="!workOrdersOnly">
          <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-secondary-500 dark:bg-secondary-400 shrink-0"></span>Delivery</div>
        </template>
        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm shrink-0 border-2 border-dashed border-amber-400"></span>Overlapping</div>
        <span v-if="!workOrdersOnly" class="text-gray-300 dark:text-gray-600">|</span>
        <span v-if="workOrdersOnly" class="text-xs text-gray-400">Drag to reassign technician or day</span>
        <span v-else class="text-xs text-gray-400">Delivery: drag body to reassign · left/right edge to slide time · bottom edge to resize duration</span>
      </div>
    </div>

    <!-- ── Grid ──────────────────────────────────────────────────────────── -->
    <div class="overflow-x-auto grow">
      <table ref="gridTableRef" class="w-full border-collapse table-fixed">
        <thead class="sticky top-0 z-10">
          <tr>
            <th class="w-36 px-3 py-3 text-left bg-gray-50 dark:bg-gray-700/80 border-b border-r border-gray-100 dark:border-gray-700">
              <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Technician</span>
            </th>
            <th v-for="day in weekDays" :key="fmt(day)"
              class="px-1 py-3 text-center border-b border-r border-gray-100 dark:border-gray-700 last:border-r-0"
              :class="isToday(day) ? 'bg-primary-50 dark:bg-primary-900/20' : 'bg-gray-50 dark:bg-gray-700/80'"
            >
              <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isToday(day) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400'">
                {{ day.toLocaleDateString('en-US', { weekday: 'short' }) }}
              </p>
              <p class="text-base font-bold mt-0.5" :class="isToday(day) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white'">
                {{ day.getDate() }}
              </p>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tech in filteredTechs" :key="tech.id" class="border-b border-gray-50 dark:border-gray-700/60">
            <!-- Tech label -->
            <td class="w-36 px-3 py-2 border-r border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 align-middle">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center shrink-0">
                  <span class="text-[11px] font-bold text-primary-700 dark:text-primary-300">{{ initials(tech.name) }}</span>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ tech.name }}</p>
                  <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ tech.location }}</p>
                </div>
              </div>
            </td>

            <!-- Day cells — tagged with data attrs so elementFromPoint can find them -->
            <td v-for="day in weekDays" :key="fmt(day)"
              :data-sched-cell="true"
              :data-tech-id="tech.id"
              :data-day-str="fmt(day)"
              class="border-r border-gray-100 dark:border-gray-700 last:border-r-0 transition-colors align-top p-1.5 pb-2"
              :class="[isToday(day) ? 'bg-primary-50/40 dark:bg-primary-900/10' : '', cellOverClass(tech.id, fmt(day))]"
            >
              <!-- Drop ghost preview (drag mode) -->
              <div
                v-if="gesture?.hasMoved && gesture?.mode === 'drag' && gesture?.overCell?.techId === tech.id && gesture?.overCell?.dayStr === fmt(day)"
                class="pointer-events-none mb-1 flex flex-col gap-0.5 rounded border-2 border-dashed px-2 py-1.5 shadow-sm"
                :class="gesture.overCell.ok
                  ? gesture.wo.type === 'delivery' ? 'border-secondary-600 bg-secondary-500/50' : 'border-primary-600 bg-primary-500/50'
                  : 'border-red-500 bg-red-50/90 dark:bg-red-950/50'"
                aria-hidden="true"
              >
                <div class="flex min-w-0 items-center gap-1">
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ background: STATUS_COLORS[gesture.wo.status] || '#9CA3AF' }"></span>
                  <span class="min-w-0 flex-1 truncate text-xs font-semibold text-white leading-tight">{{ gesture.wo.title }}</span>
                </div>
                <div class="pl-2.5 text-[11px] font-medium" :class="gesture.overCell.ok ? 'text-white/90' : 'text-red-700 dark:text-red-300'">
                  {{ gesture.overCell.ok ? 'Drop here' : 'Cannot drop — over capacity' }}
                </div>
              </div>

              <!-- WO / delivery cards -->
              <div
                v-for="wo in ordersForTechOnDay(tech.id, fmt(day))"
                :key="wo.id"
                class="relative mb-1 flex flex-col gap-0.5 rounded border px-2 py-1.5 last:mb-0 select-none"
                :class="[
                  woCardBaseClass(wo),
                  isActiveGesture(wo) && gesture.mode === 'drag' && gesture.hasMoved ? 'opacity-30' : 'opacity-100',
                  hasOverlapOnDay(tech.id, fmt(day)) ? '!border-2 !border-dashed !border-amber-400/80' : 'border-black/10',
                  cardRingClass(wo, fmt(day)),
                  cardCursor(wo, fmt(day)),
                ]"
                :style="isDeliveryOnDay(wo, fmt(day)) ? { minHeight: deliveryCardMinHeight(wo) } : undefined"
                :title="`${wo.title} · ${fmtTime(startTimeOnDay(wo, fmt(day)))}–${fmtTime(endTimeOnDay(wo, fmt(day)))}${isDeliveryOnDay(wo, fmt(day)) ? ` · ${deliveryAtLocationMinutes(wo)} min on site` : ''}`"
                @mousedown="onCardMouseDown(wo, tech.id, fmt(day), $event)"
                @mousemove="onCardMouseMove(wo, fmt(day), $event)"
                @mouseleave="onCardMouseLeave(wo)"
                @dblclick.stop="selectedWo = { ...wo }"
              >
                <!-- Left edge resize indicator (delivery only) -->
                <div
                  v-if="isDeliveryOnDay(wo, fmt(day))"
                  class="absolute left-0 top-0 bottom-0 w-2.5 rounded-l flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity"
                  :class="hoverEdge?.woId === wo.id && hoverEdge?.edge === 'left' ? 'opacity-100 bg-black/20' : ''"
                  aria-hidden="true"
                >
                  <div class="flex flex-col gap-0.5">
                    <div class="w-0.5 h-2 rounded-full bg-white/70"></div>
                  </div>
                </div>

                <!-- Right edge resize indicator (delivery only) -->
                <div
                  v-if="isDeliveryOnDay(wo, fmt(day))"
                  class="absolute right-0 top-0 bottom-0 w-2.5 rounded-r flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity"
                  :class="hoverEdge?.woId === wo.id && hoverEdge?.edge === 'right' ? 'opacity-100 bg-black/20' : ''"
                  aria-hidden="true"
                >
                  <div class="flex flex-col gap-0.5">
                    <div class="w-0.5 h-2 rounded-full bg-white/70"></div>
                  </div>
                </div>

                <!-- Title row -->
                <div class="flex min-w-0 items-center gap-1">
                  <span v-if="isClippedLeft(wo) && fmt(day) === wo.start_date" class="shrink-0 text-[10px] text-white/70">◀</span>
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ background: STATUS_COLORS[wo.status] || '#9CA3AF' }"></span>
                  <span class="min-w-0 flex-1 truncate text-xs font-semibold leading-tight text-white">{{ wo.title }}</span>
                  <span v-if="isClippedRight(wo) && fmt(day) === computedEndDate(wo)" class="shrink-0 text-[10px] text-white/70">▶</span>
                </div>

                <!-- Time row -->
                <div class="pl-2.5 text-[11px] leading-tight"
                  :class="isActiveGesture(wo) && gesture.blocked ? 'text-red-200' : 'text-white/75'"
                >
                  <template v-if="isDeliveryOnDay(wo, fmt(day))">
                    {{ fmtTime(startTimeOnDay(wo, fmt(day))) }} – {{ fmtTime(endTimeOnDay(wo, fmt(day))) }}
                    <span class="text-white/50">({{ deliveryAtLocationMinutes(wo) }}m)</span>
                    <span v-if="isActiveGesture(wo) && gesture.blocked" class="ml-1 text-red-200">⚠ overlap</span>
                  </template>
                  <template v-else>
                    {{ fmtTime(startTimeOnDay(wo, fmt(day))) }} – {{ fmtTime(endTimeOnDay(wo, fmt(day))) }}
                  </template>
                </div>

                <!-- Bottom resize handle (delivery only) -->
                <div
                  v-if="isDeliveryOnDay(wo, fmt(day))"
                  class="absolute inset-x-0 bottom-0 h-2.5 rounded-b flex items-end justify-center pb-0.5"
                  :class="hoverEdge?.woId === wo.id && hoverEdge?.edge === 'bottom' ? 'bg-black/20' : ''"
                  aria-hidden="true"
                >
                  <span class="w-8 h-0.5 rounded-full bg-white/40"></span>
                </div>
              </div>

              <!-- Overlap banner -->
              <div v-if="hasOverlapOnDay(tech.id, fmt(day))"
                class="inline-flex items-center gap-1 mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700"
              >⚠ Over capacity</div>

              <!-- Capacity bar -->
              <div class="mt-1.5 h-1 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-200"
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

  <!-- ── Floating ghost (drag mode only) ───────────────────────────────────── -->
  <Teleport to="body">
    <div
      v-if="ghostStyle"
      id="sched-ghost"
      class="pointer-events-none fixed z-[300] w-[min(200px,calc(100vw-2rem))] rounded border border-black/10 px-2 py-1.5 shadow-xl"
      :class="gesture?.wo?.type === 'delivery' ? 'bg-secondary-500 dark:bg-secondary-400' : 'bg-primary-600 dark:bg-primary-500'"
      :style="ghostStyle"
      aria-hidden="true"
    >
      <div class="flex min-w-0 items-center gap-1">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ background: STATUS_COLORS[gesture?.wo?.status] || '#9CA3AF' }"></span>
        <span class="min-w-0 flex-1 truncate text-xs font-semibold text-white leading-tight">{{ gesture?.wo?.title }}</span>
      </div>
      <div class="pl-2.5 text-[11px] text-white/80">
        {{ gesture?.wo?.type === 'delivery' ? 'Delivery' : 'Work order' }}
        <template v-if="gesture?.overCell">
          → {{ gesture.overCell.ok ? 'Drop to reassign' : '✕ Over capacity' }}
        </template>
      </div>
    </div>
  </Teleport>

  <!-- ── Detail Modal ──────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-150" enter-from-class="opacity-0" leave-to-class="opacity-0">
      <div v-if="selectedWo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="selectedWo = null">
        <div class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-2xl overflow-hidden">
          <div class="flex items-start justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-700"
            :class="selectedWo.type === 'delivery' ? 'bg-secondary-50 dark:bg-secondary-900/20' : 'bg-primary-50 dark:bg-primary-900/20'"
          >
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xl shrink-0"
                :class="selectedWo.type === 'delivery' ? 'bg-secondary-100 dark:bg-secondary-900/40' : 'bg-primary-100 dark:bg-primary-900/40'">
                {{ selectedWo.type === 'delivery' ? '🚚' : '🔧' }}
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase tracking-widest mb-0.5"
                  :class="selectedWo.type === 'delivery' ? 'text-secondary-600 dark:text-secondary-400' : 'text-primary-600 dark:text-primary-400'">
                  {{ selectedWo.type === 'delivery' ? 'Delivery' : 'Work Order' }}
                </p>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedWo.title }}</h3>
              </div>
            </div>
            <button @click="selectedWo = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors text-xl leading-none mt-0.5">×</button>
          </div>
          <div class="grid grid-cols-2 gap-2 p-4">
            <div class="col-span-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <label class="block text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1.5">Scheduled start</label>
              <input v-model="modalScheduledAtLocal" type="datetime-local" class="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500" />
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">End date</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ fmtDateShort(computedEndDate(selectedWo)) }}</p>
              <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ woSpan(selectedWo) }} day{{ woSpan(selectedWo) !== 1 ? 's' : '' }} @ {{ hoursPerDay }} hrs/day</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Technician</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ technicians.find(t => t.id === selectedWo.technician_id)?.name ?? '—' }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Status</p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: STATUS_COLORS[selectedWo.status] || '#9CA3AF' }"></span>
                <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ (selectedWo.status || '').replace('_', ' ') }}</p>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Planned hours</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedWo.planned_hours }} hrs total</p>
            </div>
            <div v-if="selectedWo.type === 'delivery'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Time on site</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ deliveryAtLocationMinutes(selectedWo) }} min</p>
              <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">Left/right edge → slide time · bottom → resize</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Hrs / day</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ hpd(selectedWo).toFixed(1) }} hrs × {{ woSpan(selectedWo) }} day{{ woSpan(selectedWo) !== 1 ? 's' : '' }}</p>
            </div>
            <div class="col-span-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2.5">
              <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Schedule on start day</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ fmtTime(startTimeOnDay(selectedWo, selectedWo.start_date)) }} – {{ fmtTime(endTimeOnDay(selectedWo, selectedWo.start_date)) }}
              </p>
            </div>
            <div v-if="hasOverlapOnDay(selectedWo.technician_id, selectedWo.start_date)"
              class="col-span-2 flex items-start gap-2 px-3 py-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700"
            >
              <span class="text-amber-500 text-base leading-snug mt-0.5">⚠</span>
              <p class="text-xs text-amber-700 dark:text-amber-300">This work order overlaps with others on its start day. Total assigned hours exceed the {{ hoursPerDay }}-hour workday.</p>
            </div>
          </div>
          <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
            <a v-if="recordShowUrl" :href="recordShowUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Open record in new tab</a>
            <span v-else class="text-xs text-gray-400"> </span>
            <div class="flex items-center gap-2 ms-auto">
              <button type="button" :disabled="isSavingSchedule" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors" @click="saveScheduleFromModal">
                {{ isSavingSchedule ? 'Saving…' : 'Save' }}
              </button>
              <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors" @click="selectedWo = null">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>