<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
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
 
// ── Hero rulers: vertical = positive at top → negative below (depth down); horizontal = fixed ──
const DEPTH_STEP_FT = 10;
/** Extra range so vertical strips don’t run out on tall pages. */
const STRIP_HALF_STEPS = 80;
const heroStripTicksFt = Array.from(
    { length: STRIP_HALF_STEPS * 2 + 1 },
    (_, i) => (i - STRIP_HALF_STEPS) * DEPTH_STEP_FT,
);
/** Vertical tape: high readings at top, sea level mid-strip, negative below (descending). */
const heroVerticalStripTicksFt = [...heroStripTicksFt].reverse();

/** Bottom tape: −60′ … +60′ by 10′ steps, full width via flex-1. */
const HORIZ_HALF_STEPS = 6;
const heroHorizontalTickFt = Array.from(
    { length: HORIZ_HALF_STEPS * 2 + 1 },
    (_, i) => (i - HORIZ_HALF_STEPS) * DEPTH_STEP_FT,
);

const scrollY = ref(0);
/** Subtle vertical tape motion vs page scroll (depth cue). */
const VERTICAL_RULER_SCROLL_FACTOR = 0.24;

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
                    const zr = z.getBoundingClientRect();
                    const vc = vpr.top + vpr.height / 2;
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
    <section class="relative flex min-h-[80vh] flex-col justify-center bg-gray-950">
            <!-- ── Background: coordinate grid ───────────────────────────── -->
            <div class="pointer-events-none absolute inset-0 hidden select-none opacity-15 md:block" aria-hidden="true">
                <div
                    v-for="n in 9"
                    :key="'v' + n"
                    class="absolute top-0 bottom-0 w-px bg-primary-400/20"
                    :style="{ left: `${(n - 1) * 12.5}%` }"
                />
                <div
                    v-for="n in 7"
                    :key="'h' + n"
                    class="absolute left-0 right-0 h-px bg-primary-400/20"
                    :style="{ top: `${(n - 1) * 16.666}%` }"
                />
                <div class="absolute top-0 bottom-0 left-1/4 w-px bg-primary-400/35" />
                <div class="absolute top-0 bottom-0 left-3/4 w-px bg-primary-400/25" />
                <div class="absolute left-0 right-0 top-1/3 h-px bg-primary-400/25" />
            </div>

        <!-- ── Left ruler: 0′ centered at rest; strip moves up as you scroll (deeper) ── -->
        <div
            ref="leftRulerViewport"
            class="pointer-events-none absolute bottom-16 left-0 top-16 z-10 hidden w-16 overflow-hidden border-r border-primary-400/35 select-none md:block"
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
                    class="flex w-full shrink-0 justify-center text-[11px] font-mono tabular-nums text-primary-400/50"
                >
                    <span class="leading-none" :class="{ 'hero-axis-zero': ft === 0 }">{{ ft }}′</span>
                </div>
            </div>
        </div>

        <!-- ── Right ruler (mirror of left) ── -->
        <div
            class="pointer-events-none absolute bottom-16 right-0 top-16 z-10 hidden w-16 overflow-hidden border-l border-primary-400/35 select-none md:block"
            aria-hidden="true"
        >
            <div
                class="flex w-full flex-col items-center gap-12 py-24 will-change-transform"
                :style="{ transform: `translateY(${verticalAlignPx - scrollY * VERTICAL_RULER_SCROLL_FACTOR}px)` }"
            >
                <div
                    v-for="ft in heroVerticalStripTicksFt"
                    :key="`R-${ft}`"
                    class="flex w-full shrink-0 justify-center text-[11px] font-mono tabular-nums text-primary-400/50"
                >
                    <span class="leading-none" :class="{ 'hero-axis-zero': ft === 0 }">{{ ft }}′</span>
                </div>
            </div>
        </div>

 <!-- ── Bottom tape: same height as vertical ruler width (w-16), between corner squares ── -->
 <div
     class="pointer-events-none absolute bottom-0 left-16 right-16 z-10 hidden h-16 overflow-hidden border-t border-primary-400/35 select-none md:block"
     aria-hidden="true"
 >
     <div class="flex h-full w-full flex-row items-center px-1">
         <div
             v-for="ft in heroHorizontalTickFt"
             :key="`H-${ft}`"
             class="flex min-h-0 min-w-0 flex-1 flex-col items-center justify-center gap-1 text-[11px] font-mono tabular-nums text-primary-400/50"
         >
             <div class="h-2 w-px shrink-0 bg-primary-400/50" />
             <span class="text-center leading-none">{{ ft }}′</span>
         </div>
     </div>
 </div>

 <!-- ── Frame corners: w-16 h-16 squares (match ruler module), compass flush in corners ── -->
 <div
     class="pointer-events-none absolute left-0 top-0 z-20 hidden h-16 w-16 items-center justify-center border-b border-r border-primary-400/35 bg-gray-950 text-primary-400/80 select-none md:flex"
     aria-hidden="true"
 >
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>
 <div
     class="pointer-events-none absolute right-0 top-0 z-20 hidden h-16 w-16 items-center justify-center border-b border-l border-primary-400/35 bg-gray-950 text-primary-400/80 select-none md:flex"
     aria-hidden="true"
 >
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>
 <div
     class="pointer-events-none absolute bottom-0 left-0 z-20 hidden h-16 w-16 items-center justify-center border-t border-r border-primary-400/35 bg-gray-950 text-primary-400/80 select-none md:flex"
     aria-hidden="true"
 >
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>
 <div
     class="pointer-events-none absolute bottom-0 right-0 z-20 hidden h-16 w-16 items-center justify-center border-t border-l border-primary-400/35 bg-gray-950 text-primary-400/80 select-none md:flex"
     aria-hidden="true"
 >
     <span class="material-icons text-[28px] leading-none">explore</span>
 </div>

 <!-- ── Crosshair center mark ─────────────────────────────────── -->
 <!-- <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
     <div class="relative w-4 h-4 opacity-20">
         <div class="absolute top-1/2 left-0 right-0 h-px bg-primary-400" />
         <div class="absolute left-1/2 top-0 bottom-0 w-px bg-primary-400" />
         <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full border border-primary-400" />
     </div>
 </div> -->

 <!-- ── Content ───────────────────────────────────────────────── -->
 <div class="relative z-10 mx-auto  px-6 sm:px-16 lg:px-24 py-24 lg:py-32 grid grid-cols-1 lg:grid-cols-2 gap-16 lg:items-center">

     <!-- Left: copy -->
     <div>
         <!-- Badge -->
         <div
             class="mb-8 inline-flex items-center gap-2 rounded-sm border border-primary-700/60 bg-primary-950/80 px-4 py-2 text-xs font-mono uppercase tracking-[0.15em] text-primary-400"
         >
             <span class="material-icons text-sm leading-none">anchor</span>
             <span>Marina &amp; Dealer Platform</span>
         </div>

         <h1 class="mb-6 text-4xl font-black leading-[1.06] tracking-tight text-white sm:text-5xl lg:text-[3.5rem]">
             Your marina,<br />
             <span class="text-primary-400">one steady helm</span>
         </h1>

         <p class="max-w-lg text-lg leading-relaxed text-gray-400">
             Docks, fleet, service, and sales in one workspace — see what's on the lot,
             what's in the yard, and what's closing, without chasing spreadsheets.
         </p>

         <!-- Chip tags -->
         <div class="mt-7 flex flex-wrap gap-2">
             <span
                 class="inline-flex items-center gap-1.5 rounded-sm border border-gray-700 bg-gray-900 px-3 py-1.5 text-sm font-mono text-gray-300"
             >
                 <span class="material-icons text-sm text-primary-500">directions_boat</span>
                 Slips &amp; listings
             </span>
             <span
                 class="inline-flex items-center gap-1.5 rounded-sm border border-gray-700 bg-gray-900 px-3 py-1.5 text-sm font-mono text-gray-300"
             >
                 <span class="material-icons text-sm text-primary-500">build</span>
                 Yard &amp; rigging
             </span>
             <span
                 class="inline-flex items-center gap-1.5 rounded-sm border border-gray-700 bg-gray-900 px-3 py-1.5 text-sm font-mono text-gray-300"
             >
                 <span class="material-icons text-sm text-primary-500">event</span>
                 Season &amp; events
             </span>
         </div>

         <!-- CTAs -->
         <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
             <Link
                 :href="route('checkout.plans')"
                 class="inline-flex items-center justify-center gap-2 rounded-sm bg-primary-600 px-8 py-4 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-primary-900/40 transition hover:bg-primary-500"
             >
                 <span>Start free trial</span>
                 <span class="material-icons text-base leading-none">arrow_forward</span>
             </Link>
             <Link
                 :href="route('contact')"
                 class="inline-flex items-center justify-center gap-2 rounded-sm border border-gray-700 bg-gray-900 px-8 py-4 text-sm font-bold uppercase tracking-wide text-gray-300 transition hover:border-primary-600 hover:text-white"
             >
                 <span class="material-icons text-base leading-none">call</span>
                 <span>Talk to our team</span>
             </Link>
         </div>
     </div>

     <!-- Right: feature highlights -->
     <div class="relative">
         <!-- Subtle vertical accent line along left edge of card -->
         <div class="absolute -left-6 top-4 bottom-4 w-px bg-primary-600/40" />

         <div
             v-for="(item, idx) in heroHighlights"
             :key="item.title"
             :class="[
                 'relative pl-6',
                 idx > 0 ? 'mt-8 border-t border-gray-800 pt-8' : '',
             ]"
         >
             <!-- Dot on the left accent line -->
             <div
                 class="absolute -left-6 top-0 h-3 w-3 -translate-x-1/2 rounded-full border-2 border-gray-950 bg-primary-600 shadow-[0_0_8px_2px_rgba(var(--color-primary-500),0.35)]"
                 :class="idx === 0 ? 'mt-0' : 'mt-8'"
             />

             <div class="flex items-start gap-4">
                 <div
                     class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm border border-gray-700 bg-gray-900"
                 >
                     <span class="material-icons text-xl leading-none text-primary-400">{{ item.icon }}</span>
                 </div>
                 <div>
                     <h2 class="mb-1.5 text-lg font-bold uppercase tracking-wide text-white">
                         {{ item.title }}
                     </h2>
                     <p class="text-lg leading-relaxed text-gray-400">
                         {{ item.description }}
                     </p>
                 </div>
             </div>
         </div>
     </div>
 </div>

 <!-- ── Bottom fade into next section ─────────────────────────── -->
 <div
     class="pointer-events-none absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-gray-950 to-transparent"
     aria-hidden="true"
 />
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
