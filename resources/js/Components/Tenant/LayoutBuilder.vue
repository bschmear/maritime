<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';

// ── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    // Pre-populate from the event's asset list if desired
    initialBoats: { type: Array, default: () => [] },
    // Saved layout JSON (stringified state) to restore
    savedLayout: { type: String, default: null },
});

const emit = defineEmits(['save', 'change']);

// ── Constants ────────────────────────────────────────────────────────────────
const SCALE  = 10;   // pixels per foot
const MARGIN = 28;

const COLORS = [
    { label: 'Blue',   value: '#378ADD' },
    { label: 'Teal',   value: '#1D9E75' },
    { label: 'Coral',  value: '#D85A30' },
    { label: 'Green',  value: '#639922' },
    { label: 'Amber',  value: '#BA7517' },
    { label: 'Pink',   value: '#D4537E' },
    { label: 'Purple', value: '#7F77DD' },
    { label: 'Gray',   value: '#888780' },
];

// ── State ────────────────────────────────────────────────────────────────────
const canvasRef    = ref(null);
const spaceW       = ref(60);
const spaceH       = ref(40);
const pendingW     = ref(60);
const pendingH     = ref(40);
const boats        = ref([]);
const selected     = ref(null);
const boatIdCounter = ref(0);

// Modal
const showModal = ref(false);
const form = reactive({ name: '', length: 20, width: 8, color: '#378ADD' });

// Drag state (plain object, not reactive — perf)
const drag = { active: false, offX: 0, offY: 0 };

// ── Computed ─────────────────────────────────────────────────────────────────
const canvasW = computed(() => spaceW.value * SCALE + MARGIN * 2);
const canvasH = computed(() => spaceH.value * SCALE + MARGIN * 2);

const selectedInfo = computed(() => {
    const b = selected.value;
    if (!b) return null;
    const l = b.rotated ? b.w : b.l;
    const w = b.rotated ? b.l : b.w;
    const oob = b.x < 0 || b.y < 0 || b.x + l > spaceW.value || b.y + w > spaceH.value;
    return { name: b.name, dims: `${b.l} × ${b.w} ft`, pos: `${b.x}', ${b.y}'`, oob };
});

const boatCount = computed(() => boats.value.length);

// ── Canvas drawing ───────────────────────────────────────────────────────────
function draw() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const cw = canvasW.value;
    const ch = canvasH.value;
    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    const bg         = isDark ? '#1c1c1e' : '#f0f0eb';
    const floorBg    = isDark ? '#2a2a28' : '#ffffff';
    const gridColor  = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    const labelColor = isDark ? '#666' : '#aaa';
    const borderCol  = isDark ? '#444' : '#ccc';

    ctx.clearRect(0, 0, cw, ch);

    // Background
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, cw, ch);

    // Floor
    ctx.fillStyle = floorBg;
    ctx.strokeStyle = borderCol;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.rect(MARGIN, MARGIN, spaceW.value * SCALE, spaceH.value * SCALE);
    ctx.fill();
    ctx.stroke();

    // Grid lines
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 0.5;
    for (let x = 0; x <= spaceW.value; x += 5) {
        ctx.beginPath();
        ctx.moveTo(MARGIN + x * SCALE, MARGIN);
        ctx.lineTo(MARGIN + x * SCALE, MARGIN + spaceH.value * SCALE);
        ctx.stroke();
    }
    for (let y = 0; y <= spaceH.value; y += 5) {
        ctx.beginPath();
        ctx.moveTo(MARGIN, MARGIN + y * SCALE);
        ctx.lineTo(MARGIN + spaceW.value * SCALE, MARGIN + y * SCALE);
        ctx.stroke();
    }

    // Ruler labels
    ctx.fillStyle = labelColor;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    for (let x = 0; x <= spaceW.value; x += 10) {
        ctx.font = '9px sans-serif';
        ctx.fillText(x + "'", MARGIN + x * SCALE, MARGIN - 8);
    }
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    for (let y = 0; y <= spaceH.value; y += 10) {
        ctx.font = '9px sans-serif';
        ctx.fillText(y + "'", MARGIN - 5, MARGIN + y * SCALE);
    }

    // Boats
    for (const boat of boats.value) {
        drawBoat(ctx, boat, boat === selected.value);
    }
}

function drawBoat(ctx, boat, isSel) {
    const px = MARGIN + boat.x * SCALE;
    const py = MARGIN + boat.y * SCALE;
    const pw = (boat.rotated ? boat.w : boat.l) * SCALE;
    const ph = (boat.rotated ? boat.l : boat.w) * SCALE;

    const oob =
        boat.x < 0 || boat.y < 0 ||
        (boat.rotated ? boat.x + boat.w > spaceW.value : boat.x + boat.l > spaceW.value) ||
        (boat.rotated ? boat.y + boat.l > spaceH.value : boat.y + boat.w > spaceH.value);

    // Body
    ctx.globalAlpha = 0.9;
    ctx.fillStyle   = oob ? '#E24B4A' : boat.color;
    ctx.strokeStyle = isSel ? '#fff' : 'rgba(0,0,0,0.22)';
    ctx.lineWidth   = isSel ? 2.5 : 1;
    ctx.beginPath();
    ctx.roundRect(px, py, pw, ph, 5);
    ctx.fill();
    ctx.stroke();
    ctx.globalAlpha = 1;

    // Selection dashes
    if (isSel) {
        ctx.strokeStyle = '#fff';
        ctx.lineWidth   = 1.5;
        ctx.setLineDash([4, 3]);
        ctx.strokeRect(px - 3, py - 3, pw + 6, ph + 6);
        ctx.setLineDash([]);
    }

    // Bow triangle (right side = bow)
    const bowLen = Math.min(pw * 0.22, 20);
    ctx.fillStyle = 'rgba(0,0,0,0.18)';
    ctx.beginPath();
    ctx.moveTo(px + pw - bowLen, py);
    ctx.lineTo(px + pw,          py + ph / 2);
    ctx.lineTo(px + pw - bowLen, py + ph);
    ctx.closePath();
    ctx.fill();

    // Label
    ctx.fillStyle    = '#fff';
    ctx.textAlign    = 'center';
    ctx.textBaseline = 'middle';
    const maxW = pw - bowLen - 6;
    let label = boat.name;
    ctx.font = `500 ${Math.min(12, Math.max(9, ph * 0.25))}px sans-serif`;
    while (ctx.measureText(label + '…').width > maxW && label.length > 1) {
        label = label.slice(0, -1);
    }
    if (label !== boat.name) label += '…';
    ctx.fillText(label, px + (pw - bowLen) / 2, py + ph / 2 - (ph > 22 ? 7 : 0));

    // Dimensions sub-label
    if (ph > 22) {
        const dim = `${boat.l}×${boat.w}ft`;
        ctx.font      = `400 ${Math.min(10, Math.max(8, ph * 0.18))}px sans-serif`;
        ctx.fillStyle = 'rgba(255,255,255,0.72)';
        ctx.fillText(dim, px + (pw - bowLen) / 2, py + ph / 2 + 7);
    }
}

// ── Hit test ─────────────────────────────────────────────────────────────────
function hitTest(mx, my) {
    for (let i = boats.value.length - 1; i >= 0; i--) {
        const b  = boats.value[i];
        const px = MARGIN + b.x * SCALE;
        const py = MARGIN + b.y * SCALE;
        const pw = (b.rotated ? b.w : b.l) * SCALE;
        const ph = (b.rotated ? b.l : b.w) * SCALE;
        if (mx >= px && mx <= px + pw && my >= py && my <= py + ph) return b;
    }
    return null;
}

function snap(val) { return Math.round(val); }

function getXY(e, canvas) {
    const r  = canvas.getBoundingClientRect();
    const cl = e.touches ? e.touches[0] : e;
    return { mx: cl.clientX - r.left, my: cl.clientY - r.top };
}

// ── Pointer handlers ─────────────────────────────────────────────────────────
function onPointerDown(e) {
    const { mx, my } = getXY(e, canvasRef.value);
    const hit = hitTest(mx, my);
    if (hit) {
        selected.value = hit;
        drag.active = true;
        drag.offX   = mx - (MARGIN + hit.x * SCALE);
        drag.offY   = my - (MARGIN + hit.y * SCALE);
        // Bring to top
        boats.value = [...boats.value.filter(b => b !== hit), hit];
    } else {
        selected.value = null;
    }
    draw();
}

function onPointerMove(e) {
    if (!drag.active || !selected.value) return;
    const { mx, my } = getXY(e, canvasRef.value);
    selected.value.x = snap((mx - drag.offX - MARGIN) / SCALE);
    selected.value.y = snap((my - drag.offY - MARGIN) / SCALE);
    draw();
    emit('change', serializeLayout());
}

function onPointerUp() {
    drag.active = false;
}

// ── Actions ───────────────────────────────────────────────────────────────────
function openModal() {
    form.name   = '';
    form.length = 20;
    form.width  = 8;
    form.color  = '#378ADD';
    showModal.value = true;
}

function addBoat() {
    if (!form.name.trim()) return;
    const boat = {
        id:      ++boatIdCounter.value,
        name:    form.name.trim(),
        l:       parseFloat(form.length) || 20,
        w:       parseFloat(form.width)  || 8,
        color:   form.color,
        x:       2,
        y:       2,
        rotated: false,
    };
    boats.value = [...boats.value, boat];
    selected.value = boat;
    showModal.value = false;
    draw();
    emit('change', serializeLayout());
}

function rotateSelected() {
    if (!selected.value) return;
    selected.value.rotated = !selected.value.rotated;
    draw();
    emit('change', serializeLayout());
}

function deleteSelected() {
    if (!selected.value) return;
    boats.value    = boats.value.filter(b => b !== selected.value);
    selected.value = null;
    draw();
    emit('change', serializeLayout());
}

function applySpace() {
    spaceW.value = Math.max(10, Math.min(200, parseInt(pendingW.value) || 60));
    spaceH.value = Math.max(10, Math.min(200, parseInt(pendingH.value) || 40));
    pendingW.value = spaceW.value;
    pendingH.value = spaceH.value;
    draw();
}

function clearAll() {
    if (!confirm('Remove all boats from the layout?')) return;
    boats.value    = [];
    selected.value = null;
    draw();
    emit('change', serializeLayout());
}

// ── Serialize / restore ───────────────────────────────────────────────────────
function serializeLayout() {
    return JSON.stringify({
        spaceW: spaceW.value,
        spaceH: spaceH.value,
        boats: boats.value.map(b => ({ ...b })),
        boatIdCounter: boatIdCounter.value,
    });
}

function restoreLayout(json) {
    try {
        const data = JSON.parse(json);
        spaceW.value        = data.spaceW ?? 60;
        spaceH.value        = data.spaceH ?? 40;
        pendingW.value      = spaceW.value;
        pendingH.value      = spaceH.value;
        boats.value         = data.boats ?? [];
        boatIdCounter.value = data.boatIdCounter ?? boats.value.length;
        selected.value      = null;
        draw();
    } catch (e) {
        console.warn('LayoutDesigner: could not restore layout', e);
    }
}

function saveLayout() {
    emit('save', serializeLayout());
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    // Restore saved layout or seed from initialBoats prop
    if (props.savedLayout) {
        restoreLayout(props.savedLayout);
    } else if (props.initialBoats?.length) {
        boats.value = props.initialBoats.map((b, i) => ({
            id:      ++boatIdCounter.value,
            name:    b.display_name ?? b.name ?? `Boat ${i + 1}`,
            l:       parseFloat(b.length) || 20,
            w:       parseFloat(b.width)  || 8,
            color:   COLORS[i % COLORS.length].value,
            x:       2 + (i % 5) * 4,
            y:       2 + Math.floor(i / 5) * 4,
            rotated: false,
        }));
        draw();
    } else {
        draw();
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', draw);
});

onUnmounted(() => {
    window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', draw);
});

// Redraw when canvas size changes
watch([canvasW, canvasH], () => {
    // canvas dimensions are bound via :width/:height, Vue updates them next tick
    requestAnimationFrame(draw);
});
</script>

<template>
    <div class="flex flex-col gap-0 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800 select-none">

        <!-- ── Toolbar ─────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">

            <!-- Left: actions -->
            <button
                @click="openModal"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md transition-colors"
            >
                <span class="material-icons text-[14px]">add</span>
                Add boat
            </button>

            <button
                @click="rotateSelected"
                :disabled="!selected"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md transition-colors hover:bg-gray-50 dark:hover:bg-gray-500 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">rotate_right</span>
                Rotate 90°
            </button>

            <button
                @click="deleteSelected"
                :disabled="!selected"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-600 border border-red-200 dark:border-red-700 rounded-md transition-colors hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">delete_outline</span>
                Remove
            </button>

            <button
                @click="clearAll"
                :disabled="!boats.length"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">clear_all</span>
                Clear
            </button>

            <!-- Right: space size + save -->
            <div class="ml-auto flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Space (ft):</span>
                <input
                    v-model.number="pendingW"
                    type="number" min="10" max="200"
                    class="w-14 px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-primary-500"
                    @keydown.enter="applySpace"
                />
                <span class="text-xs text-gray-400">×</span>
                <input
                    v-model.number="pendingH"
                    type="number" min="10" max="200"
                    class="w-14 px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-primary-500"
                    @keydown.enter="applySpace"
                />
                <button
                    @click="applySpace"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500 transition-colors"
                >
                    Apply
                </button>
                <button
                    @click="saveLayout"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-gray-700 dark:bg-gray-600 hover:bg-gray-800 dark:hover:bg-gray-500 rounded-md transition-colors"
                >
                    <span class="material-icons text-[14px]">save</span>
                    Save
                </button>
            </div>
        </div>

        <!-- ── Info bar ────────────────────────────────────────────────────── -->
        <div class="flex items-center gap-3 px-4 py-1.5 text-xs border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
            <template v-if="selectedInfo">
                <span class="font-medium text-gray-900 dark:text-white">{{ selectedInfo.name }}</span>
                <span class="text-gray-400">{{ selectedInfo.dims }}</span>
                <span class="text-gray-400">@ {{ selectedInfo.pos }}</span>
                <span v-if="selectedInfo.oob" class="flex items-center gap-1 text-red-500 font-medium">
                    <span class="material-icons text-[13px]">warning</span>
                    Outside boundary
                </span>
            </template>
            <template v-else>
                <span class="text-gray-400">No boat selected — click a boat to select, then drag to move</span>
            </template>
            <span class="ml-auto font-medium text-gray-700 dark:text-gray-300">
                {{ boatCount }} boat{{ boatCount !== 1 ? 's' : '' }}
            </span>
        </div>

        <!-- ── Canvas ──────────────────────────────────────────────────────── -->
        <div class="overflow-auto bg-gray-100 dark:bg-gray-900 p-4">
            <canvas
                ref="canvasRef"
                :width="canvasW"
                :height="canvasH"
                class="block rounded cursor-default touch-none"
                @mousedown="onPointerDown"
                @mousemove="onPointerMove"
                @mouseup="onPointerUp"
                @mouseleave="onPointerUp"
                @touchstart.prevent="onPointerDown"
                @touchmove.prevent="onPointerMove"
                @touchend="onPointerUp"
            />
        </div>

        <!-- ── Legend ──────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#E24B4A]"></span>
                Out of bounds
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-primary-500 opacity-70"></span>
                Selected (dashed outline)
            </span>
            <span class="text-gray-400 hidden sm:block">Grid = 5 ft intervals · Bow points right</span>
            <span class="ml-auto">{{ spaceW }}' × {{ spaceH }}' floor plan</span>
        </div>
    </div>

    <!-- ── Add Boat Modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click.self="showModal = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 p-6 w-80">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Add boat to layout</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Boat name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Sea Ray 250"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-primary-500"
                                @keydown.enter="addBoat"
                                autofocus
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Length (ft)</label>
                                <input
                                    v-model.number="form.length"
                                    type="number" min="1"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-primary-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Width (ft)</label>
                                <input
                                    v-model.number="form.width"
                                    type="number" min="1"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-primary-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Color</label>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button
                                    v-for="c in COLORS"
                                    :key="c.value"
                                    @click="form.color = c.value"
                                    :title="c.label"
                                    :style="{ background: c.value }"
                                    :class="[
                                        'w-7 h-7 rounded-full transition-all border-2',
                                        form.color === c.value
                                            ? 'border-gray-900 dark:border-white scale-110 shadow'
                                            : 'border-transparent opacity-70 hover:opacity-100 hover:scale-105'
                                    ]"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 justify-end mt-5">
                        <button
                            @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="addBoat"
                            :disabled="!form.name.trim()"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            Add to map
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.15s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
.modal-fade-enter-active .bg-white,
.modal-fade-leave-active .bg-white {
    transition: transform 0.15s ease;
}
.modal-fade-enter-from .bg-white,
.modal-fade-leave-to .bg-white {
    transform: scale(0.96);
}
</style>
