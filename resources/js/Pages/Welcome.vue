
Copy

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const billingCycle = ref('monthly');

defineProps({
    canLogin:     { type: Boolean },
    canRegister:  { type: Boolean },
    blogPosts:    { type: Array, default: () => [] },
    pricingPlans: { type: Array, default: () => [] },
    faqs:         { type: Array, default: () => [] },
    heroHighlights: {
        type: Array,
        default: () => [
            {
                icon: 'hub',
                title: 'Everything connected',
                description: 'No more jumping between spreadsheets, inboxes, and legacy tools. One platform, one source of truth.',
            },
            {
                icon: 'speed',
                title: 'Built for your pace',
                description: 'Designed around how dealerships actually work — fast-moving, seasonal, relationship-driven.',
            },
            {
                icon: 'groups',
                title: 'Made for your whole team',
                description: 'Sales, service, and management all in sync — no more silos between departments.',
            },
        ],
    },
});

// ── Hero rulers ──
const DEPTH_STEP_FT = 10;
const STRIP_HALF_STEPS = 80;
const heroStripTicksFt = Array.from(
    { length: STRIP_HALF_STEPS * 2 + 1 },
    (_, i) => (i - STRIP_HALF_STEPS) * DEPTH_STEP_FT,
);
const heroVerticalStripTicksFt = [...heroStripTicksFt].reverse();

const HORIZ_HALF_STEPS = 6;
const heroHorizontalTickFt = Array.from(
    { length: HORIZ_HALF_STEPS * 2 + 1 },
    (_, i) => (i - HORIZ_HALF_STEPS) * DEPTH_STEP_FT,
);

const scrollY = ref(0);
const VERTICAL_RULER_SCROLL_FACTOR = 0.24;
/** 0′ on the vertical rulers (and the sea line) sit this far down: 0 = top, 1 = bottom. */
const HERO_AXIS_ZERO_FRACTION = 0.70;

/** Pixels the hero wave (inner) and vertical rulers are shifted for scroll parallax. */
const waveParallaxPx = computed(() => scrollY.value * VERTICAL_RULER_SCROLL_FACTOR);


const leftRulerViewport = ref(null);
const leftRulerStrip = ref(null);
const verticalAlignPx = ref(0);


function handleScroll() {
    scrollY.value = window.scrollY;
}

function measureHeroRulers() {
    nextTick(() => {
        requestAnimationFrame(() => {
            if (typeof window !== 'undefined' && !window.matchMedia('(min-width: 768px)').matches) {
                return;
            }
            const vVp = leftRulerViewport.value;
            const vStrip = leftRulerStrip.value;
            if (vVp && vStrip) {
                const z = vStrip.querySelector('.hero-axis-zero');
                if (z) {
                    const vpr = vVp.getBoundingClientRect();
                    const zr  = z.getBoundingClientRect();
                    // Align strip so 0′ is ~3/4 down the ruler viewport (matches the wave)
                    const vc = vpr.top + vpr.height * HERO_AXIS_ZERO_FRACTION;
                    const zc = zr.top + zr.height / 2;
                    verticalAlignPx.value += vc - zc;

                }
            }
        });
    });
}


onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', measureHeroRulers);
    handleScroll();
    measureHeroRulers();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('resize', measureHeroRulers);
});
</script>

<template>
    <Head title="Welcome" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <!-- ── HERO ─────────────────────────────────────────────────────────── -->
    <section class="relative flex min-h-[85vh] flex-col justify-center overflow-hidden bg-white dark:bg-gray-950 px-6 sm:px-16 lg:px-24 border-b border-gray-300/70 dark:border-primary-400/35">
 
 <!-- ── Background: coordinate grid ───────────────────────────── -->
 <div class="pointer-events-none absolute inset-0 hidden select-none opacity-50 lg:block" aria-hidden="true">
     <div
         v-for="n in 9"
         :key="'v' + n"
         class="absolute top-0 bottom-0 w-px bg-primary-400/15 dark:bg-primary-400/20"
         :style="{ left: `${(n - 1) * 12.5}%` }"
     />
     <div
         v-for="n in 7"
         :key="'h' + n"
         class="absolute left-0 right-0 h-px bg-primary-400/15 dark:bg-primary-400/20"
         :style="{ top: `${(n - 1) * 16.666}%` }"
     />
     <div class="absolute top-0 bottom-0 left-1/4 w-px bg-primary-400/30 dark:bg-primary-400/35" />
     <div class="absolute top-0 bottom-0 left-3/4 w-px bg-primary-400/20 dark:bg-primary-400/25" />
     <div class="absolute left-0 right-0 top-1/3 h-px bg-primary-400/20 dark:bg-primary-400/25" />
 </div>

 <!-- ── Sea-level wave ─────────────────────────────────────────── -->
 <div
     class="pointer-events-none absolute left-0 right-0 bottom-0 z-[5] overflow-hidden select-none hidden lg:block"
     aria-hidden="true"
     :style="{
         top: `calc(${HERO_AXIS_ZERO_FRACTION * 90}%)`,
         height: `calc(100% + 4rem + ${waveParallaxPx}px)`,
         transform: `translateY(-${waveParallaxPx}px)`,
     }"
 >
     <div class="relative will-change-transform">
         <svg class="h-full w-full" viewBox="0 0 1440 500" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
             <defs>
                 <linearGradient id="waveFillLight" x1="0" y1="0" x2="0" y2="1">
                     <stop offset="0%" stop-color="rgb(56 189 248)" stop-opacity="0.18" />
                     <stop offset="100%" stop-color="rgb(14 165 233)" stop-opacity="0.07" />
                 </linearGradient>
                 <linearGradient id="waveFillDark" x1="0" y1="0" x2="0" y2="1">
                     <stop offset="0%" stop-color="rgb(56 189 248)" stop-opacity="0.22" />
                     <stop offset="100%" stop-color="rgb(2 132 199)" stop-opacity="0.09" />
                 </linearGradient>
             </defs>
             <path class="wave-back" fill="none" stroke="rgb(125 211 252 / 0.25)" stroke-width="2" d="M0,40 C180,20 360,60 540,40 C720,20 900,60 1080,40 C1260,20 1440,40 1440,40" />
             <path class="wave-front" fill="none" stroke="rgb(56 189 248 / 0.6)" stroke-width="2.5" d="M0,38 C200,18 400,58 600,38 C800,18 1000,58 1200,38 C1320,25 1380,32 1440,38" />
             <path class="wave-fill dark:hidden" fill="url(#waveFillLight)" d="M0,38 C200,18 400,58 600,38 C800,18 1000,58 1200,38 C1320,25 1380,32 1440,38 L1440,500 L0,500 Z" />
             <path class="wave-fill hidden dark:block" fill="url(#waveFillDark)" d="M0,38 C200,18 400,58 600,38 C800,18 1000,58 1200,38 C1320,25 1380,32 1440,38 L1440,500 L0,500 Z" />
         </svg>
 
     </div>
 </div>

 <!-- ── Left ruler ── -->
 <div
     ref="leftRulerViewport"
     class="pointer-events-none absolute bottom-16 left-0 top-16 z-10 hidden w-16 overflow-hidden border-r border-gray-300/70 dark:border-primary-400/35 select-none lg:block bg-white dark:bg-gray-950"
     aria-hidden="true"
 >
     <div
         ref="leftRulerStrip"
         class="flex w-full flex-col items-center gap-12 py-24 will-change-transform"
         :style="{ transform: `translateY(${verticalAlignPx - scrollY * VERTICAL_RULER_SCROLL_FACTOR}px)` }"
     >
         <div
             v-for="ft in heroVerticalStripTicksFt"
             :key="`L-${ft}`"
             class="flex w-full shrink-0 justify-center text-[11px] font-mono tabular-nums text-gray-500 dark:text-primary-400/50"
             :class="{ 'text-sky-600 dark:text-sky-400 font-bold': ft === 0 }"
         >
             <span class="leading-none" :class="{ 'hero-axis-zero': ft === 0 }">{{ ft }}′</span>
         </div>
     </div>
 </div>

 <!-- ── Right ruler ── -->
 <div
     class="pointer-events-none absolute bottom-16 right-0 top-16 z-10 hidden w-16 overflow-hidden border-l border-gray-300/70 dark:border-primary-400/35 select-none lg:block bg-white dark:bg-gray-950"
     aria-hidden="true"
 >
     <div
         class="flex w-full flex-col items-center gap-12 py-24 will-change-transform"
         :style="{ transform: `translateY(${verticalAlignPx - scrollY * VERTICAL_RULER_SCROLL_FACTOR}px)` }"
     >
         <div
             v-for="ft in heroVerticalStripTicksFt"
             :key="`R-${ft}`"
             class="flex w-full shrink-0 justify-center text-[11px] font-mono tabular-nums text-gray-500 dark:text-primary-400/50"
             :class="{ 'text-sky-600 dark:text-sky-400 font-bold': ft === 0 }"
         >
             <span class="leading-none" :class="{ 'hero-axis-zero': ft === 0 }">{{ ft }}′</span>
         </div>
     </div>
 </div>

 <!-- ── Bottom tape ── -->
 <div class="pointer-events-none absolute bottom-0 left-16 right-16 z-10 hidden h-16 overflow-hidden border-t border-gray-300/70 dark:border-primary-400/35 bg-white dark:bg-gray-950 select-none lg:block" aria-hidden="true">
     <div class="flex h-full w-full flex-row items-center px-1">
         <div v-for="ft in heroHorizontalTickFt" :key="`H-${ft}`" class="flex min-h-0 min-w-0 flex-1 flex-col items-center justify-center gap-1 text-[11px] font-mono tabular-nums text-gray-500 dark:text-primary-400/50">
             <div class="h-2 w-px shrink-0 bg-gray-400/60 dark:bg-primary-400/50" />
             <span class="text-center leading-none">{{ ft }}′</span>
         </div>
     </div>
 </div>

 <!-- ── Frame corners ── -->
 <div class="pointer-events-none absolute left-0 top-0 z-20 hidden h-16 w-16 items-center justify-center border-b border-r border-gray-300/70 dark:border-primary-400/35 bg-white dark:bg-gray-950 text-gray-500 dark:text-primary-400/80 select-none lg:flex" aria-hidden="true">
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>
 <div class="pointer-events-none absolute right-0 top-0 z-20 hidden h-16 w-16 items-center justify-center border-b border-l border-gray-300/70 dark:border-primary-400/35 bg-white dark:bg-gray-950 text-gray-500 dark:text-primary-400/80 select-none lg:flex" aria-hidden="true">
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>
 <div class="pointer-events-none absolute bottom-0 left-0 z-20 hidden h-16 w-16 items-center justify-center border-t border-r border-gray-300/70 dark:border-primary-400/35 bg-white dark:bg-gray-950 text-gray-500 dark:text-primary-400/80 select-none lg:flex" aria-hidden="true">
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>
 <div class="pointer-events-none absolute bottom-0 right-0 z-20 hidden h-16 w-16 items-center justify-center border-t border-l border-gray-300/70 dark:border-primary-400/35 bg-white dark:bg-gray-950 text-gray-500 dark:text-primary-400/80 select-none lg:flex" aria-hidden="true">
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>

 <!-- ── Content ───────────────────────────────────────────────── -->
 <div class="relative z-10 mx-auto max-w-7xl py-24 lg:py-32 grid grid-cols-1 lg:grid-cols-2 gap-16 lg:items-center">

     <!-- Left: copy -->
     <div>
         <!-- Badge — glassmorphism blur pill -->
         <div class="mb-8 inline-flex items-center gap-2
                     rounded-md
                     bg-primary-500/10 backdrop-blur-sm
                     border border-primary-400/25 dark:border-primary-400/20
                     px-4 py-2
                     text-xs font-mono uppercase tracking-[0.15em]
                     text-primary-600 dark:text-primary-400">
             <span class="material-icons text-sm leading-none">anchor</span>
             <span>Boat Dealership CRM</span>
         </div>

         <h1 class="mb-6 text-4xl font-black leading-[1.06] tracking-tight text-gray-950 dark:text-white sm:text-5xl lg:text-[3.5rem]">
            Run your dealership,<br />
            <span class="text-primary-600 dark:text-primary-400">from lead to close</span>
        </h1>

        <p class="max-w-lg text-lg leading-relaxed text-gray-600 dark:text-gray-400">
            Track leads, manage inventory, handle service, and close deals — all in one system built specifically for boat dealerships. No spreadsheets. No disconnected tools.
        </p>


        <div class="mt-7 grid grid-cols-2
            rounded-md
            bg-gray-400/8 dark:bg-white/5
            backdrop-blur-sm
            border border-gray-300/60 dark:border-gray-600/40
            overflow-hidden">

    <!-- Item -->
    <div class="flex items-center gap-2 px-4 py-3
                text-sm font-mono text-gray-700 dark:text-gray-300">
        <span class="material-icons text-sm text-primary-500">contact_phone</span>
        Leads & pipeline
    </div>

    <!-- Item -->
    <div class="flex items-center gap-2 px-4 py-3
                border-l border-gray-300/60 dark:border-gray-600/40
                text-sm font-mono text-gray-700 dark:text-gray-300">
        <span class="material-icons text-sm text-primary-500">directions_boat</span>
        Inventory & listings
    </div>

    <!-- Item -->
    <div class="flex items-center gap-2 px-4 py-3
                border-t border-gray-300/60 dark:border-gray-600/40
                text-sm font-mono text-gray-700 dark:text-gray-300">
        <span class="material-icons text-sm text-primary-500">receipt_long</span>
        Deals & invoices
    </div>

    <!-- Item -->
    <div class="flex items-center gap-2 px-4 py-3
                border-t border-l border-gray-300/60 dark:border-gray-600/40
                text-sm font-mono text-gray-700 dark:text-gray-300">
        <span class="material-icons text-sm text-primary-500">build_circle</span>
        Service & work orders
    </div>

</div>

         <!-- CTAs -->
         <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
             <Link
                 :href="route('checkout.plans')"
                 class="inline-flex items-center justify-center gap-2 rounded-md bg-primary-600 px-8 py-4 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-primary-200/60 dark:shadow-primary-900/40 transition hover:bg-primary-500"
             >
                 <span>Start free trial</span>
                 <span class="material-icons text-base leading-none">arrow_forward</span>
             </Link>
             <!-- Secondary CTA — glassmorphism blur button -->
             <Link
                 :href="route('contact')"
                 class="inline-flex items-center justify-center gap-2 rounded-md
                        bg-white/60 dark:bg-white/5
                        backdrop-blur-sm
                        border border-gray-300/70 dark:border-gray-600/50
                        px-8 py-4
                        text-sm font-bold uppercase tracking-wide
                        text-gray-700 dark:text-gray-300
                        transition hover:bg-white/80 dark:hover:bg-white/10 hover:border-primary-400 hover:text-primary-700 dark:hover:border-primary-600 dark:hover:text-white"
             >
                 <span class="material-icons text-base leading-none">call</span>
                 <span>Talk to our team</span>
             </Link>
         </div>
     </div>

     <!-- Right: feature highlights — glassmorphism blur card -->
     <div class="relative">


         <!-- Frosted glass wrapper card -->
         <div class="rounded-md
                     bg-white/50 dark:bg-white/[0.04]
                     backdrop-blur-sm
                     border border-gray-200/80 dark:border-white/10
                     px-6 py-6
                     shadow-sm shadow-gray-200/50 dark:shadow-black/20">

             <div
                 v-for="(item, idx) in heroHighlights"
                 :key="item.title"
                 :class="[ 'relative lg:pl-2', idx > 0 ? 'mt-6 border-t border-gray-200/70 dark:border-white/8 pt-6' : '', ]"
             >
          

                 <div class="flex items-start gap-4">
                     <!-- Icon tile — inner glass tile -->
                     <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md
                                 bg-primary-50/80 dark:bg-primary-950/50
                                 backdrop-blur-sm
                                 border border-primary-200/60 dark:border-primary-700/40">
                         <span class="material-icons text-xl leading-none text-primary-500 dark:text-primary-400">{{ item.icon }}</span>
                     </div>
                     <div>
                         <h2 class="mb-1.5 text-lg font-bold uppercase tracking-wide text-gray-900 dark:text-white">
                             {{ item.title }}
                         </h2>
                         <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
                             {{ item.description }}
                         </p>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </div>

 <!-- ── Bottom fade into next section ─────────────────────────── -->
 <div class="pointer-events-none absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-gray-50 dark:from-gray-950 to-transparent" aria-hidden="true" />
</section>


            <section class="border-b border-gray-200 bg-white px-6 py-20 dark:border-gray-800 dark:bg-gray-900 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-2xl">
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                            From the bulkhead to the dock
                        </p>
                        <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                            Everything your operation needs afloat
                        </h2>
                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            Inventory, customers, service, and money in one flow — built for busy seasons, boat shows, and
                            year-round yard work.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400"
                                    >contact_phone</span
                                >
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Capture & convert leads</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Every inquiry is captured automatically. Track conversations, follow-ups, and deal progress
                                without anything slipping through.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400"
                                    >directions_boat</span
                                >
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Manage inventory</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Track boats, specs, pricing, and availability so your team always knows what’s ready to sell.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400"
                                    >build_circle</span
                                >
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Operations & service</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Manage work orders and service tickets with full visibility across your team.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400"
                                    >trending_up</span
                                >
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Close deals faster</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Move opportunities through your pipeline and convert estimates into invoices seamlessly.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400"
                                    >credit_card</span
                                >
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Get paid seamlessly</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Accept payments, send invoices, and track status in real time with built-in processing.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400"
                                    >bar_chart</span
                                >
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Financial reporting</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Track revenue, performance, and trends with reporting designed for dealerships.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400">anchor</span>
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">Boat shows & events</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                Capture leads at events and follow up efficiently to convert interest into sales.
                            </p>
                        </div>

                        <div
                            class="relative rounded-2xl border border-primary-200 bg-primary-600 p-6 text-white shadow-lg dark:border-primary-700 lg:col-span-2"
                        >
                            <h3 class="text-xl font-semibold">Everything connected. Nothing overlooked.</h3>
                            <p class="mt-3 text-lg leading-relaxed text-primary-100">
                                Leads, inventory, service, sales, and reporting work together in one system built for boat
                                dealerships.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pricing -->
            <section id="pricing" class="border-b border-gray-200 bg-gray-50 px-6 py-20 dark:border-gray-800 dark:bg-gray-950 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                            Pricing
                        </p>
                        <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                            Choose your plan
                        </h2>
                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            Flexible pricing for boat dealerships of all sizes. Start free and scale as you grow.
                        </p>

                        <div class="mt-10 inline-flex items-center rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <button
                                type="button"
                                class="rounded-full px-6 py-2 text-base font-semibold transition"
                                :class="
                                    billingCycle === 'monthly'
                                        ? 'bg-primary-600 text-white shadow-sm'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                                "
                                @click="billingCycle = 'monthly'"
                            >
                                Monthly
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-full px-6 py-2 text-base font-semibold transition"
                                :class="
                                    billingCycle === 'annual'
                                        ? 'bg-primary-600 text-white shadow-sm'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                                "
                                @click="billingCycle = 'annual'"
                            >
                                Annual
                                <span class="rounded-full bg-secondary-600 px-2 py-0.5 text-xs font-semibold text-white">
                                    Save 20%
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-16 grid grid-cols-1 gap-8 md:grid-cols-3">
                        <div
                            v-for="(plan, index) in pricingPlans"
                            :key="index"
                            class="relative rounded-2xl border border-gray-200 bg-white p-8 shadow-lg transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                            :class="plan.popular ? 'ring-2 ring-primary-500 dark:ring-primary-400' : ''"
                        >
                            <div
                                v-if="plan.popular"
                                class="absolute right-0 top-0 rounded-bl-xl rounded-tr-xl bg-primary-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white"
                            >
                                Most popular
                            </div>

                            <div class="mb-6">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ plan.name }}</h3>
                                <p class="mt-2 text-base text-gray-600 dark:text-gray-400">{{ plan.description }}</p>
                            </div>

                            <div class="mb-8">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-5xl font-bold text-gray-900 dark:text-white">
                                        ${{ billingCycle === 'monthly' ? plan.price.monthly : plan.price.annual }}
                                    </span>
                                    <span class="text-gray-600 dark:text-gray-400">
                                        {{ billingCycle === 'monthly' ? '/month' : '/year' }}
                                    </span>
                                </div>
                                <p
                                    v-if="billingCycle === 'annual' && plan.price.annual > 0"
                                    class="mt-2 text-sm text-secondary-600 dark:text-secondary-400"
                                >
                                    Save ${{ plan.price.monthly * 12 - plan.price.annual }}/year
                                </p>
                            </div>

                            <Link
                                :href="route('checkout.plans', { plan: plan.id, billing: billingCycle })"
                                class="mb-8 block w-full rounded-xl px-6 py-3.5 text-center text-base font-semibold transition"
                                :class="
                                    plan.popular
                                        ? 'bg-primary-600 text-white hover:bg-primary-700'
                                        : 'border border-gray-300 bg-gray-900 text-white hover:bg-gray-800 dark:border-gray-600 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100'
                                "
                            >
                                {{ plan.cta }}
                            </Link>

                            <div class="space-y-4">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    What’s included
                                </p>
                                <ul class="space-y-3">
                                    <li v-for="(feature, fIndex) in plan.features" :key="fIndex" class="flex items-start gap-3">
                                        <span class="material-icons mt-0.5 shrink-0 text-lg leading-none text-primary-600 dark:text-primary-400"
                                            >check_circle</span
                                        >
                                        <span class="text-base text-gray-700 dark:text-gray-300">{{ feature }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mt-16 text-center">
                        <p class="text-base text-gray-600 dark:text-gray-400">
                            All plans include a 14-day free trial. No credit card required.
                        </p>
                        <Link
                            :href="route('checkout.plans')"
                            class="mt-3 inline-block text-base font-semibold text-primary-600 hover:underline dark:text-primary-400"
                        >
                            View all plans →
                        </Link>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="border-b border-gray-200 bg-white px-6 py-20 dark:border-gray-800 dark:bg-gray-900 sm:px-12 lg:px-24">
                <div class="mx-auto w-full max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">FAQ</p>
                        <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                            Frequently asked questions
                        </h2>
                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            Everything you need to know about getting started with Helmful.
                        </p>
                    </div>

                    <div
                        v-if="faqs && faqs.length > 0"
                        class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="(faq, index) in faqs"
                            :key="faq.id || index"
                            class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-6 shadow-lg transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div
                                class="mb-4 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/40"
                            >
                                <span class="material-icons text-xl leading-none text-primary-600 dark:text-primary-400"
                                    >help_outline</span
                                >
                            </div>
                            <h3 class="text-lg font-semibold leading-snug text-gray-900 dark:text-white">
                                {{ faq.question }}
                            </h3>
                            <p class="mt-3 flex-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                {{ faq.answer }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="mt-12 rounded-2xl border border-dashed border-gray-300 bg-gray-50 py-12 text-center dark:border-gray-600 dark:bg-gray-800/30">
                        <div
                            class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-900/40"
                        >
                            <span class="material-icons text-3xl leading-none text-primary-500 dark:text-primary-400"
                                >help_outline</span
                            >
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">No FAQs yet</h3>
                        <p class="mt-2 text-base text-gray-600 dark:text-gray-400">Check back soon — we’re adding common questions.</p>
                    </div>

                    <div
                        v-if="faqs && faqs.length > 0"
                        class="mt-12 rounded-2xl border border-gray-200 bg-primary-50 p-8 text-center dark:border-gray-700 dark:bg-primary-950/30"
                    >
                        <h3 class="text-2xl font-bold text-gray-950 dark:text-white">Still have questions?</h3>
                        <p class="mt-2 text-base leading-relaxed text-gray-600 dark:text-gray-400">
                            Can’t find what you need? Our team is happy to help.
                        </p>
                        <div class="mt-6 flex flex-col justify-center gap-4 sm:flex-row">
                            <Link
                                :href="route('contact')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-base font-semibold text-white transition hover:bg-primary-700"
                            >
                                <span class="material-icons text-xl leading-none">mail_outline</span>
                                Contact us
                            </Link>
                            <Link
                                :href="route('faq')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-6 py-3 text-base font-semibold text-gray-900 transition hover:border-primary-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:border-primary-500"
                            >
                                <span class="material-icons text-xl leading-none">quiz</span>
                                Full FAQ
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Blog: three latest published + featured posts -->
            <section v-if="blogPosts.length > 0" id="blog" class="border-b border-gray-200 bg-gray-50 px-6 py-20 dark:border-gray-800 dark:bg-gray-950 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                            From our blog
                        </p>
                        <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                            Featured posts
                        </h2>
                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            The latest three published, featured articles—sales, ops, and life on the water.
                        </p>
                    </div>

                    <div class="mt-16 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="(post, index) in blogPosts"
                            :key="post.id"
                            class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="relative h-48 overflow-hidden">
                                <img
                                    :src="post.image"
                                    :alt="post.title"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                />
                                <div class="absolute left-4 top-4 flex flex-wrap gap-2">
                                    <span
                                        class="inline-block rounded-full bg-secondary-600 px-3 py-1 text-xs font-semibold text-white"
                                    >
                                        Featured
                                    </span>
                                    <span
                                        class="inline-block rounded-full bg-primary-600 px-3 py-1 text-xs font-semibold text-white"
                                    >
                                        {{ post.category }}
                                    </span>
                                </div>
                            </div>

                            <div class="p-6">
                                <div class="mb-3 flex flex-wrap items-center gap-4 text-base text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-icons text-lg leading-none">calendar_today</span>
                                        {{ post.date }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-icons text-lg leading-none">schedule</span>
                                        {{ post.readTime }}
                                    </span>
                                </div>

                                <h3
                                    class="mb-3 line-clamp-2 text-xl font-bold text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400"
                                >
                                    {{ post.title }}
                                </h3>

                                <p class="mb-4 line-clamp-3 text-base leading-relaxed text-gray-600 dark:text-gray-400">
                                    {{ post.excerpt }}
                                </p>

                                <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-700">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300"
                                        >
                                            {{ post.author.charAt(0) }}
                                        </div>
                                        <span class="text-base font-medium text-gray-700 dark:text-gray-300">{{ post.author }}</span>
                                    </div>
                                    <a
                                        :href="post.link"
                                        class="inline-flex items-center gap-1 text-base font-semibold text-primary-600 transition group-hover:gap-2 dark:text-primary-400"
                                    >
                                        Read more
                                        <span class="material-icons text-lg leading-none">arrow_forward</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-12 text-center">
                        <Link
                            :href="route('blog')"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-8 py-3.5 text-base font-semibold text-white shadow-lg transition hover:bg-primary-700"
                        >
                            View all articles
                            <span class="material-icons text-xl leading-none">arrow_forward</span>
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Wave animation — back wave drifts right, front wave drifts left */
@keyframes waveSlideRight {
    from { transform: translateX(0); }
    to   { transform: translateX(50px); }
}
@keyframes waveSlideLeft {
    from { transform: translateX(0); }
    to   { transform: translateX(-50px); }
}

.wave-back {
    animation: waveSlideRight 4s ease-in-out infinite alternate;
    transform-origin: center;
}

.wave-front {
    animation: waveSlideLeft 4s ease-in-out infinite alternate;
    transform-origin: center;
}

.wave-fill {
    animation: waveSlideLeft 3s ease-in-out infinite alternate;
    transform-origin: center;
}
</style>

