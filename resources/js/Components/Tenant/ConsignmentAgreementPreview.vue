<script setup>
import ConsignmentAgreementDocument from '@/Components/Tenant/ConsignmentAgreementDocument.vue';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';

defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    consignmentPolicies: { type: Array, default: () => [] },
});

defineEmits(['close']);

const rootRef = ref(null);
const printing = ref(false);

function findOverlay() {
    return rootRef.value?.closest('.consignment-agreement-preview-overlay') ?? null;
}

function resetScrollPositions() {
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;

    const overlay = findOverlay();
    if (!overlay) {
        return;
    }

    overlay.scrollTop = 0;

    let node = overlay;
    while (node) {
        if (node.scrollTop > 0) {
            node.scrollTop = 0;
        }
        node = node.parentElement;
    }

    overlay.querySelectorAll('*').forEach((element) => {
        if (element.scrollTop > 0) {
            element.scrollTop = 0;
        }
    });
}

function clearPrintingState() {
    printing.value = false;
    findOverlay()?.classList.remove('is-printing');
}

function prepareForPrint() {
    findOverlay()?.classList.add('is-printing');
    resetScrollPositions();
}

async function handlePrint() {
    if (printing.value) {
        return;
    }

    printing.value = true;
    prepareForPrint();

    await nextTick();
    resetScrollPositions();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            resetScrollPositions();
            window.print();
        });
    });
}

onMounted(() => {
    window.addEventListener('beforeprint', prepareForPrint);
    window.addEventListener('afterprint', clearPrintingState);
});

onUnmounted(() => {
    window.removeEventListener('beforeprint', prepareForPrint);
    window.removeEventListener('afterprint', clearPrintingState);
});
</script>

<template>
    <div ref="rootRef" class="consignment-preview-shell min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm print:hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto max-w-5xl px-3 py-2 sm:px-6 lg:px-8 lg:py-4">
                <div class="flex items-center justify-between gap-2 lg:gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-semibold text-gray-900 dark:text-white lg:text-lg">
                            Agreement preview
                        </h2>
                        <p class="mt-0.5 hidden text-sm text-gray-500 dark:text-gray-400 lg:block">
                            Sample owner-facing consignment agreement using your current fee, terms, and policy bullets
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5 lg:gap-3">
                        <button
                            type="button"
                            aria-label="Close preview"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 lg:px-4"
                            @click="$emit('close')"
                        >
                            <span class="material-icons text-[18px]">close</span>
                            <span class="hidden lg:inline">Close</span>
                        </button>
                        <button
                            type="button"
                            :aria-label="printing ? 'Preparing print' : 'Print preview'"
                            :aria-busy="printing"
                            :disabled="printing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="handlePrint"
                        >
                            <span v-if="printing" class="material-icons animate-spin text-[18px]">refresh</span>
                            <span v-else class="material-icons text-[18px]">print</span>
                            <span class="hidden lg:inline">{{ printing ? 'Preparing…' : 'Print' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConsignmentAgreementDocument
            :record="record"
            :account="account"
            :logo-url="logoUrl"
            :consignment-policies="consignmentPolicies"
            preview-mode
        />
    </div>
</template>

<style>
@media print {
    .sticky {
        display: none !important;
    }

    .min-h-screen {
        min-height: auto !important;
        background: white !important;
    }

    .bg-gray-100 {
        background: white !important;
    }

    .shadow-lg,
    .shadow-sm {
        box-shadow: none !important;
    }

    @page {
        margin: 0.5in;
    }
}
</style>
