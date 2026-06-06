<script setup>
import MsoFieldOverlay from '@/Components/Tenant/Mso/MsoFieldOverlay.vue';
import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

const props = defineProps({
    previewUrl: {
        type: String,
        default: null,
    },
    fields: {
        type: Array,
        default: () => [],
    },
    selectedFieldId: {
        type: String,
        default: null,
    },
    fieldTypeLabels: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['add-field', 'select-field', 'update-field', 'delete-field', 'page-count', 'page-sizes']);

const pageCount = ref(0);
const currentPage = ref(1);
const pageWidth = ref(0);
const pageHeight = ref(0);
const naturalPageWidth = ref(0);
const naturalPageHeight = ref(0);
const loading = ref(false);
const error = ref(null);
const pageCanvas = ref(null);
const overlayRoot = ref(null);
const canvasContainerRef = ref(null);

/** US Letter width at 72 DPI (pdf.js default user space). Used as fallback only. */
const STANDARD_PDF_WIDTH_PT = 612;

let pdfDoc = null;
let interactCleanups = [];
let resizeObserver = null;
let resizeRenderTimer = null;
let lastDisplayScale = 1;

const canvasDisplayStyle = computed(() => {
    if (!pageWidth.value || !pageHeight.value) {
        return undefined;
    }

    return {
        width: `${pageWidth.value}px`,
        height: `${pageHeight.value}px`,
    };
});

function containerWidthForScale() {
    return canvasContainerRef.value?.clientWidth ?? 0;
}

/** Never upscale beyond the uploaded PDF's native size (scale ≤ 1). */
function displayScaleForPage(containerWidth) {
    const nativeWidth = naturalPageWidth.value || STANDARD_PDF_WIDTH_PT;

    if (!containerWidth || containerWidth >= nativeWidth) {
        return 1;
    }

    return containerWidth / nativeWidth;
}

const pageFields = computed(() =>
    props.fields.filter((field) => Number(field.page) === currentPage.value),
);

const interactBindingKey = computed(() =>
    [
        props.fields.map((field) => `${field.id}:${field.page}`).join('|'),
        props.selectedFieldId ?? '',
        pageWidth.value,
        pageHeight.value,
    ].join(':'),
);

const pageSizesByPage = ref({});

function overlayMetrics() {
    const rect = overlayRoot.value?.getBoundingClientRect();
    if (!rect?.width || !rect?.height) {
        return null;
    }

    return {
        rect,
        width: rect.width,
        height: rect.height,
    };
}

function emitPageSizes() {
    emit('page-sizes', { ...pageSizesByPage.value });
}

async function renderPage(pageNumber) {
    if (!pdfDoc || !pageCanvas.value) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const page = await pdfDoc.getPage(pageNumber);
        const naturalViewport = page.getViewport({ scale: 1 });
        naturalPageWidth.value = naturalViewport.width;
        naturalPageHeight.value = naturalViewport.height;

        const displayScale = displayScaleForPage(containerWidthForScale());
        lastDisplayScale = displayScale;

        const pixelRatio = typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1;
        const renderViewport = page.getViewport({ scale: displayScale * pixelRatio });
        const layoutViewport = page.getViewport({ scale: displayScale });

        const canvas = pageCanvas.value;
        const context = canvas.getContext('2d');

        canvas.width = renderViewport.width;
        canvas.height = renderViewport.height;
        pageWidth.value = layoutViewport.width;
        pageHeight.value = layoutViewport.height;
        pageSizesByPage.value = {
            ...pageSizesByPage.value,
            [pageNumber]: {
                width: naturalViewport.width,
                height: naturalViewport.height,
            },
        };
        emitPageSizes();

        await page.render({ canvasContext: context, viewport: renderViewport }).promise;
        await nextTick();
        setupInteract();
    } catch (e) {
        error.value = 'Unable to render PDF page.';
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function loadPdf(url) {
    if (!url) {
        pdfDoc = null;
        pageCount.value = 0;
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        pdfDoc = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
        pageCount.value = pdfDoc.numPages;
        emit('page-count', pageCount.value);
        currentPage.value = 1;
        await renderPage(1);
    } catch (e) {
        error.value = 'Unable to load PDF.';
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function teardownInteract() {
    interactCleanups.forEach((cleanup) => cleanup());
    interactCleanups = [];
}

function setupInteract() {
    teardownInteract();

    if (!overlayRoot.value) {
        return;
    }

    props.fields
        .filter((field) => Number(field.page) === currentPage.value)
        .forEach((field) => {
            const el = overlayRoot.value?.querySelector(`[data-field-id="${field.id}"]`);
            if (!el) {
                return;
            }

            const applyResize = (event) => {
                const metrics = overlayMetrics();
                const updates = {
                    width: metrics
                        ? Math.min(1, Math.max(0.05, event.rect.width / metrics.width))
                        : Math.min(1, Math.max(0.05, event.rect.width / pageWidth.value)),
                    height: metrics
                        ? Math.min(1, Math.max(0.02, event.rect.height / metrics.height))
                        : Math.min(1, Math.max(0.02, event.rect.height / pageHeight.value)),
                };

                if (metrics) {
                    updates.x = Math.min(0.98, Math.max(0, (event.rect.left - metrics.rect.left) / metrics.width));
                    updates.y = Math.min(0.98, Math.max(0, (event.rect.top - metrics.rect.top) / metrics.height));
                }

                emit('update-field', field.id, updates);
            };

            const interaction = interact(el);

            const draggable = interaction.draggable({
                allowFrom: '.drag-handle',
                ignoreFrom: '.field-input, button, .resize-handle-e, .resize-handle-s, .resize-handle-se',
                listeners: {
                    move(event) {
                        const target = event.target;
                        const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
                        const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
                        target.style.transform = `translate(${x}px, ${y}px)`;
                        target.setAttribute('data-x', x);
                        target.setAttribute('data-y', y);
                    },
                    end(event) {
                        const target = event.target;
                        const translateX = parseFloat(target.getAttribute('data-x')) || 0;
                        const translateY = parseFloat(target.getAttribute('data-y')) || 0;
                        const metrics = overlayMetrics();
                        const coordWidth = metrics?.width ?? pageWidth.value;
                        const coordHeight = metrics?.height ?? pageHeight.value;
                        const baseX = field.x * coordWidth;
                        const baseY = field.y * coordHeight;

                        emit('update-field', field.id, {
                            x: Math.min(0.98, Math.max(0, (baseX + translateX) / coordWidth)),
                            y: Math.min(0.98, Math.max(0, (baseY + translateY) / coordHeight)),
                        });

                        target.style.transform = '';
                        target.setAttribute('data-x', '0');
                        target.setAttribute('data-y', '0');
                    },
                },
            });

            const resizable = interaction.resizable({
                edges: {
                    right: '.resize-handle-e',
                    bottom: '.resize-handle-s',
                    bottomRight: '.resize-handle-se',
                },
                ignoreFrom: '.field-input',
                listeners: {
                    move: applyResize,
                    end: applyResize,
                },
            });

            interactCleanups.push(() => {
                draggable.unset();
                resizable.unset();
            });
        });
}

function placeNewField(type, clientX, clientY) {
    const rect = overlayRoot.value?.getBoundingClientRect();
    if (!rect?.width || !rect?.height) {
        emit('add-field', { type, page: currentPage.value, x: 0.1, y: 0.1 });
        return;
    }

    const x = Math.min(0.9, Math.max(0, (clientX - rect.left) / rect.width));
    const y = Math.min(0.9, Math.max(0, (clientY - rect.top) / rect.height));

    emit('add-field', {
        type,
        page: currentPage.value,
        x,
        y,
        width: 0.25,
        height: 0.04,
    });
}

function onCanvasDrop(event) {
    event.preventDefault();
    const type = event.dataTransfer?.getData('application/mso-field-type');
    if (type) {
        placeNewField(type, event.clientX, event.clientY);
    }
}

function onCanvasDragOver(event) {
    event.preventDefault();
}

function onCanvasClick(event) {
    const root = overlayRoot.value;
    if (!root) {
        return;
    }

    if (event.target === root || event.target === pageCanvas.value) {
        emit('select-field', null);
    }
}

function goToPage(delta) {
    const next = currentPage.value + delta;
    if (next < 1 || next > pageCount.value) {
        return;
    }
    currentPage.value = next;
    renderPage(next);
}

watch(interactBindingKey, () => nextTick(() => setupInteract()));

function scheduleRenderOnResize() {
    if (!pdfDoc || loading.value) {
        return;
    }

    const nextScale = displayScaleForPage(containerWidthForScale());
    if (nextScale === lastDisplayScale) {
        return;
    }

    clearTimeout(resizeRenderTimer);
    resizeRenderTimer = setTimeout(() => {
        renderPage(currentPage.value);
    }, 150);
}

function attachResizeObserver() {
    nextTick(() => {
        if (!resizeObserver || !canvasContainerRef.value) {
            return;
        }

        resizeObserver.disconnect();
        resizeObserver.observe(canvasContainerRef.value);
    });
}

watch(
    () => props.previewUrl,
    async (url) => {
        if (!url) {
            return;
        }

        await loadPdf(url);
        attachResizeObserver();
    },
    { immediate: true },
);

onMounted(() => {
    resizeObserver = new ResizeObserver(() => scheduleRenderOnResize());
    attachResizeObserver();
});

onBeforeUnmount(() => {
    clearTimeout(resizeRenderTimer);
    resizeObserver?.disconnect();
    teardownInteract();
});

defineExpose({ placeNewField, currentPage });
</script>

<template>
    <div class="space-y-3">
        <div v-if="!previewUrl" class="flex min-h-[480px] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-900/40">
            <p class="text-sm text-gray-500 dark:text-gray-400">Upload an original MSO PDF to begin.</p>
        </div>

        <template v-else>
            <div v-if="pageCount > 1" class="flex items-center justify-between">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-gray-600"
                    :disabled="currentPage <= 1"
                    @click="goToPage(-1)"
                >
                    Previous page
                </button>
                <span class="text-sm text-gray-600 dark:text-gray-300">Page {{ currentPage }} of {{ pageCount }}</span>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-gray-600"
                    :disabled="currentPage >= pageCount"
                    @click="goToPage(1)"
                >
                    Next page
                </button>
            </div>

            <div ref="canvasContainerRef" class="flex w-full justify-center overflow-auto">
                <div
                    ref="overlayRoot"
                    class="relative inline-block shrink-0 rounded-xl bg-gray-100 outline outline-1 -outline-offset-1 outline-gray-200 dark:bg-gray-900 dark:outline-gray-700"
                    :style="canvasDisplayStyle"
                    @drop="onCanvasDrop"
                    @dragover="onCanvasDragOver"
                    @click="onCanvasClick"
                >
                    <canvas ref="pageCanvas" class="absolute left-0 top-0 block" :style="canvasDisplayStyle" />
                <MsoFieldOverlay
                    v-for="field in pageFields"
                    :key="field.id"
                    :field="field"
                    :selected="selectedFieldId === field.id"
                    :page-width="pageWidth"
                    :page-height="pageHeight"
                    :label="fieldTypeLabels[field.type] || field.type"
                    @select="emit('select-field', $event)"
                    @update-value="(id, value) => emit('update-field', id, { value })"
                    @delete="emit('delete-field', $event)"
                />
                </div>
            </div>

            <p v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading PDF…</p>
            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        </template>
    </div>
</template>
