<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { defaultRectPerimeter } from '@/Utils/layoutGeometry.js';
import { formatCalendarDateShort } from '@/Utils/calendarDate.js';
import { layoutItemLabel } from '@/Utils/layoutLabels.js';

const props = defineProps({
    event: { type: Object, required: true },
    assets: { type: Object, default: () => ({ boats: [], engines: [], trailers: [] }) },
    layoutSpace: {
        type: Object,
        default: () => ({ width_ft: 60, height_ft: 40, perimeter: null, fixtures: [] }),
    },
    companyName: { type: String, default: '' },
    layoutName: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    titleFallback: { type: String, default: 'Boat show event' },
});

const canvasRef = ref(null);
const printImgRef = ref(null);
const MARGIN = 28;
const PRINT_MAX_WIDTH = 760;

const ASSET_TYPE = { BOAT: 1, ENGINE: 2, TRAILER: 3 };
const LAYOUT_COLOR_BY_TYPE = {
    [ASSET_TYPE.BOAT]: '#3B82F6',
    [ASSET_TYPE.ENGINE]: '#F97316',
    [ASSET_TYPE.TRAILER]: '#22C55E',
};
const FIXTURE_COLOR = '#8B5CF6';

const spaceW = computed(() => Math.max(10, Number(props.layoutSpace?.width_ft) || 60));
const spaceH = computed(() => Math.max(10, Number(props.layoutSpace?.height_ft) || 40));

const perimeterPoints = computed(() => {
    const raw = props.layoutSpace?.perimeter;
    if (Array.isArray(raw) && raw.length >= 3) {
        return raw.map((pt) => ({ x: Number(pt.x) || 0, y: Number(pt.y) || 0 }));
    }
    return defaultRectPerimeter(spaceW.value, spaceH.value);
});

const scale = computed(() =>
    Math.max(4, Math.floor((PRINT_MAX_WIDTH - MARGIN * 2) / spaceW.value)),
);

const canvasW = computed(() => spaceW.value * scale.value + MARGIN * 2);
const canvasH = computed(() => spaceH.value * scale.value + MARGIN * 2);

function typeLabelFor(row, isFixture, shape) {
    if (isFixture) {
        if (shape === 'circle') return 'Round shape';
        if (shape === 'square') return 'Square shape';
        return 'Shape';
    }
    const t = Number(row.type ?? row.assetType);
    if (t === ASSET_TYPE.BOAT) return 'Boat';
    if (t === ASSET_TYPE.ENGINE) return 'Engine';
    if (t === ASSET_TYPE.TRAILER) return 'Trailer';
    return 'Item';
}

function dimsLabel(l, w, shape) {
    if (shape === 'circle') return `Ø ${l} ft`;
    return `${l} × ${w} ft`;
}

const legendItems = computed(() => {
    const rows = [
        ...(props.assets.boats ?? []),
        ...(props.assets.engines ?? []),
        ...(props.assets.trailers ?? []),
    ]
        .filter((r) => r.include_in_layout)
        .map((r) => {
            const l = parseFloat(r.length_ft ?? r.length) || 20;
            const w = parseFloat(r.width_ft ?? r.width) || 8;
            const rotated = Number(r.rotation ?? 0) % 180 === 90;
            return {
                x: Number(r.x) || 0,
                y: Number(r.y) || 0,
                l,
                w,
                rotated,
                assetType: r.type != null ? Number(r.type) : null,
                shape: null,
                fixtureId: null,
                name: layoutItemLabel(r),
                typeLabel: typeLabelFor(r, false, null),
                dims: dimsLabel(l, w, null),
                color: LAYOUT_COLOR_BY_TYPE[Number(r.type)] ?? '#64748B',
            };
        });

    for (const f of props.layoutSpace?.fixtures ?? []) {
        if (f.include_in_layout === false) continue;
        const shape = f.shape || 'rectangle';
        const l = parseFloat(f.length_ft) || 4;
        const w = shape === 'circle' || shape === 'square' ? l : parseFloat(f.width_ft) || 4;
        const rotated = Number(f.rotation ?? 0) % 180 === 90;
        rows.push({
            x: Number(f.x) || 0,
            y: Number(f.y) || 0,
            l,
            w,
            rotated,
            assetType: null,
            shape,
            fixtureId: f.id,
            name: (f.label || f.name || 'Shape').trim(),
            typeLabel: typeLabelFor(null, true, shape),
            dims: dimsLabel(l, w, shape),
            color: FIXTURE_COLOR,
        });
    }

    return rows
        .sort((a, b) => a.y - b.y || a.x - b.x)
        .map((item, index) => ({ ...item, key: index + 1 }));
});

const eventDates = computed(() => {
    const s = props.event.starts_at;
    const e = props.event.ends_at;
    if (s && e) {
        return `${formatCalendarDateShort(s, { year: 'numeric' })} – ${formatCalendarDateShort(e, { year: 'numeric' })}`;
    }
    if (s) return formatCalendarDateShort(s, { year: 'numeric' });
    if (e) return formatCalendarDateShort(e, { year: 'numeric' });
    return null;
});

function fillColor(item) {
    if (item.fixtureId) return FIXTURE_COLOR;
    if (item.assetType == null) return '#64748B';
    return LAYOUT_COLOR_BY_TYPE[item.assetType] ?? '#64748B';
}

function drawKeyBadge(ctx, px, py, key, S) {
    const r = Math.min(13, Math.max(9, S * 0.35));
    const cx = px + r + 3;
    const cy = py + r + 3;
    ctx.fillStyle = '#ffffff';
    ctx.strokeStyle = '#0f172a';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = '#0f172a';
    ctx.font = `bold ${Math.max(10, Math.round(r * 1.1))}px sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(String(key), cx, cy);
}

function draw() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const S = scale.value;
    const cw = canvasW.value;
    const ch = canvasH.value;
    const poly = perimeterPoints.value;

    ctx.clearRect(0, 0, cw, ch);
    ctx.fillStyle = '#f1f5f9';
    ctx.fillRect(0, 0, cw, ch);

    ctx.fillStyle = '#f8fafc';
    ctx.strokeStyle = '#64748b';
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

    ctx.strokeStyle = 'rgba(71,85,105,0.1)';
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

    for (const item of legendItems.value) {
        const px = MARGIN + item.x * S;
        const py = MARGIN + item.y * S;
        const pw = (item.rotated ? item.w : item.l) * S;
        const ph = (item.rotated ? item.l : item.w) * S;
        const isCircle = item.shape === 'circle';

        ctx.globalAlpha = 0.92;
        ctx.fillStyle = fillColor(item);
        ctx.strokeStyle = 'rgba(15,23,42,0.25)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        if (isCircle) {
            ctx.ellipse(px + pw / 2, py + ph / 2, pw / 2, ph / 2, 0, 0, Math.PI * 2);
        } else if (ctx.roundRect) {
            ctx.roundRect(px, py, pw, ph, 4);
        } else {
            ctx.rect(px, py, pw, ph);
        }
        ctx.fill();
        ctx.stroke();
        ctx.globalAlpha = 1;

        drawKeyBadge(ctx, px, py, item.key, S);

        ctx.fillStyle = '#ffffff';
        ctx.font = `600 ${Math.min(11, Math.max(8, ph * 0.22))}px sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        let label = item.name;
        const maxW = pw - 20;
        while (ctx.measureText(`${label}…`).width > maxW && label.length > 1) {
            label = label.slice(0, -1);
        }
        if (label !== item.name) label += '…';
        if (ph > 16) {
            ctx.fillText(label, px + pw / 2, py + ph / 2);
        }
    }
}

function syncPrintImage() {
    const canvas = canvasRef.value;
    const img = printImgRef.value;
    if (!canvas || !img) return;
    try {
        img.src = canvas.toDataURL('image/png');
        img.width = canvasW.value;
        img.height = canvasH.value;
    } catch {
        // ignore tainted canvas / unsupported browser
    }
}

async function redraw() {
    await nextTick();
    requestAnimationFrame(() => {
        draw();
        syncPrintImage();
    });
}

async function runPrint() {
    await redraw();
    setTimeout(() => window.print(), 200);
}

onMounted(() => redraw());
watch([legendItems, canvasW, canvasH, perimeterPoints], () => redraw());
</script>

<template>
    <div id="layout-print-root" class="layout-print-root min-h-screen bg-gray-100 text-gray-900">
        <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-4xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <slot name="back" />
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700"
                    @click="runPrint"
                >
                    <span class="material-icons text-base leading-none">print</span>
                    Print layout
                </button>
            </div>
        </div>

        <section class="print-layout-sheet mx-auto max-w-4xl px-4 py-8">
            <header class="mb-6 border-b border-gray-200 pb-4">
                <p v-if="companyName" class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ companyName }}
                </p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">
                    {{ event.display_name ?? titleFallback }}
                </h1>
                <p v-if="subtitle" class="mt-2 text-sm text-gray-600">
                    {{ subtitle }}
                </p>
                <p v-else-if="event.venue || eventDates" class="mt-2 text-sm text-gray-600">
                    <span v-if="event.venue">{{ event.venue }}</span>
                    <span v-if="event.venue && eventDates"> · </span>
                    <span v-if="eventDates">{{ eventDates }}</span>
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    Floor plan {{ spaceW }}′ × {{ spaceH }}′
                    <span v-if="layoutName"> · {{ layoutName }}</span>
                    <span v-if="legendItems.length"> · {{ legendItems.length }} item{{ legendItems.length === 1 ? '' : 's' }} on layout</span>
                </p>
            </header>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white p-4 shadow-sm print:shadow-none">
                <canvas
                    ref="canvasRef"
                    :width="canvasW"
                    :height="canvasH"
                    class="layout-print-canvas mx-auto block max-w-full"
                    :style="{ width: `${canvasW}px`, height: `${canvasH}px` }"
                />
                <!-- Browsers often print <canvas> blank; snapshot as <img> for print. -->
                <img
                    ref="printImgRef"
                    alt="Floor plan"
                    class="layout-print-canvas-img mx-auto hidden max-w-full"
                />
            </div>

            <p
                v-if="legendItems.length === 0"
                class="mt-4 rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500"
            >
                Nothing is placed on the layout yet. The floor plan above shows the space dimensions — add assets or shapes on the layout builder, then print again.
            </p>

            <div v-if="legendItems.length > 0" class="mt-8">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Layout key</h2>
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm print:shadow-none">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="w-12 px-4 py-3 font-semibold">#</th>
                                <th class="px-4 py-3 font-semibold">Item</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 font-semibold">Size</th>
                                <th class="px-4 py-3 font-semibold">Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in legendItems"
                                :key="item.key"
                                class="border-t border-gray-100"
                            >
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 bg-white text-xs font-bold text-gray-900"
                                    >
                                        {{ item.key }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <span class="mr-2 inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: item.color }" />
                                    {{ item.name }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ item.typeLabel }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ item.dims }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ item.x }}′, {{ item.y }}′</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    .layout-print-root {
        background: #fff !important;
        color: #111827 !important;
    }

    .print-layout-sheet {
        max-width: none;
        padding: 0;
    }

    .layout-print-canvas {
        display: none !important;
    }

    .layout-print-canvas-img {
        display: block !important;
        max-width: 100% !important;
        height: auto !important;
    }
}
</style>

<style>
@media print {
    #layout-print-root,
    #layout-print-root * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body:has(#layout-print-root) {
        background: #fff !important;
    }

    .dark #layout-print-root {
        background: #fff !important;
        color: #111827 !important;
    }

    .dark #layout-print-root .text-gray-500,
    .dark #layout-print-root .text-gray-600,
    .dark #layout-print-root .text-gray-700,
    .dark #layout-print-root .text-gray-900 {
        color: #374151 !important;
    }

    .dark #layout-print-root .text-gray-900,
    .dark #layout-print-root h1,
    .dark #layout-print-root h2 {
        color: #111827 !important;
    }

    .dark #layout-print-root .bg-gray-50 {
        background-color: #f9fafb !important;
    }

    .dark #layout-print-root .bg-white {
        background-color: #fff !important;
    }
}
</style>
