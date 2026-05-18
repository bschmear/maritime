<template>
  <div class="flex flex-col h-full min-h-[360px] bg-[#080c0e] text-[#e8edf0] overflow-hidden rounded-xl font-sans">

    <!-- HEADER -->
    <div class="flex items-center justify-between px-4 py-3 bg-[#0d1417] border-b border-white/[0.06] shrink-0">
      <div class="flex items-center gap-2.5">
        <div class="flex items-center justify-center w-8 h-8 rounded-[7px] bg-emerald-400/10 text-emerald-400 shrink-0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <rect x="3" y="3" width="4" height="8" rx="0.5"/>
            <rect x="3" y="13" width="4" height="8" rx="0.5"/>
            <rect x="9" y="3" width="2" height="18" rx="0.5"/>
            <rect x="13" y="3" width="4" height="5" rx="0.5"/>
            <rect x="13" y="10" width="4" height="4" rx="0.5"/>
            <rect x="13" y="16" width="4" height="5" rx="0.5"/>
            <rect x="19" y="3" width="2" height="18" rx="0.5"/>
          </svg>
        </div>
        <div>
          <p class="m-0 text-[13px] font-semibold tracking-[0.01em] text-[#f0f4f6]">License Scan</p>
          <p class="m-0 text-[10px] text-[#4a6070] font-mono uppercase tracking-[0.08em]">PDF417 barcode reader</p>
        </div>
      </div>
      <button
        type="button"
        aria-label="Close"
        class="flex items-center justify-center w-7 h-7 rounded-md bg-transparent border-0 text-[#4a6070] cursor-pointer transition-colors duration-150 hover:bg-white/[0.07] hover:text-[#e8edf0]"
        @click="close"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- SCANNER AREA -->
    <div class="relative flex-1 min-h-0 bg-black">
      <div id="qr-reader" class="w-full h-full min-h-[220px]" />

      <!-- Overlay -->
      <div class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center" aria-hidden="true">
        <!-- Vignette -->
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_40%,rgba(0,0,0,0.72)_100%)]" />

        <!-- Corner-bracket frame -->
        <div class="relative z-10">
          <div class="relative w-[min(320px,82vw)] h-[140px]">
            <span class="absolute top-0 left-0 w-[22px] h-[22px] border-emerald-400 border-t-[2.5px] border-l-[2.5px] rounded-tl-[4px]" />
            <span class="absolute top-0 right-0 w-[22px] h-[22px] border-emerald-400 border-t-[2.5px] border-r-[2.5px] rounded-tr-[4px]" />
            <span class="absolute bottom-0 left-0 w-[22px] h-[22px] border-emerald-400 border-b-[2.5px] border-l-[2.5px] rounded-bl-[4px]" />
            <span class="absolute bottom-0 right-0 w-[22px] h-[22px] border-emerald-400 border-b-[2.5px] border-r-[2.5px] rounded-br-[4px]" />

            <!-- Laser sweep -->
            <div
              class="absolute left-1 right-1 h-0.5 rounded-sm bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_8px_2px_rgba(52,211,153,0.5)] transition-opacity duration-300"
              :class="isScanning ? 'opacity-100 animate-laser-sweep' : 'opacity-0'"
            />
          </div>
        </div>

        <!-- Status pill -->
        <div class="absolute bottom-3.5 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5 bg-[#080c0e]/80 backdrop-blur-sm border border-white/[0.08] rounded-full px-3 py-1.5 font-mono text-[10px] text-[#94aab8] whitespace-nowrap">
          <span
            class="w-1.5 h-1.5 rounded-full shrink-0 transition-colors duration-300"
            :class="isScanning ? 'bg-emerald-400 animate-pulse-dot' : 'bg-[#4a6070]'"
          />
          <span>{{ status }}</span>
        </div>
      </div>
    </div>

    <!-- CONTROLS -->
    <div class="flex items-center justify-between gap-2.5 px-3.5 py-2.5 bg-[#0d1417] border-t border-white/[0.06] shrink-0">
      <button
        type="button"
        class="flex items-center gap-1.5 px-3 py-[7px] rounded-[7px] border text-xs font-medium cursor-pointer transition-colors duration-150"
        :class="flashOn
          ? 'bg-emerald-400/10 border-emerald-400/35 text-emerald-400'
          : 'bg-white/[0.04] border-white/10 text-[#94aab8] hover:bg-white/[0.08] hover:text-[#e8edf0]'"
        @click="toggleFlash"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
        </svg>
        Flash
      </button>

      <div
        class="flex-1 text-center font-mono text-[10px] uppercase tracking-[0.1em] px-2.5 py-1 rounded-[5px] transition-all duration-200"
        :class="{
          'bg-emerald-400/10 text-emerald-400': scanState === 'scanning',
          'bg-emerald-400/[0.18] text-emerald-300': scanState === 'success',
          'bg-red-500/10 text-red-400': scanState === 'error',
          'bg-white/[0.04] text-[#4a6070]': scanState === 'idle',
        }"
      >{{ statusLabel }}</div>

      <button
        type="button"
        class="flex items-center gap-1.5 px-3 py-[7px] rounded-[7px] border border-emerald-400/30 bg-emerald-400/[0.14] text-emerald-400 text-xs font-medium cursor-pointer transition-colors duration-150 hover:bg-emerald-400/20 hover:text-emerald-300"
        @click="restartScanner"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M1 4v6h6M23 20v-6h-6"/>
          <path d="M20.49 9A9 9 0 005.64 5.64L1 10M23 14l-4.64 4.36A9 9 0 013.51 15"/>
        </svg>
        Retry
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const emit = defineEmits(['close', 'decoded']);

const scanner = ref(null);
const status = ref('Align barcode inside the frame');
const flashOn = ref(false);
const isScanning = ref(false);
const scanState = ref('idle'); // idle | scanning | success | error

const statusLabel = computed(() => {
  if (scanState.value === 'success') return 'Detected';
  if (scanState.value === 'error') return 'Error';
  if (scanState.value === 'scanning') return 'Live';
  return 'Idle';
});

onMounted(async () => { await startScanner(); });
onBeforeUnmount(async () => { await stopScannerQuiet(); });

async function startScanner() {
  status.value = 'Starting camera…';
  scanState.value = 'idle';
  isScanning.value = false;
  await stopScannerQuiet();

  scanner.value = new Html5Qrcode('qr-reader', {
    formatsToSupport: [Html5QrcodeSupportedFormats.PDF_417],
    verbose: false,
  });

  try {
    await scanner.value.start(
      { facingMode: 'environment' },
      {
        fps: 15,
        // No qrbox — scan the full video frame for best PDF417 detection
        aspectRatio: 1.777778,
      },
      (decodedText) => { void handleScanSuccess(decodedText); },
      () => {}
    );
    status.value = 'Hold the PDF417 barcode steady in frame';
    scanState.value = 'scanning';
    isScanning.value = true;
  } catch (err) {
    status.value = 'Camera error — allow access (HTTPS required)';
    scanState.value = 'error';
    console.error(err);
  }
}

async function handleScanSuccess(decodedText) {
  status.value = 'Barcode detected!';
  scanState.value = 'success';
  isScanning.value = false;
  try {
    await stopScannerQuiet();
    emit('decoded', decodedText);
  } catch (e) {
    console.error(e);
    status.value = 'Error processing result';
    scanState.value = 'error';
  }
}

async function stopScannerQuiet() {
  flashOn.value = false;
  isScanning.value = false;
  if (!scanner.value) return;
  try { if (scanner.value.isScanning) await scanner.value.stop(); } catch (_) {}
  try { scanner.value.clear(); } catch (_) {}
  scanner.value = null;
}

async function toggleFlash() {
  if (!scanner.value?.isScanning) return;
  try {
    const caps = scanner.value.getRunningTrackCapabilities?.();
    if (!caps?.torch) { status.value = 'Flash not supported on this device'; return; }
    flashOn.value = !flashOn.value;
    await scanner.value.applyVideoConstraints({ advanced: [{ torch: flashOn.value }] });
  } catch {
    status.value = 'Could not toggle flash';
  }
}

async function restartScanner() {
  await stopScannerQuiet();
  await startScanner();
}

function close() {
  void stopScannerQuiet();
  emit('close');
}
</script>

<style scoped>
/* Keyframe animations — only thing that can't be done in Tailwind without config */
@keyframes laser-sweep {
  0%   { top: 8%;  opacity: 0.9; }
  50%  { top: 88%; opacity: 1;   }
  100% { top: 8%;  opacity: 0.9; }
}
@keyframes pulse-dot {
  0%, 100% { opacity: 1;   transform: scale(1);    }
  50%       { opacity: 0.4; transform: scale(0.75); }
}
.animate-laser-sweep {
  animation: laser-sweep 1.8s ease-in-out infinite;
  position: absolute;
}
.animate-pulse-dot {
  animation: pulse-dot 1.4s ease-in-out infinite;
}

/* Override html5-qrcode injected DOM */
:deep(#qr-reader)             { border: none !important; }
:deep(#qr-reader video)       { width: 100% !important; height: 100% !important; object-fit: cover !important; }
:deep(#qr-reader img)         { display: none !important; }
:deep(#qr-reader__scan_region){ border: none !important; }
</style>