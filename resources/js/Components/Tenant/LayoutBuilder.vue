<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    /** Assigned boats, engines, and trailers for this event (each row includes `type` from the asset). */
    initialLayoutItems: { type: Array, default: () => [] },
    layoutSpace: {
        type: Object,
        default: () => ({ width_ft: 60, height_ft: 40 }),
    },
    attachAssetConfig: { type: Object, default: null },
});

const emit = defineEmits(['save', 'change', 'request-attach-asset']);

const MARGIN = 28;

/** @see App\Enums\Inventory\AssetType */
const ASSET_TYPE = { BOAT: 1, ENGINE: 2, TRAILER: 3 };

/** Tailwind text-*-500 — matches Boat Show event asset list (boat blue, engine orange, trailer green). */
const LAYOUT_COLOR_BY_TYPE = {
    [ASSET_TYPE.BOAT]: '#3B82F6',
    [ASSET_TYPE.ENGINE]: '#F97316',
    [ASSET_TYPE.TRAILER]: '#22C55E',
};

const CUSTOM_LAYOUT_COLOR = '#64748B';

function layoutFillColor(item) {
    if (item.assetType == null) return CUSTOM_LAYOUT_COLOR;
    return LAYOUT_COLOR_BY_TYPE[item.assetType] ?? CUSTOM_LAYOUT_COLOR;
}

/** Stacking: trailers bottom (0), boats middle (1), engines top (2). Custom shapes use boat tier. */
function typeDrawTier(assetType) {
    if (assetType == null) return 1;
    const t = Number(assetType);
    if (t === ASSET_TYPE.TRAILER) return 0;
    if (t === ASSET_TYPE.BOAT) return 1;
    if (t === ASSET_TYPE.ENGINE) return 2;
    return 1;
}

function stackTypeKey(item) {
    return item.assetType == null ? 'custom' : String(item.assetType);
}

function itemFootprint(item) {
    const fw = item.rotated ? item.w : item.l;
    const fh = item.rotated ? item.l : item.w;
    return { x: item.x, y: item.y, w: fw, h: fh };
}

function footprintsOverlap(a, b) {
    return !(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y);
}

function hasSameTypeFootprintOverlap(moving, allItems) {
    const m = itemFootprint(moving);
    for (const o of allItems) {
        if (o === moving || !o.includeInLayout) continue;
        if (stackTypeKey(o) !== stackTypeKey(moving)) continue;
        if (footprintsOverlap(m, itemFootprint(o))) return true;
    }
    return false;
}

function nudgeItemToFreeSpot(item) {
    if (!item.includeInLayout) return;
    const maxY = Math.max(0, Math.ceil(spaceH.value));
    const maxX = Math.max(0, Math.ceil(spaceW.value));
    for (let y = 0; y < maxY; y++) {
        for (let x = 0; x < maxX; x++) {
            item.x = x;
            item.y = y;
            if (!hasSameTypeFootprintOverlap(item, boats.value)) return;
        }
    }
}

const canvasRef = ref(null);
const containerRef = ref(null);
const containerW = ref(800);

const spaceW = ref(60);
const spaceH = ref(40);
const pendingW = ref(60);
const pendingH = ref(40);
const boats = ref([]);
const selected = ref(null);
const boatIdCounter = ref(0);

const showModal = ref(false);
const modalMode = ref('add'); // 'add' | 'edit'
const form = reactive({ name: '', length: 20, width: 8 });
const editingBoat = ref(null);

const drag = { active: false, offX: 0, offY: 0, lastValidX: 0, lastValidY: 0 };

const SCALE = computed(() =>
    Math.max(4, Math.floor((containerW.value - MARGIN * 2) / spaceW.value)),
);

const canvasW = computed(() => spaceW.value * SCALE.value + MARGIN * 2);
const canvasH = computed(() => spaceH.value * SCALE.value + MARGIN * 2);

const onLayoutBoats = computed(() => boats.value.filter((b) => b.includeInLayout));
const offLayoutBoats = computed(() => boats.value.filter((b) => !b.includeInLayout));

/** Selected item draws on top and wins hit-tests; deselect restores type-tier + z_index order. */
const boatsDrawOrder = computed(() => {
    const sel = selected.value;
    return [...onLayoutBoats.value].sort((a, b) => {
        const aSel = sel != null && a === sel;
        const bSel = sel != null && b === sel;
        if (aSel !== bSel) return aSel ? 1 : -1;
        const ta = typeDrawTier(a.assetType);
        const tb = typeDrawTier(b.assetType);
        if (ta !== tb) return ta - tb;
        return (a.zIndex ?? 0) - (b.zIndex ?? 0);
    });
});

const boatsHitOrder = computed(() => {
    const sel = selected.value;
    return [...onLayoutBoats.value].sort((a, b) => {
        const aSel = sel != null && a === sel;
        const bSel = sel != null && b === sel;
        if (aSel !== bSel) return aSel ? -1 : 1;
        const ta = typeDrawTier(a.assetType);
        const tb = typeDrawTier(b.assetType);
        if (ta !== tb) return tb - ta;
        return (b.zIndex ?? 0) - (a.zIndex ?? 0);
    });
});

const selectedInfo = computed(() => {
    const b = selected.value;
    if (!b) return null;
    const l = b.rotated ? b.w : b.l;
    const w = b.rotated ? b.l : b.w;
    const oob = b.x < 0 || b.y < 0 || b.x + l > spaceW.value || b.y + w > spaceH.value;
    return {
        name: b.name,
        dims: `${b.l} × ${b.w} ft`,
        pos: `${b.x}', ${b.y}'`,
        oob,
        onLayout: b.includeInLayout,
        hasEventAsset: b.eventAssetId != null,
    };
});

const boatCount = computed(() => boats.value.length);

function rowToBoat(b, i) {
    const rot = Number(b.rotation ?? 0);
    const assetType = b.type != null ? Number(b.type) : null;
    const label = b.layout_label && String(b.layout_label).trim()
        ? b.layout_label
        : (b.display_name ?? b.name ?? `Asset ${i + 1}`);
    return {
        id: ++boatIdCounter.value,
        eventAssetId: b.event_asset_id ?? null,
        assetId: b.id ?? null,
        assetType,
        includeInLayout: !!b.include_in_layout,
        name: label,
        l: parseFloat(b.length_ft ?? b.length) || 20,
        w: parseFloat(b.width_ft ?? b.width) || 8,
        x: Number.isFinite(Number(b.x)) ? Number(b.x) : 2 + (i % 5) * 4,
        y: Number.isFinite(Number(b.y)) ? Number(b.y) : 2 + Math.floor(i / 5) * 4,
        rotated: rot % 180 === 90,
        zIndex: Number(b.z_index ?? 0),
    };
}

function applyLayoutSpaceFromProps() {
    const w = props.layoutSpace?.width_ft;
    const h = props.layoutSpace?.height_ft;
    if (w != null && Number(w) > 0) {
        spaceW.value = Math.max(10, Math.min(200, Number(w)));
        pendingW.value = spaceW.value;
    }
    if (h != null && Number(h) > 0) {
        spaceH.value = Math.max(10, Math.min(200, Number(h)));
        pendingH.value = spaceH.value;
    }
}

function buildSyncPayload() {
    return {
        width_ft: spaceW.value,
        height_ft: spaceH.value,
        items: boats.value
            .filter((b) => b.eventAssetId != null)
            .map((b) => ({
                event_asset_id: b.eventAssetId,
                include_in_layout: !!b.includeInLayout,
                x: b.x,
                y: b.y,
                rotation: b.rotated ? 90 : 0,
                z_index: b.zIndex ?? 0,
                name: b.name?.trim() ? b.name.trim() : null,
                length_ft: b.l,
                width_ft: b.w,
            })),
    };
}

function draw() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const cw = canvasW.value;
    const ch = canvasH.value;
    const S = SCALE.value;
    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    const bg = isDark ? '#1e2433' : '#e8ecf2';
    const floorBg = isDark ? '#252d3d' : '#f4f6f9';
    const gridColor = isDark ? 'rgba(148,163,184,0.08)' : 'rgba(71,85,105,0.08)';
    const labelColor = isDark ? '#64748b' : '#94a3b8';
    const borderCol = isDark ? '#334155' : '#94a3b8';

    ctx.clearRect(0, 0, cw, ch);
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, cw, ch);
    ctx.fillStyle = floorBg;
    ctx.strokeStyle = borderCol;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.rect(MARGIN, MARGIN, spaceW.value * S, spaceH.value * S);
    ctx.fill();
    ctx.stroke();

    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 0.5;
    for (let x = 0; x <= spaceW.value; x += 5) {
        ctx.beginPath();
        ctx.moveTo(MARGIN + x * S, MARGIN);
        ctx.lineTo(MARGIN + x * S, MARGIN + spaceH.value * S);
        ctx.stroke();
    }
    for (let y = 0; y <= spaceH.value; y += 5) {
        ctx.beginPath();
        ctx.moveTo(MARGIN, MARGIN + y * S);
        ctx.lineTo(MARGIN + spaceW.value * S, MARGIN + y * S);
        ctx.stroke();
    }

    ctx.fillStyle = labelColor;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    for (let x = 0; x <= spaceW.value; x += 10) {
        ctx.font = '9px sans-serif';
        ctx.fillText(x + "'", MARGIN + x * S, MARGIN - 8);
    }
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    for (let y = 0; y <= spaceH.value; y += 10) {
        ctx.font = '9px sans-serif';
        ctx.fillText(y + "'", MARGIN - 5, MARGIN + y * S);
    }

    for (const boat of boatsDrawOrder.value) {
        drawBoat(ctx, boat, boat === selected.value, S, false);
    }
}

function drawBoat(ctx, boat, isSel, S, dimmed) {
    const px = MARGIN + boat.x * S;
    const py = MARGIN + boat.y * S;
    const pw = (boat.rotated ? boat.w : boat.l) * S;
    const ph = (boat.rotated ? boat.l : boat.w) * S;

    const oob =
        boat.x < 0 ||
        boat.y < 0 ||
        (boat.rotated ? boat.x + boat.w > spaceW.value : boat.x + boat.l > spaceW.value) ||
        (boat.rotated ? boat.y + boat.l > spaceH.value : boat.y + boat.w > spaceH.value);

    ctx.globalAlpha = dimmed ? 0.38 : 0.9;
    ctx.fillStyle = oob ? '#E24B4A' : layoutFillColor(boat);
    ctx.strokeStyle = isSel ? '#fff' : 'rgba(0,0,0,0.22)';
    ctx.lineWidth = isSel ? 2.5 : 1;
    ctx.beginPath();
    ctx.roundRect(px, py, pw, ph, 5);
    ctx.fill();
    ctx.stroke();
    ctx.globalAlpha = 1;

    if (isSel) {
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 1.5;
        ctx.setLineDash([4, 3]);
        ctx.strokeRect(px - 3, py - 3, pw + 6, ph + 6);
        ctx.setLineDash([]);
    }

    const bowLen = Math.min(pw * 0.22, 20);
    ctx.fillStyle = 'rgba(0,0,0,0.18)';
    ctx.beginPath();
    ctx.moveTo(px + pw - bowLen, py);
    ctx.lineTo(px + pw, py + ph / 2);
    ctx.lineTo(px + pw - bowLen, py + ph);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = '#fff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const maxW = pw - bowLen - 6;
    let label = boat.name;
    ctx.font = `500 ${Math.min(12, Math.max(9, ph * 0.25))}px sans-serif`;
    while (ctx.measureText(label + '…').width > maxW && label.length > 1) {
        label = label.slice(0, -1);
    }
    if (label !== boat.name) label += '…';
    ctx.fillText(label, px + (pw - bowLen) / 2, py + ph / 2 - (ph > 22 ? 7 : 0));

    if (ph > 22) {
        const dim = `${boat.l}×${boat.w}ft`;
        ctx.font = `400 ${Math.min(10, Math.max(8, ph * 0.18))}px sans-serif`;
        ctx.fillStyle = 'rgba(255,255,255,0.72)';
        ctx.fillText(dim, px + (pw - bowLen) / 2, py + ph / 2 + 7);
    }
}

function hitTest(mx, my) {
    const S = SCALE.value;
    for (const b of boatsHitOrder.value) {
        const px = MARGIN + b.x * S;
        const py = MARGIN + b.y * S;
        const pw = (b.rotated ? b.w : b.l) * S;
        const ph = (b.rotated ? b.l : b.w) * S;
        if (mx >= px && mx <= px + pw && my >= py && my <= py + ph) return b;
    }
    return null;
}

function snap(val) { return Math.round(val); }

function getXY(e, canvas) {
    const r = canvas.getBoundingClientRect();
    const cl = e.touches ? e.touches[0] : e;
    return { mx: cl.clientX - r.left, my: cl.clientY - r.top };
}

function onPointerDown(e) {
    const { mx, my } = getXY(e, canvasRef.value);
    const hit = hitTest(mx, my);
    if (hit) {
        selected.value = hit;
        drag.active = true;
        drag.offX = mx - (MARGIN + hit.x * SCALE.value);
        drag.offY = my - (MARGIN + hit.y * SCALE.value);
        drag.lastValidX = hit.x;
        drag.lastValidY = hit.y;
        boats.value = [...boats.value.filter((b) => b !== hit), hit];
    } else {
        selected.value = null;
    }
    draw();
}

function onPointerMove(e) {
    if (!drag.active || !selected.value) return;
    const { mx, my } = getXY(e, canvasRef.value);
    const S = SCALE.value;
    const nx = snap((mx - drag.offX - MARGIN) / S);
    const ny = snap((my - drag.offY - MARGIN) / S);
    selected.value.x = nx;
    selected.value.y = ny;
    if (selected.value.includeInLayout && hasSameTypeFootprintOverlap(selected.value, boats.value)) {
        selected.value.x = drag.lastValidX;
        selected.value.y = drag.lastValidY;
    } else {
        drag.lastValidX = selected.value.x;
        drag.lastValidY = selected.value.y;
    }
    draw();
    emit('change', buildSyncPayload());
}

function onPointerUp() { drag.active = false; }

// Open modal to ADD a new boat
function openAddModal() {
    modalMode.value = 'add';
    editingBoat.value = null;
    form.name = '';
    form.length = 20;
    form.width = 8;
    showModal.value = true;
}

function openEditModal() {
    if (!selected.value) return;
    modalMode.value = 'edit';
    editingBoat.value = selected.value;
    form.name = selected.value.name;
    form.length = selected.value.l;
    form.width = selected.value.w;
    showModal.value = true;
}

function submitModal() {
    if (!form.name.trim()) return;
    if (modalMode.value === 'edit' && editingBoat.value) {
        editingBoat.value.name = form.name.trim();
        editingBoat.value.l = parseFloat(form.length) || editingBoat.value.l;
        editingBoat.value.w = parseFloat(form.width) || editingBoat.value.w;
        if (editingBoat.value.includeInLayout && hasSameTypeFootprintOverlap(editingBoat.value, boats.value)) {
            nudgeItemToFreeSpot(editingBoat.value);
        }
        showModal.value = false;
        draw();
        emit('change', buildSyncPayload());
    } else {
        const boat = {
            id: ++boatIdCounter.value,
            eventAssetId: null,
            assetId: null,
            assetType: null,
            includeInLayout: true,
            name: form.name.trim(),
            l: parseFloat(form.length) || 20,
            w: parseFloat(form.width) || 8,
            x: 2,
            y: 2,
            rotated: false,
            zIndex: 0,
        };
        boats.value = [...boats.value, boat];
        selected.value = boat;
        if (hasSameTypeFootprintOverlap(boat, boats.value)) {
            nudgeItemToFreeSpot(boat);
        }
        showModal.value = false;
        draw();
        emit('change', buildSyncPayload());
    }
}

function rotateSelected() {
    if (!selected.value) return;
    const was = selected.value.rotated;
    selected.value.rotated = !selected.value.rotated;
    if (
        selected.value.includeInLayout &&
        hasSameTypeFootprintOverlap(selected.value, boats.value)
    ) {
        selected.value.rotated = was;
        return;
    }
    draw();
    emit('change', buildSyncPayload());
}

function addToGrid(boat) {
    boat.includeInLayout = true;
    if (!Number.isFinite(boat.x)) boat.x = 2;
    if (!Number.isFinite(boat.y)) boat.y = 2;
    if (hasSameTypeFootprintOverlap(boat, boats.value)) {
        nudgeItemToFreeSpot(boat);
    }
    selected.value = boat;
    draw();
    emit('change', buildSyncPayload());
}

function removeFromGrid(boat) {
    boat.includeInLayout = false;
    if (selected.value === boat) selected.value = null;
    draw();
    emit('change', buildSyncPayload());
}

function removeSelectedFromGrid() {
    if (!selected.value) return;
    removeFromGrid(selected.value);
}

function deleteSelected() {
    if (!selected.value) return;
    boats.value = boats.value.filter((b) => b !== selected.value);
    selected.value = null;
    draw();
    emit('change', buildSyncPayload());
}

function applySpace() {
    spaceW.value = Math.max(10, Math.min(200, parseInt(pendingW.value) || 60));
    spaceH.value = Math.max(10, Math.min(200, parseInt(pendingH.value) || 40));
    pendingW.value = spaceW.value;
    pendingH.value = spaceH.value;
    draw();
    emit('change', buildSyncPayload());
}

function clearAll() {
    if (!confirm('Clear the floor plan? Assets will be moved off the layout.')) return;
    boats.value.forEach((b) => (b.includeInLayout = false));
    selected.value = null;
    draw();
    emit('change', buildSyncPayload());
}

function saveLayout() { emit('save', buildSyncPayload()); }

let ro;

onMounted(() => {
    applyLayoutSpaceFromProps();
    ro = new ResizeObserver((entries) => {
        containerW.value = entries[0].contentRect.width;
        requestAnimationFrame(draw);
    });
    if (containerRef.value) {
        ro.observe(containerRef.value);
        containerW.value = containerRef.value.clientWidth || 800;
    }
    if (props.initialLayoutItems?.length) {
        boats.value = props.initialLayoutItems.map((b, i) => rowToBoat(b, i));
    }
    draw();
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', draw);
});

watch(
    () => props.layoutSpace,
    () => { applyLayoutSpaceFromProps(); requestAnimationFrame(draw); },
    { deep: true },
);

watch(
    () => props.initialLayoutItems,
    (newItems) => {
        const list = newItems ?? [];
        const byEventAsset = new Map(list.map((b) => [b.event_asset_id, b]));
        boats.value = boats.value.filter((b) => {
            if (!b.eventAssetId) return true;
            return byEventAsset.has(b.eventAssetId);
        });
        for (const b of list) {
            if (!b.event_asset_id) continue;
            const existing = boats.value.find((x) => x.eventAssetId === b.event_asset_id);
            if (existing) {
                Object.assign(existing, {
                    includeInLayout: !!b.include_in_layout,
                    assetType: b.type != null ? Number(b.type) : existing.assetType,
                    name: b.layout_label && String(b.layout_label).trim() ? b.layout_label : existing.name,
                    l: parseFloat(b.length_ft ?? b.length) || existing.l,
                    w: parseFloat(b.width_ft ?? b.width) || existing.w,
                    x: Number.isFinite(Number(b.x)) ? Number(b.x) : existing.x,
                    y: Number.isFinite(Number(b.y)) ? Number(b.y) : existing.y,
                    rotated: Number(b.rotation ?? 0) % 180 === 90,
                    zIndex: Number(b.z_index ?? existing.zIndex ?? 0),
                });
            } else {
                boats.value.push(rowToBoat(b, boats.value.length));
            }
        }
        requestAnimationFrame(draw);
    },
    { deep: true },
);

onUnmounted(() => {
    ro?.disconnect();
    window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', draw);
});

watch([canvasW, canvasH], () => { requestAnimationFrame(draw); });
</script>

<template>
    <div class="flex flex-col rounded-lg border border-slate-300 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800 select-none">

        <!-- ── Toolbar row 1: actions ── -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
            <button
                v-if="attachAssetConfig"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md transition-colors"
                @click="emit('request-attach-asset')"
            >
                <span class="material-icons text-[14px]">add</span>
                Add asset
            </button>
            <button
                v-else
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md transition-colors"
                @click="openAddModal"
            >
                <span class="material-icons text-[14px]">add</span>
                Add custom shape
            </button>

            <div class="w-px h-5 bg-slate-200 dark:bg-slate-600 hidden sm:block"></div>

            <!-- Custom size (edit selected) -->
            <button
                type="button"
                @click="openEditModal"
                :disabled="!selected"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border border-slate-300 dark:border-slate-500 rounded-md hover:bg-slate-50 dark:hover:bg-slate-500 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">straighten</span>
                Custom size
            </button>

            <!-- Rotate -->
            <button
                type="button"
                @click="rotateSelected"
                :disabled="!selected"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border border-slate-300 dark:border-slate-500 rounded-md transition-colors hover:bg-slate-50 dark:hover:bg-slate-500 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">rotate_right</span>
                Rotate 90°
            </button>

            <!-- Remove from grid -->
            <button
                type="button"
                @click="removeSelectedFromGrid"
                :disabled="!selected || !selected.includeInLayout"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-amber-700 dark:text-amber-400 bg-white dark:bg-slate-600 border border-amber-200 dark:border-amber-700 rounded-md transition-colors hover:bg-amber-50 dark:hover:bg-amber-900/20 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">grid_off</span>
                Remove from grid
            </button>

            <!-- Delete -->
            <button
                type="button"
                @click="deleteSelected"
                :disabled="!selected"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-slate-600 border border-red-200 dark:border-red-700 rounded-md transition-colors hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">delete_outline</span>
                Delete
            </button>

            <!-- Clear all -->
            <button
                type="button"
                @click="clearAll"
                :disabled="!onLayoutBoats.length"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span class="material-icons text-[14px]">clear_all</span>
                Clear
            </button>

            <!-- Right: space + save -->
            <div class="ml-auto flex items-center gap-2">
                <span class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">Space (ft)</span>
                <input
                    v-model.number="pendingW"
                    type="number" min="10" max="200"
                    class="w-14 px-2 py-1.5 text-xs border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                    @keydown.enter="applySpace"
                />
                <span class="text-xs text-slate-400">×</span>
                <input
                    v-model.number="pendingH"
                    type="number" min="10" max="200"
                    class="w-14 px-2 py-1.5 text-xs border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                    @keydown.enter="applySpace"
                />
                <button
                    type="button"
                    @click="applySpace"
                    class="px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-600 border border-slate-300 dark:border-slate-500 rounded-md hover:bg-slate-50 dark:hover:bg-slate-500 transition-colors"
                >Apply</button>
                <button
                    type="button"
                    @click="saveLayout"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-slate-700 dark:bg-slate-600 hover:bg-slate-800 dark:hover:bg-slate-500 rounded-md transition-colors"
                >
                    <span class="material-icons text-[14px]">save</span>
                    Save
                </button>
            </div>
        </div>

        <!-- ── Selection status bar ── -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-1.5 text-xs border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 min-h-[34px]">
            <template v-if="selectedInfo">
                <span class="font-medium text-slate-900 dark:text-white">{{ selectedInfo.name }}</span>
                <span class="text-slate-400">{{ selectedInfo.dims }}</span>
                <span class="text-slate-400">@ {{ selectedInfo.pos }}</span>
                <span v-if="selectedInfo.oob" class="flex items-center gap-1 text-red-500 font-medium">
                    <span class="material-icons text-[13px]">warning</span>
                    Outside boundary
                </span>
            </template>
            <template v-else>
                <span class="text-slate-400">Click an asset to select, then drag to reposition</span>
            </template>
            <span class="ml-auto text-slate-500 dark:text-slate-400">
                {{ onLayoutBoats.length }} on layout · {{ boatCount }} total
            </span>
        </div>

        <!-- ── Main content: canvas + off-layout panel ── -->
        <div class="flex min-h-0">

            <!-- Canvas -->
            <div ref="containerRef" class="flex-1 min-w-0 bg-slate-200 dark:bg-slate-900">
                <canvas
                    ref="canvasRef"
                    :width="canvasW"
                    :height="canvasH"
                    class="block w-full touch-none cursor-default"
                    @mousedown="onPointerDown"
                    @mousemove="onPointerMove"
                    @mouseup="onPointerUp"
                    @mouseleave="onPointerUp"
                    @touchstart.prevent="onPointerDown"
                    @touchmove.prevent="onPointerMove"
                    @touchend="onPointerUp"
                />
            </div>

            <!-- Off-layout sidebar -->
            <div
                v-if="offLayoutBoats.length"
                class="w-52 shrink-0 border-l border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex flex-col overflow-hidden"
            >
                <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-700/40">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Not on grid
                    </p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                        {{ offLayoutBoats.length }} asset{{ offLayoutBoats.length !== 1 ? 's' : '' }} hidden from layout
                    </p>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                    <div
                        v-for="boat in offLayoutBoats"
                        :key="boat.id"
                        class="px-3 py-2.5 flex items-start gap-2.5 group hover:bg-white dark:hover:bg-slate-700/40 transition-colors"
                    >
                        <!-- Color swatch -->
                        <span
                            class="mt-0.5 w-2.5 h-2.5 rounded-full shrink-0"
                            :style="{ background: layoutFillColor(boat) }"
                        ></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-slate-800 dark:text-slate-100 truncate leading-tight">{{ boat.name }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ boat.l }} × {{ boat.w }} ft</p>
                            <button
                                type="button"
                                @click="addToGrid(boat)"
                                class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-primary-700 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700 rounded transition-colors hover:bg-primary-100 dark:hover:bg-primary-900/50"
                            >
                                <span class="material-icons text-[11px]">grid_on</span>
                                Add to grid
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Legend footer ── -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500 dark:text-slate-400">
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#3B82F6]"></span>
                Boat
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#F97316]"></span>
                Engine
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#22C55E]"></span>
                Trailer
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#64748B]"></span>
                Custom
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#E24B4A]"></span>
                Out of bounds
            </span>
            <span class="text-slate-400 hidden lg:block">Stacking: trailer under boat under engine. Same type cannot overlap.</span>
            <span class="ml-auto">{{ spaceW }}' × {{ spaceH }}' floor plan</span>
        </div>
    </div>

    <!-- ── Add / Edit modal ── -->
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="showModal"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click.self="showModal = false"
            >
                <div
                    class="relative z-[101] bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 p-6 w-80"
                    @click.stop
                >
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">
                        {{ modalMode === 'edit' ? 'Edit dimensions' : 'Add custom shape' }}
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Label</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Display area"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                                @keydown.enter="submitModal"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Length (ft)</label>
                                <input
                                    v-model.number="form.length"
                                    type="number" min="1"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Width (ft)</label>
                                <input
                                    v-model.number="form.width"
                                    type="number" min="1"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 justify-end mt-5">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors"
                        >Cancel</button>
                        <button
                            type="button"
                            @click="submitModal"
                            :disabled="!form.name.trim()"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            {{ modalMode === 'edit' ? 'Save changes' : 'Add to map' }}
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
