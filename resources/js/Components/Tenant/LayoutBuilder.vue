<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import axios from 'axios';
import {
    defaultRectPerimeter,
    footprintOutsidePolygon,
    isDefaultRectPerimeter,
    nearestEdgeInsertIfClose,
} from '@/Utils/layoutGeometry.js';
import {
    layoutCustomNameForSave,
    layoutDisplayName,
    layoutItemLabel,
    resolveUnitLabel,
} from '@/Utils/layoutLabels.js';

const props = defineProps({
    /** Assigned boats, engines, and trailers for this event (each row includes `type` from the asset). */
    initialLayoutItems: { type: Array, default: () => [] },
    layoutSpace: {
        type: Object,
        default: () => ({ width_ft: 60, height_ft: 40 }),
    },
    attachAssetConfig: { type: Object, default: null },
    /** Payload key for placement row id (event_asset_id | placement_id). */
    itemLinkField: { type: String, default: 'event_asset_id' },
    /** When true, parent pages debounce-save after @change. */
    autoSave: { type: Boolean, default: true },
    /** POST URL to enroll a unit on the layout (location floor plans). */
    unitStoreUrl: { type: String, default: null },
});

const emit = defineEmits(['save', 'change', 'request-attach-asset', 'update:autoSave']);

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
const FIXTURE_LAYOUT_COLOR = '#8B5CF6';

const SHAPE_PRESETS = [
    { label: 'Desk', shape: 'rectangle', length: 5, width: 3 },
    { label: 'Display table', shape: 'rectangle', length: 8, width: 4 },
    { label: 'Counter', shape: 'rectangle', length: 10, width: 3 },
    { label: 'Square booth', shape: 'square', length: 10 },
    { label: 'Round table', shape: 'circle', length: 6 },
];

function layoutFillColor(item) {
    if (item.fixtureId) return FIXTURE_LAYOUT_COLOR;
    if (item.assetType == null) return CUSTOM_LAYOUT_COLOR;
    return LAYOUT_COLOR_BY_TYPE[item.assetType] ?? CUSTOM_LAYOUT_COLOR;
}

function isFixture(item) {
    return !!item.fixtureId;
}

function itemShape(item) {
    return item.shape || 'rectangle';
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
    if (item.fixtureId) return `fixture:${item.fixtureId}`;
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
    if (isFixture(moving)) return false;
    const m = itemFootprint(moving);
    for (const o of allItems) {
        if (o === moving || !o.includeInLayout) continue;
        if (isFixture(o)) continue;
        if (stackTypeKey(o) !== stackTypeKey(moving)) continue;
        if (footprintsOverlap(m, itemFootprint(o))) return true;
    }
    return false;
}

function itemIsOutOfBounds(item) {
    const fp = itemFootprint(item);
    if (perimeterPoints.value.length >= 3) {
        return footprintOutsidePolygon(fp, perimeterPoints.value);
    }
    return (
        item.x < 0 ||
        item.y < 0 ||
        (item.rotated ? item.x + item.w > spaceW.value : item.x + item.l > spaceW.value) ||
        (item.rotated ? item.y + item.l > spaceH.value : item.y + item.w > spaceH.value)
    );
}

function itemNotAtLocation(item) {
    if (isFixture(item) || item.linkId == null) {
        return false;
    }

    return item.isAtLocation === false;
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
const stageRef = ref(null);
const containerRef = ref(null);
const containerW = ref(800);
const containerH = ref(400);
const isFullscreen = ref(false);
const fitToScreen = ref(false);

const spaceW = ref(60);
const spaceH = ref(40);
const pendingW = ref(60);
const pendingH = ref(40);
const boats = ref([]);
const selected = ref(null);
const boatIdCounter = ref(0);
const fixtureIdCounter = ref(0);

const perimeterPoints = ref([]);
const perimeterMode = ref(false);
const selectedVertex = ref(null);

const showModal = ref(false);
const modalMode = ref('add'); // 'add' | 'edit'
const form = reactive({ name: '', length: 20, width: 8, shape: 'rectangle' });
const editingBoat = ref(null);

const showDimensionsModal = ref(false);
const dimensionsForm = reactive({ length: 20, width: 8 });
const editingDimensionsBoat = ref(null);
const dimensionsSaving = ref(false);
const dimensionsError = ref(null);

const drag = { active: false, offX: 0, offY: 0, lastValidX: 0, lastValidY: 0 };
const vertexDrag = { active: false, index: -1, offX: 0, offY: 0 };

const SCALE = computed(() => {
    const fitPadding = isFullscreen.value && fitToScreen.value ? 8 : 0;
    const availW = Math.max(0, containerW.value - MARGIN * 2 - fitPadding);
    const availH = Math.max(0, containerH.value - MARGIN * 2 - fitPadding);

    if (availW <= 0) {
        return 4;
    }

    if (fitToScreen.value && isFullscreen.value && availH > 0) {
        const byW = availW / spaceW.value;
        const byH = availH / spaceH.value;

        return Math.max(4, Math.floor(Math.min(byW, byH)));
    }

    return Math.max(4, Math.floor(availW / spaceW.value));
});

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
    const oob = itemIsOutOfBounds(b);
    const notAtLocation = itemNotAtLocation(b);
    const shapeLabel = isFixture(b) ? itemShape(b) : null;
    return {
        name: b.name,
        dims: itemShape(b) === 'circle' ? `Ø ${b.l} ft` : `${b.l} × ${b.w} ft`,
        pos: `${b.x}', ${b.y}'`,
        oob,
        notAtLocation,
        currentLocationName: b.currentLocationName ?? null,
        onLayout: b.includeInLayout,
        hasEventAsset: b.eventAssetId != null,
        isFixture: isFixture(b),
        shapeLabel,
    };
});

const boatCount = computed(() => boats.value.length);

function formatDimensionFt(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }

    return parseFloat(n.toFixed(2)).toString();
}

function rowToBoat(b, i) {
    const rot = Number(b.rotation ?? 0);
    const assetType = b.type != null ? Number(b.type) : null;
    const unitLabel = resolveUnitLabel(b);
    const displayName = layoutDisplayName(b, i);
    const label = layoutItemLabel(b, i);
    const linkId = b[props.itemLinkField] ?? b.event_asset_id ?? b.placement_id ?? null;
    return {
        id: ++boatIdCounter.value,
        fixtureId: null,
        shape: null,
        linkId,
        eventAssetId: linkId,
        assetId: b.id ?? b.asset_id ?? null,
        assetUnitId: b.asset_unit_id ?? b.asset_unit?.id ?? null,
        poolOnly: !!(b.pool_only && linkId == null),
        isAtLocation: b.is_at_location ?? true,
        currentLocationName: b.current_location_name ?? null,
        assetType,
        includeInLayout: !!b.include_in_layout,
        name: label,
        displayName,
        unitLabel,
        assetDisplayName: b.display_name ?? null,
        l: parseFloat(b.length_ft ?? b.length) || 20,
        w: parseFloat(b.width_ft ?? b.width) || 8,
        x: Number.isFinite(Number(b.x)) ? Number(b.x) : 2 + (i % 5) * 4,
        y: Number.isFinite(Number(b.y)) ? Number(b.y) : 2 + Math.floor(i / 5) * 4,
        rotated: rot % 180 === 90,
        zIndex: Number(b.z_index ?? 0),
    };
}

function fixtureToBoat(f, i) {
    const rot = Number(f.rotation ?? 0);
    const shape = f.shape || 'rectangle';
    const length = parseFloat(f.length_ft) || 4;
    const width = parseFloat(f.width_ft) || length;
    return {
        id: ++boatIdCounter.value,
        fixtureId: f.id || `fixture_${++fixtureIdCounter.value}`,
        shape,
        eventAssetId: null,
        assetId: null,
        assetType: null,
        includeInLayout: f.include_in_layout !== false,
        name: (f.label || f.name || `Shape ${i + 1}`).trim(),
        l: length,
        w: shape === 'circle' || shape === 'square' ? length : width,
        x: Number.isFinite(Number(f.x)) ? Number(f.x) : 2,
        y: Number.isFinite(Number(f.y)) ? Number(f.y) : 2,
        rotated: rot % 180 === 90,
        zIndex: Number(f.z_index ?? 0),
    };
}

function applyPerimeterFromProps() {
    const raw = props.layoutSpace?.perimeter;
    if (Array.isArray(raw) && raw.length >= 3) {
        perimeterPoints.value = raw.map((pt) => ({
            x: Math.round(Number(pt.x) || 0),
            y: Math.round(Number(pt.y) || 0),
        }));
        return;
    }
    perimeterPoints.value = defaultRectPerimeter(spaceW.value, spaceH.value);
}

function syncFixturesFromProps() {
    const fixtures = Array.isArray(props.layoutSpace?.fixtures) ? props.layoutSpace.fixtures : [];
    const savedIds = new Set(fixtures.map((f) => f.id).filter(Boolean));
    boats.value = boats.value.filter((b) => !b.fixtureId || savedIds.has(b.fixtureId));
    const byId = new Map(boats.value.filter((b) => b.fixtureId).map((b) => [b.fixtureId, b]));
    for (const f of fixtures) {
        const existing = byId.get(f.id);
        if (existing) {
            const shape = f.shape || 'rectangle';
            const length = parseFloat(f.length_ft) || existing.l;
            Object.assign(existing, {
                shape,
                includeInLayout: f.include_in_layout !== false,
                name: (f.label || f.name || existing.name).trim(),
                l: length,
                w: shape === 'circle' || shape === 'square' ? length : parseFloat(f.width_ft) || existing.w,
                x: Number.isFinite(Number(f.x)) ? Number(f.x) : existing.x,
                y: Number.isFinite(Number(f.y)) ? Number(f.y) : existing.y,
                rotated: Number(f.rotation ?? 0) % 180 === 90,
                zIndex: Number(f.z_index ?? existing.zIndex ?? 0),
            });
        } else {
            boats.value.push(fixtureToBoat(f, boats.value.length));
        }
    }
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
        perimeter: perimeterPoints.value.map((pt) => ({ x: pt.x, y: pt.y })),
        fixtures: boats.value
            .filter((b) => b.fixtureId)
            .map((b) => ({
                id: b.fixtureId,
                shape: itemShape(b),
                label: b.name?.trim() || 'Shape',
                include_in_layout: !!b.includeInLayout,
                x: b.x,
                y: b.y,
                rotation: b.rotated ? 90 : 0,
                z_index: b.zIndex ?? 0,
                length_ft: b.l,
                width_ft: itemShape(b) === 'circle' || itemShape(b) === 'square' ? b.l : b.w,
            })),
        items: boats.value
            .filter((b) => b.linkId != null)
            .map((b) => ({
                [props.itemLinkField]: b.linkId,
                include_in_layout: !!b.includeInLayout,
                x: b.x,
                y: b.y,
                rotation: b.rotated ? 90 : 0,
                z_index: b.zIndex ?? 0,
                name: layoutCustomNameForSave(b),
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

    const poly = perimeterPoints.value;
    ctx.fillStyle = floorBg;
    ctx.strokeStyle = borderCol;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    if (poly.length >= 3) {
        poly.forEach((pt, i) => {
            const px = MARGIN + pt.x * S;
            const py = MARGIN + pt.y * S;
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        });
        ctx.closePath();
    } else {
        ctx.rect(MARGIN, MARGIN, spaceW.value * S, spaceH.value * S);
    }
    ctx.fill();
    ctx.stroke();

    if (poly.length >= 3) {
        ctx.save();
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = isDark ? '#64748b' : '#475569';
        ctx.lineWidth = 2;
        ctx.beginPath();
        poly.forEach((pt, i) => {
            const px = MARGIN + pt.x * S;
            const py = MARGIN + pt.y * S;
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        });
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
    }

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

    if (perimeterMode.value && poly.length >= 3) {
        for (let i = 0; i < poly.length; i++) {
            const px = MARGIN + poly[i].x * S;
            const py = MARGIN + poly[i].y * S;
            const isSel = selectedVertex.value === i;
            ctx.fillStyle = isSel ? '#F59E0B' : '#0EA5E9';
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(px, py, isSel ? 8 : 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        }
    }
}

function drawBoat(ctx, boat, isSel, S, dimmed) {
    const px = MARGIN + boat.x * S;
    const py = MARGIN + boat.y * S;
    const pw = (boat.rotated ? boat.w : boat.l) * S;
    const ph = (boat.rotated ? boat.l : boat.w) * S;

    const oob = itemIsOutOfBounds(boat);
    const notAtLocation = itemNotAtLocation(boat);
    const warnFill = oob || notAtLocation;
    const shape = itemShape(boat);
    const isCircle = shape === 'circle';
    const isFixtureItem = isFixture(boat);

    ctx.globalAlpha = dimmed ? 0.38 : 0.9;
    ctx.fillStyle = warnFill ? '#E24B4A' : layoutFillColor(boat);
    ctx.strokeStyle = isSel ? '#fff' : 'rgba(0,0,0,0.22)';
    ctx.lineWidth = isSel ? 2.5 : 1;
    ctx.beginPath();
    if (isCircle) {
        ctx.ellipse(px + pw / 2, py + ph / 2, pw / 2, ph / 2, 0, 0, Math.PI * 2);
    } else {
        ctx.roundRect(px, py, pw, ph, isFixtureItem ? 3 : 5);
    }
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

    const bowLen = isFixtureItem || isCircle ? 0 : Math.min(pw * 0.22, 20);
    if (bowLen > 0) {
        ctx.fillStyle = 'rgba(0,0,0,0.18)';
        ctx.beginPath();
        ctx.moveTo(px + pw - bowLen, py);
        ctx.lineTo(px + pw, py + ph / 2);
        ctx.lineTo(px + pw - bowLen, py + ph);
        ctx.closePath();
        ctx.fill();
    }

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
        ctx.font = `400 ${Math.min(10, Math.max(8, ph * 0.18))}px sans-serif`;
        if (notAtLocation) {
            ctx.fillStyle = 'rgba(255,255,255,0.92)';
            ctx.fillText('Not at location', px + (pw - bowLen) / 2, py + ph / 2 + 7);
        } else {
            const dim = isCircle ? `Ø${boat.l}ft` : `${boat.l}×${boat.w}ft`;
            ctx.fillStyle = 'rgba(255,255,255,0.72)';
            ctx.fillText(dim, px + (pw - bowLen) / 2, py + ph / 2 + 7);
        }
    }
}

const VERTEX_HIT_RADIUS_PX = 16;

function hitTestVertex(mx, my) {
    const S = SCALE.value;
    const poly = perimeterPoints.value;
    const r2 = VERTEX_HIT_RADIUS_PX * VERTEX_HIT_RADIUS_PX;
    for (let i = poly.length - 1; i >= 0; i--) {
        const px = MARGIN + poly[i].x * S;
        const py = MARGIN + poly[i].y * S;
        const dx = mx - px;
        const dy = my - py;
        if (dx * dx + dy * dy <= r2) {
            return i;
        }
    }
    return null;
}

function hitTest(mx, my) {
    const S = SCALE.value;
    for (const b of boatsHitOrder.value) {
        const px = MARGIN + b.x * S;
        const py = MARGIN + b.y * S;
        const pw = (b.rotated ? b.w : b.l) * S;
        const ph = (b.rotated ? b.l : b.w) * S;
        if (itemShape(b) === 'circle') {
            const cx = px + pw / 2;
            const cy = py + ph / 2;
            const rx = pw / 2;
            const ry = ph / 2;
            const nx = (mx - cx) / rx;
            const ny = (my - cy) / ry;
            if (nx * nx + ny * ny <= 1) return b;
        } else if (mx >= px && mx <= px + pw && my >= py && my <= py + ph) {
            return b;
        }
    }
    return null;
}

function canvasToFeet(mx, my) {
    const S = SCALE.value;
    return {
        x: snap((mx - MARGIN) / S),
        y: snap((my - MARGIN) / S),
    };
}

function snap(val) { return Math.round(val); }

function getXY(e, canvas) {
    const r = canvas.getBoundingClientRect();
    const cl = e.touches ? e.touches[0] : e;
    if (!canvas || r.width <= 0 || r.height <= 0) {
        return { mx: 0, my: 0 };
    }
    const scaleX = canvas.width / r.width;
    const scaleY = canvas.height / r.height;

    return {
        mx: (cl.clientX - r.left) * scaleX,
        my: (cl.clientY - r.top) * scaleY,
    };
}

function onPointerDown(e) {
    const { mx, my } = getXY(e, canvasRef.value);

    if (perimeterMode.value) {
        e.preventDefault?.();
        const vi = hitTestVertex(mx, my);
        if (vi !== null) {
            selectedVertex.value = vi;
            vertexDrag.active = true;
            vertexDrag.index = vi;
            vertexDrag.offX = mx - (MARGIN + perimeterPoints.value[vi].x * SCALE.value);
            vertexDrag.offY = my - (MARGIN + perimeterPoints.value[vi].y * SCALE.value);
            selected.value = null;
            draw();
            return;
        }
        const { x, y } = canvasToFeet(mx, my);
        const insert = nearestEdgeInsertIfClose(x, y, perimeterPoints.value, 4);
        if (insert && perimeterPoints.value.length < 32) {
            perimeterPoints.value.splice(insert.index, 0, insert.point);
            selectedVertex.value = insert.index;
            vertexDrag.active = true;
            vertexDrag.index = insert.index;
            vertexDrag.offX = mx - (MARGIN + insert.point.x * SCALE.value);
            vertexDrag.offY = my - (MARGIN + insert.point.y * SCALE.value);
            emit('change', buildSyncPayload());
        } else {
            selectedVertex.value = null;
        }
        draw();
        return;
    }

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
    if (vertexDrag.active && vertexDrag.index >= 0) {
        const { mx, my } = getXY(e, canvasRef.value);
        const S = SCALE.value;
        const nx = snap((mx - vertexDrag.offX - MARGIN) / S);
        const ny = snap((my - vertexDrag.offY - MARGIN) / S);
        const clampedX = Math.max(0, Math.min(spaceW.value, nx));
        const clampedY = Math.max(0, Math.min(spaceH.value, ny));
        perimeterPoints.value[vertexDrag.index] = { x: clampedX, y: clampedY };
        draw();
        emit('change', buildSyncPayload());
        return;
    }

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

function onPointerUp() {
    const wasDragging = drag.active || vertexDrag.active;
    drag.active = false;
    vertexDrag.active = false;
    if (wasDragging) {
        emit('change', buildSyncPayload());
    }
}

function applyShapePreset(preset) {
    form.name = preset.label;
    form.shape = preset.shape;
    form.length = preset.length;
    form.width = preset.shape === 'rectangle' ? preset.width : preset.length;
}

function syncFormDimensionsForShape() {
    if (form.shape === 'square' || form.shape === 'circle') {
        form.width = form.length;
    }
}

// Open modal to ADD a new shape
function openAddModal() {
    modalMode.value = 'add';
    editingBoat.value = null;
    form.name = '';
    form.length = 20;
    form.width = 8;
    form.shape = 'rectangle';
    showModal.value = true;
}

function openEditModal() {
    if (!selected.value) return;
    modalMode.value = 'edit';
    editingBoat.value = selected.value;
    form.name = selected.value.displayName ?? selected.value.name;
    form.length = selected.value.l;
    form.width = selected.value.w;
    form.shape = isFixture(selected.value) ? itemShape(selected.value) : 'rectangle';
    showModal.value = true;
}

function openDimensionsModal(boat) {
    if (!boat || isFixture(boat) || boat.linkId == null) {
        return;
    }

    editingDimensionsBoat.value = boat;
    dimensionsForm.length = boat.l;
    dimensionsForm.width = boat.w;
    dimensionsError.value = null;
    showDimensionsModal.value = true;
}

function closeDimensionsModal() {
    showDimensionsModal.value = false;
    editingDimensionsBoat.value = null;
    dimensionsError.value = null;
}

function parseDimensionFeet(value, fallback) {
    const parsed = parseFloat(value);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function applyDimensionsToBoat(boat, length, width) {
    boat.l = length;
    boat.w = width;
    if (boat.includeInLayout && hasSameTypeFootprintOverlap(boat, boats.value)) {
        nudgeItemToFreeSpot(boat);
    }
}

function saveDimensionsFloorPlanOnly() {
    const boat = editingDimensionsBoat.value;
    if (!boat) return;

    const length = parseDimensionFeet(dimensionsForm.length, 0);
    const width = parseDimensionFeet(dimensionsForm.width, 0);
    if (!length || !width) {
        dimensionsError.value = 'Enter valid length and width in feet.';
        return;
    }

    applyDimensionsToBoat(boat, length, width);
    closeDimensionsModal();
    draw();
    emit('change', buildSyncPayload());
}

async function saveDimensionsFloorPlanAndUnit() {
    const boat = editingDimensionsBoat.value;
    if (!boat) return;

    const length = parseDimensionFeet(dimensionsForm.length, 0);
    const width = parseDimensionFeet(dimensionsForm.width, 0);
    if (!length || !width) {
        dimensionsError.value = 'Enter valid length and width in feet.';
        return;
    }

    if (!boat.assetUnitId) {
        dimensionsError.value = 'This placement is not linked to an inventory unit.';
        return;
    }

    dimensionsSaving.value = true;
    dimensionsError.value = null;

    try {
        await axios.patch(
            route('assetunits.layout-footprint', boat.assetUnitId),
            { length_ft: length, width_ft: width },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        applyDimensionsToBoat(boat, length, width);
        closeDimensionsModal();
        draw();
        emit('change', buildSyncPayload());
    } catch (e) {
        dimensionsError.value =
            e.response?.data?.message ??
            (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ??
            'Could not update unit dimensions.';
    } finally {
        dimensionsSaving.value = false;
    }
}

function onCanvasDblClick(e) {
    if (perimeterMode.value) return;

    const { mx, my } = getXY(e, canvasRef.value);
    const hit = hitTest(mx, my);
    if (!hit) return;

    selected.value = hit;

    if (isFixture(hit)) {
        openEditModal();
        return;
    }

    if (hit.linkId != null) {
        openDimensionsModal(hit);
    }
}

function submitModal() {
    if (!form.name.trim()) return;
    syncFormDimensionsForShape();

    if (modalMode.value === 'edit' && editingBoat.value) {
        editingBoat.value.name = form.name.trim();
        editingBoat.value.l = parseFloat(form.length) || editingBoat.value.l;
        editingBoat.value.w =
            form.shape === 'circle' || form.shape === 'square'
                ? editingBoat.value.l
                : parseFloat(form.width) || editingBoat.value.w;
        if (isFixture(editingBoat.value)) {
            editingBoat.value.shape = form.shape;
        }
        if (editingBoat.value.includeInLayout && hasSameTypeFootprintOverlap(editingBoat.value, boats.value)) {
            nudgeItemToFreeSpot(editingBoat.value);
        }
        showModal.value = false;
        draw();
        emit('change', buildSyncPayload());
    } else {
        const length = parseFloat(form.length) || 4;
        const width =
            form.shape === 'circle' || form.shape === 'square' ? length : parseFloat(form.width) || 4;
        const boat = {
            id: ++boatIdCounter.value,
            fixtureId: `fixture_${++fixtureIdCounter.value}`,
            shape: form.shape,
            eventAssetId: null,
            assetId: null,
            assetType: null,
            includeInLayout: true,
            name: form.name.trim(),
            l: length,
            w: width,
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
    if (boat.poolOnly && boat.assetUnitId && props.unitStoreUrl) {
        enrollPoolUnitAndAddToGrid(boat);
        return;
    }

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

const poolUnitEnrolling = ref(false);

async function enrollPoolUnitAndAddToGrid(boat) {
    if (!props.unitStoreUrl || !boat.assetUnitId || poolUnitEnrolling.value) {
        return;
    }

    poolUnitEnrolling.value = true;

    try {
        const response = await axios.post(
            props.unitStoreUrl,
            { asset_unit_id: boat.assetUnitId, transfer: false },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        const items = response.data.layoutUnits ?? response.data.placement ?? [];
        const match = items.find(
            (item) => Number(item.asset_unit_id) === Number(boat.assetUnitId),
        );

        boats.value = boats.value.filter((b) => b !== boat);

        if (match) {
            const enrolled = rowToBoat(match, boats.value.length);
            enrolled.includeInLayout = true;
            if (!Number.isFinite(enrolled.x)) enrolled.x = 2;
            if (!Number.isFinite(enrolled.y)) enrolled.y = 2;
            if (hasSameTypeFootprintOverlap(enrolled, boats.value)) {
                nudgeItemToFreeSpot(enrolled);
            }
            boats.value.push(enrolled);
            selected.value = enrolled;
        }

        draw();
        emit('change', buildSyncPayload());
    } catch (e) {
        const msg =
            e.response?.data?.message ??
            (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ??
            'Could not add unit to floor plan.';
        window.alert(msg);
    } finally {
        poolUnitEnrolling.value = false;
    }
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
    const oldW = spaceW.value;
    const oldH = spaceH.value;
    spaceW.value = Math.max(10, Math.min(200, parseInt(pendingW.value) || 60));
    spaceH.value = Math.max(10, Math.min(200, parseInt(pendingH.value) || 40));
    pendingW.value = spaceW.value;
    pendingH.value = spaceH.value;
    if (isDefaultRectPerimeter(perimeterPoints.value, oldW, oldH)) {
        perimeterPoints.value = defaultRectPerimeter(spaceW.value, spaceH.value);
    }
    draw();
    emit('change', buildSyncPayload());
}

function togglePerimeterMode() {
    perimeterMode.value = !perimeterMode.value;
    selectedVertex.value = null;
    selected.value = null;
    draw();
}

function resetPerimeterToRect() {
    perimeterPoints.value = defaultRectPerimeter(spaceW.value, spaceH.value);
    selectedVertex.value = null;
    draw();
    emit('change', buildSyncPayload());
}

function removeSelectedVertex() {
    if (selectedVertex.value === null || perimeterPoints.value.length <= 3) {
        return;
    }
    perimeterPoints.value.splice(selectedVertex.value, 1);
    selectedVertex.value = null;
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

function measureContainer() {
    const heightEl = stageRef.value ?? containerRef.value;
    const widthEl = containerRef.value ?? stageRef.value;

    if (widthEl) {
        containerW.value = widthEl.clientWidth || 800;
    }

    if (heightEl) {
        containerH.value = heightEl.clientHeight || 400;
    }
}

function scheduleMeasureAndDraw() {
    requestAnimationFrame(() => {
        measureContainer();
        draw();
    });
}

function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value;

    if (isFullscreen.value) {
        fitToScreen.value = true;
        document.body.style.overflow = 'hidden';
    } else {
        fitToScreen.value = false;
        document.body.style.overflow = '';
    }

    nextTick(() => {
        scheduleMeasureAndDraw();
        requestAnimationFrame(scheduleMeasureAndDraw);
    });
}

function toggleFitToScreen() {
    if (!isFullscreen.value) {
        return;
    }

    fitToScreen.value = !fitToScreen.value;

    nextTick(() => {
        scheduleMeasureAndDraw();
        requestAnimationFrame(scheduleMeasureAndDraw);
    });
}

function onWindowResize() {
    if (!isFullscreen.value) {
        return;
    }

    scheduleMeasureAndDraw();
}

function onFullscreenKeydown(event) {
    if (event.key === 'Escape' && isFullscreen.value) {
        toggleFullscreen();
    }
}

let ro;

onMounted(() => {
    applyLayoutSpaceFromProps();
    applyPerimeterFromProps();
    ro = new ResizeObserver(() => {
        scheduleMeasureAndDraw();
    });
    nextTick(() => {
        if (stageRef.value) {
            ro.observe(stageRef.value);
        }
        if (containerRef.value) {
            ro.observe(containerRef.value);
        }
        scheduleMeasureAndDraw();
    });
    if (props.initialLayoutItems?.length) {
        boats.value = props.initialLayoutItems.map((b, i) => rowToBoat(b, i));
    }
    syncFixturesFromProps();
    draw();
    window.addEventListener('keydown', onFullscreenKeydown);
    window.addEventListener('resize', onWindowResize);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', draw);
});

watch(
    () => props.layoutSpace,
    () => {
        applyLayoutSpaceFromProps();
        applyPerimeterFromProps();
        syncFixturesFromProps();
        requestAnimationFrame(draw);
    },
    { deep: true },
);

watch(
    () => props.initialLayoutItems,
    (newItems) => {
        const list = newItems ?? [];
        const byLink = new Map(list.map((b) => [b[props.itemLinkField] ?? b.event_asset_id ?? b.placement_id, b]));
        const poolCandidates = list.filter((b) => b.pool_only && !(b[props.itemLinkField] ?? b.event_asset_id ?? b.placement_id));
        boats.value = boats.value.filter((b) => {
            if (b.fixtureId) return true;
            if (b.poolOnly && !b.linkId) {
                return poolCandidates.some(
                    (candidate) => Number(candidate.asset_unit_id) === Number(b.assetUnitId),
                );
            }
            if (!b.linkId) return false;
            return byLink.has(b.linkId);
        });
        for (const b of list) {
            const linkId = b[props.itemLinkField] ?? b.event_asset_id ?? b.placement_id;
            if (!linkId) {
                if (b.pool_only && !boats.value.some(
                    (existing) => existing.poolOnly && Number(existing.assetUnitId) === Number(b.asset_unit_id),
                )) {
                    boats.value.push(rowToBoat(b, boats.value.length));
                }
                continue;
            }
            const existing = boats.value.find((x) => x.linkId === linkId);
            if (existing) {
                Object.assign(existing, {
                    includeInLayout: !!b.include_in_layout,
                    assetType: b.type != null ? Number(b.type) : existing.assetType,
                    assetUnitId: b.asset_unit_id ?? b.asset_unit?.id ?? existing.assetUnitId ?? null,
                    poolOnly: !!(b.pool_only && linkId == null),
                    isAtLocation: b.is_at_location ?? true,
                    currentLocationName: b.current_location_name ?? existing.currentLocationName ?? null,
                    assetDisplayName: b.display_name ?? existing.assetDisplayName ?? null,
                    displayName: layoutDisplayName(b, boats.value.indexOf(existing)),
                    unitLabel: resolveUnitLabel(b),
                    name: layoutItemLabel(b, boats.value.indexOf(existing)),
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
    window.removeEventListener('keydown', onFullscreenKeydown);
    window.removeEventListener('resize', onWindowResize);
    document.body.style.overflow = '';
    window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', draw);
});

watch(isFullscreen, () => {
    nextTick(scheduleMeasureAndDraw);
});

watch(fitToScreen, () => {
    if (isFullscreen.value) {
        nextTick(scheduleMeasureAndDraw);
    }
});

watch([canvasW, canvasH], () => { requestAnimationFrame(draw); });
</script>

<template>
    <Teleport to="body" :disabled="!isFullscreen">
        <div
            class="flex flex-col overflow-hidden bg-white dark:bg-slate-800 select-none"
            :class="isFullscreen
                ? 'fixed inset-0 z-[200] h-dvh w-screen shadow-2xl'
                : 'rounded-lg border border-slate-300 dark:border-slate-700'"
        >

        <!-- ── Toolbar row 1: actions ── -->
        <div class="shrink-0 flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
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
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-md transition-colors"
                @click="openAddModal"
            >
                <span class="material-icons text-[14px]">category</span>
                Add shape
            </button>

            <div class="w-px h-5 bg-slate-200 dark:bg-slate-600 hidden sm:block"></div>

            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                :class="perimeterMode
                    ? 'text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-900/30 border-sky-300 dark:border-sky-700'
                    : 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border-slate-300 dark:border-slate-500 hover:bg-slate-50 dark:hover:bg-slate-500'"
                @click="togglePerimeterMode"
            >
                <span class="material-icons text-[14px]">polyline</span>
                {{ perimeterMode ? 'Done editing perimeter' : 'Edit perimeter' }}
            </button>
            <button
                v-if="perimeterMode"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border border-slate-300 dark:border-slate-500 rounded-md hover:bg-slate-50 dark:hover:bg-slate-500 transition-colors"
                @click="resetPerimeterToRect"
            >
                Reset to rectangle
            </button>
            <button
                v-if="perimeterMode && selectedVertex !== null && perimeterPoints.length > 3"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-slate-600 border border-red-200 dark:border-red-700 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                @click="removeSelectedVertex"
            >
                Remove corner
            </button>

            <div class="w-px h-5 bg-slate-200 dark:bg-slate-600 hidden sm:block"></div>

            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                :class="isFullscreen
                    ? 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 border-amber-300 dark:border-amber-700'
                    : 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border-slate-300 dark:border-slate-500 hover:bg-slate-50 dark:hover:bg-slate-500'"
                @click="toggleFullscreen"
            >
                <span class="material-icons text-[14px]">{{ isFullscreen ? 'close_fullscreen' : 'open_in_full' }}</span>
                {{ isFullscreen ? 'Exit full screen' : 'Full screen' }}
            </button>

            <button
                v-if="isFullscreen"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                :class="fitToScreen
                    ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-700'
                    : 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border-slate-300 dark:border-slate-500 hover:bg-slate-50 dark:hover:bg-slate-500'"
                @click="toggleFitToScreen"
            >
                <span class="material-icons text-[14px]">fit_screen</span>
                {{ fitToScreen ? 'Fit to screen on' : 'Fit to screen' }}
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
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                    :class="autoSave
                        ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-700'
                        : 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-600 border-slate-300 dark:border-slate-500 hover:bg-slate-50 dark:hover:bg-slate-500'"
                    :title="autoSave ? 'Changes save automatically after you stop editing' : 'Turn on to save changes automatically'"
                    @click="emit('update:autoSave', !autoSave)"
                >
                    <span class="material-icons text-[14px]">{{ autoSave ? 'cloud_done' : 'cloud_off' }}</span>
                    Auto-save {{ autoSave ? 'on' : 'off' }}
                </button>
                <button
                    type="button"
                    @click="saveLayout"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-slate-700 dark:bg-slate-600 hover:bg-slate-800 dark:hover:bg-slate-500 rounded-md transition-colors"
                >
                    <span class="material-icons text-[14px]">save</span>
                    Save now
                </button>
            </div>
        </div>

        <!-- ── Selection status bar ── -->
        <div class="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-1.5 text-xs border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 min-h-[34px]">
            <template v-if="selectedInfo">
                <span class="font-medium text-slate-900 dark:text-white">{{ selectedInfo.name }}</span>
                <span class="text-slate-400">{{ selectedInfo.dims }}</span>
                <span class="text-slate-400">@ {{ selectedInfo.pos }}</span>
                <span v-if="selectedInfo.notAtLocation" class="flex items-center gap-1 text-red-500 font-medium">
                    <span class="material-icons text-[13px]">location_off</span>
                    No longer at location
                    <span v-if="selectedInfo.currentLocationName" class="font-normal text-red-400">
                        · {{ selectedInfo.currentLocationName }}
                    </span>
                </span>
                <span v-if="selectedInfo.oob" class="flex items-center gap-1 text-red-500 font-medium">
                    <span class="material-icons text-[13px]">warning</span>
                    Outside boundary
                </span>
            </template>
            <template v-else-if="perimeterMode">
                <span class="text-sky-600 dark:text-sky-400">Drag blue corners to reshape the lot. Click within ~4 ft of an edge to add a corner.</span>
            </template>
            <template v-else>
                <span class="text-slate-400">Click to select · double-click a unit to edit dimensions</span>
            </template>
            <span class="ml-auto text-slate-500 dark:text-slate-400">
                {{ onLayoutBoats.length }} on layout · {{ boatCount }} total
            </span>
        </div>

        <!-- ── Main content: canvas + off-layout panel ── -->
        <div
            ref="stageRef"
            class="flex min-h-0 min-w-0"
            :class="isFullscreen ? 'flex-1 overflow-hidden' : ''"
        >

            <!-- Canvas -->
            <div
                ref="containerRef"
                class="flex-1 min-w-0 min-h-0 bg-slate-200 dark:bg-slate-900"
                :class="isFullscreen
                    ? (fitToScreen ? 'h-full overflow-hidden flex items-center justify-center' : 'h-full overflow-auto')
                    : ''"
            >
                <canvas
                    ref="canvasRef"
                    :width="canvasW"
                    :height="canvasH"
                    :class="[
                        'block touch-none cursor-default',
                        isFullscreen && fitToScreen ? 'max-w-full max-h-full w-auto h-auto' : 'w-full h-auto',
                    ]"
                    @mousedown="onPointerDown"
                    @mousemove="onPointerMove"
                    @mouseup="onPointerUp"
                    @mouseleave="onPointerUp"
                    @touchstart.prevent="onPointerDown"
                    @touchmove.prevent="onPointerMove"
                    @touchend="onPointerUp"
                    @dblclick="onCanvasDblClick"
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
                        {{ unitStoreUrl
                            ? `${offLayoutBoats.length} at location, not on grid`
                            : `${offLayoutBoats.length} asset${offLayoutBoats.length !== 1 ? 's' : ''} hidden from layout` }}
                    </p>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                    <div
                        v-for="boat in offLayoutBoats"
                        :key="boat.linkId ?? `pool-${boat.assetUnitId}` ?? boat.id"
                        class="px-3 py-2.5 flex items-start gap-2.5 group hover:bg-white dark:hover:bg-slate-700/40 transition-colors"
                    >
                        <!-- Color swatch -->
                        <span
                            class="mt-0.5 w-2.5 h-2.5 rounded-full shrink-0"
                            :style="{ background: itemNotAtLocation(boat) ? '#E24B4A' : layoutFillColor(boat) }"
                        ></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-slate-800 dark:text-slate-100 truncate leading-tight">
                                {{ boat.displayName ?? boat.name }}
                            </p>
                            <p
                                v-if="boat.unitLabel"
                                class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate"
                                :title="boat.unitLabel"
                            >
                                {{ boat.unitLabel }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                {{ formatDimensionFt(boat.l) }} × {{ formatDimensionFt(boat.w) }} ft
                            </p>
                            <p
                                v-if="itemNotAtLocation(boat)"
                                class="text-[11px] text-red-500 dark:text-red-400 mt-0.5 flex items-center gap-1"
                            >
                                <span class="material-icons text-[12px]">location_off</span>
                                No longer at location
                                <span v-if="boat.currentLocationName">· {{ boat.currentLocationName }}</span>
                            </p>
                            <button
                                type="button"
                                :disabled="poolUnitEnrolling"
                                @click="addToGrid(boat)"
                                class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-primary-700 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700 rounded transition-colors hover:bg-primary-100 dark:hover:bg-primary-900/50 disabled:opacity-50"
                            >
                                <span class="material-icons text-[11px]">grid_on</span>
                                {{ boat.poolOnly ? 'Add to floor plan' : 'Add to grid' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Legend footer ── -->
        <div class="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500 dark:text-slate-400">
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
                <span class="inline-block w-3 h-3 rounded-sm bg-[#8B5CF6]"></span>
                Shapes
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-[#E24B4A]"></span>
                Out of bounds / not at location
            </span>
            <span class="text-slate-400 hidden lg:block">Stacking: trailer under boat under engine. Same type cannot overlap.</span>
            <span class="ml-auto">{{ spaceW }}' × {{ spaceH }}' floor plan</span>
            <span v-if="isFullscreen" class="text-slate-400">Esc to exit</span>
        </div>
        </div>
    </Teleport>

    <!-- ── Add / Edit modal ── -->
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="showModal"
                class="fixed inset-0 z-[210] flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click.self="showModal = false"
            >
                <div
                    class="relative z-[211] bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 p-6 w-80"
                    @click.stop
                >
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">
                        {{ modalMode === 'edit' ? 'Edit shape' : 'Add shape' }}
                    </h3>
                    <div class="space-y-3">
                        <div v-if="modalMode === 'add'" class="flex flex-wrap gap-1.5">
                            <button
                                v-for="preset in SHAPE_PRESETS"
                                :key="preset.label"
                                type="button"
                                class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-violet-50 hover:border-violet-200 hover:text-violet-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-violet-900/30 dark:hover:text-violet-300"
                                @click="applyShapePreset(preset)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Label</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Desk, Display table"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                                @keydown.enter="submitModal"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Shape</label>
                            <select
                                v-model="form.shape"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                                @change="syncFormDimensionsForShape"
                            >
                                <option value="rectangle">Rectangle</option>
                                <option value="square">Square</option>
                                <option value="circle">Circle</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    {{ form.shape === 'circle' ? 'Diameter (ft)' : 'Length (ft)' }}
                                </label>
                                <input
                                    v-model.number="form.length"
                                    type="number" min="1"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-primary-500"
                                    @input="syncFormDimensionsForShape"
                                />
                            </div>
                            <div v-if="form.shape === 'rectangle'">
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

    <!-- ── Unit dimensions modal (double-click asset unit) ── -->
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="showDimensionsModal"
                class="fixed inset-0 z-[210] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeDimensionsModal"
            >
                <div
                    class="relative z-[211] w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-800"
                    @click.stop
                >
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Edit unit dimensions</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ editingDimensionsBoat?.displayName ?? editingDimensionsBoat?.name ?? 'Unit' }}
                        <span v-if="editingDimensionsBoat?.unitLabel"> · {{ editingDimensionsBoat.unitLabel }}</span>
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Length (ft)</label>
                            <input
                                v-model.number="dimensionsForm.length"
                                type="number"
                                min="0.01"
                                max="500"
                                step="0.1"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:outline-none dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                @keydown.enter="saveDimensionsFloorPlanOnly"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Width (ft)</label>
                            <input
                                v-model.number="dimensionsForm.width"
                                type="number"
                                min="0.01"
                                max="500"
                                step="0.1"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:outline-none dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                @keydown.enter="saveDimensionsFloorPlanOnly"
                            />
                        </div>
                    </div>

                    <p v-if="dimensionsError" class="mt-3 text-xs text-red-600 dark:text-red-400">{{ dimensionsError }}</p>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                            :disabled="dimensionsSaving"
                            @click="closeDimensionsModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-700 hover:bg-primary-100 disabled:opacity-50 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50"
                            :disabled="dimensionsSaving"
                            @click="saveDimensionsFloorPlanOnly"
                        >
                            Floor plan only
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="dimensionsSaving || !editingDimensionsBoat?.assetUnitId"
                            :title="!editingDimensionsBoat?.assetUnitId ? 'Not linked to an inventory unit' : undefined"
                            @click="saveDimensionsFloorPlanAndUnit"
                        >
                            {{ dimensionsSaving ? 'Saving…' : 'Floor plan & unit' }}
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
