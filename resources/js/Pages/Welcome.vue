<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PlanAllTiersIncluded from '@/Components/Marketing/PlanAllTiersIncluded.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { planFeatureTitles } from '@/composables/usePlanFeatureTitles';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { usePwaLinks } from '@/composables/usePwaLinks';

const page = usePage();
const { pwaForLinks, externalLinkTarget, externalLinkRel } = usePwaLinks();

const billingCycle = ref('monthly');
const blogPlaceholderImage = computed(() => page.props.app?.blogPlaceholderImage ?? '');

const authUser = computed(() => page.props.auth?.user ?? null);
const isLoggedIn = computed(() => Boolean(authUser.value));
const workspaceNav = computed(() => page.props.workspace_nav ?? []);
const hasActiveWorkspace = computed(() => workspaceNav.value.length > 0);
const singleWorkspace = computed(() =>
    workspaceNav.value.length === 1 ? workspaceNav.value[0] : null,
);

const welcomeFirstName = computed(() => {
    const user = authUser.value;
    if (!user) {
        return '';
    }
    const first = String(user.first_name ?? '').trim();
    if (first) {
        return first;
    }
    const name = String(user.name ?? '').trim();
    if (name) {
        return name.split(/\s+/)[0];
    }

    return 'there';
});

const loggedInHeroHighlights = [
    {
        icon: 'rocket_launch',
        title: 'Jump back in',
        description: 'Open your dealership workspace and pick up leads, service, and deals where you left off.',
    },
    {
        icon: 'groups',
        title: 'Your team & seats',
        description: 'Invite staff, adjust roles, and keep everyone working from the same system.',
    },
    {
        icon: 'settings',
        title: 'Billing & workspaces',
        description: 'Manage subscriptions, switch plans, and open any workspace you belong to.',
    },
];

const displayedHeroHighlights = computed(() =>
    isLoggedIn.value ? loggedInHeroHighlights : props.heroHighlights,
);

const getTenantUrl = (domain) => {
    if (!domain) {
        return null;
    }
    if (typeof window === 'undefined') {
        return `https://${domain}`;
    }

    return `${window.location.protocol}//${domain}`;
};

const openAppHref = computed(() => {
    if (singleWorkspace.value?.domain) {
        return getTenantUrl(singleWorkspace.value.domain);
    }
    if (hasActiveWorkspace.value) {
        return route('dashboard');
    }

    return null;
});

const openAppIsExternal = computed(() => Boolean(singleWorkspace.value?.domain));

const pricingFeaturesUrl = `${route('checkout.plans')}#plan-features`;

const postCoverImage = (post) => post.image || blogPlaceholderImage.value;

const props = defineProps({
    canLogin:     { type: Boolean },
    canRegister:  { type: Boolean },
    blogPosts:    { type: Array, default: () => [] },
    pricingPlans: { type: Array, default: () => [] },
    allTiers: {
        type: Object,
        default: () => ({ title: 'All tiers include', subtitle: '', features: [] }),
    },
    seatPolicy:   { type: Object, default: () => ({ included: 5 }) },
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

const planSeatsIncluded = (plan) => plan.seatLimit ?? props.seatPolicy?.included ?? 5;

const pricingGridClass = computed(() => {
    const count = props.pricingPlans?.length ?? 0;
    if (count <= 1) {
        return 'mt-16 grid grid-cols-1 gap-8 max-w-md mx-auto';
    }
    if (count === 2) {
        return 'mt-16 grid grid-cols-1 gap-8 md:grid-cols-2 max-w-4xl mx-auto';
    }
    return 'mt-16 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3';
});

// ── Hero rulers ──
// const DEPTH_STEP_FT = 10;
// const STRIP_HALF_STEPS = 80;
// const heroStripTicksFt = Array.from(
//     { length: STRIP_HALF_STEPS * 2 + 1 },
//     (_, i) => (i - STRIP_HALF_STEPS) * DEPTH_STEP_FT,
// );
// const heroVerticalStripTicksFt = [...heroStripTicksFt].reverse();

// const HORIZ_HALF_STEPS = 6;
// const heroHorizontalTickFt = Array.from(
//     { length: HORIZ_HALF_STEPS * 2 + 1 },
//     (_, i) => (i - HORIZ_HALF_STEPS) * DEPTH_STEP_FT,
// );

const scrollY = ref(0);
const VERTICAL_RULER_SCROLL_FACTOR = 0.24;
/** 0′ on the vertical rulers (and the sea line) sit this far down: 0 = top, 1 = bottom. */
const HERO_AXIS_ZERO_FRACTION = 0.70;

/** Pixels the hero wave (inner) and vertical rulers are shifted for scroll parallax. */
const waveParallaxPx = computed(() => scrollY.value * VERTICAL_RULER_SCROLL_FACTOR);


// const leftRulerViewport = ref(null);
// const leftRulerStrip = ref(null);
// const verticalAlignPx = ref(0);


function handleScroll() {
    scrollY.value = window.scrollY;
}

function measureHeroRulers() {
    // nextTick(() => {
    //     requestAnimationFrame(() => {
    //         if (typeof window !== 'undefined' && !window.matchMedia('(min-width: 768px)').matches) {
    //             return;
    //         }
    //         const vVp = leftRulerViewport.value;
    //         const vStrip = leftRulerStrip.value;
    //         if (vVp && vStrip) {
    //             const z = vStrip.querySelector('.hero-axis-zero');
    //             if (z) {
    //                 const vpr = vVp.getBoundingClientRect();
    //                 const zr  = z.getBoundingClientRect();
    //                 // Align strip so 0′ is ~3/4 down the ruler viewport (matches the wave)
    //                 const vc = vpr.top + vpr.height * HERO_AXIS_ZERO_FRACTION;
    //                 const zc = zr.top + zr.height / 2;
    //                 verticalAlignPx.value += vc - zc;

    //             }
    //         }
    //     });
    // });
}


onMounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    const hasPwaCookie = document.cookie.split('; ').some((row) => row.startsWith('pwa_mode='));
    const url = new URL(window.location.href);
    if (! hasPwaCookie && url.searchParams.get('pwa') !== '1') {
        const standalone =
            window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        if (standalone) {
            router.get(route('home', { pwa: 1 }));
            return;
        }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    // window.addEventListener('resize', measureHeroRulers);
    handleScroll();
    // measureHeroRulers();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    // window.removeEventListener('resize', measureHeroRulers);
});
</script>

<template>
    <Head title="Welcome" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <!-- ── HERO ─────────────────────────────────────────────────────────── -->
    <section class="relative flex  flex-col justify-center overflow-hidden bg-white dark:bg-gray-950 px-6 sm:px-16 lg:px-24 border-b border-gray-300/70 dark:border-primary-400/35">
 
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
         height: `calc(200% + ${waveParallaxPx}px)`,
         transform: `translateY(-${waveParallaxPx}px)`,
     }"
 >
     <div class="relative will-change-transform">
         <svg class="h-full w-full" viewBox="0 0 1440 1000" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
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
             <path class="wave-back" fill="none" stroke="rgb(125 211 252 / 0.25)" stroke-width="2"
    d="M-200,45 C80,15 260,70 540,45 C720,20 980,75 1200,45 C1340,25 1500,60 1640,45" />

<path class="wave-front" fill="none" stroke="rgb(56 189 248 / 0.6)" stroke-width="2.5"
    d="M-200,42 C120,65 320,15 600,42 C800,68 1050,18 1250,42 C1400,62 1530,28 1640,42" />

<path class="wave-fill dark:hidden" fill="url(#waveFillLight)"
    d="M-200,42 C120,65 320,15 600,42 C800,68 1050,18 1250,42 C1400,62 1530,28 1640,42 L1640,99999 L-200,99999 Z" />

<path class="wave-fill hidden dark:block" fill="url(#waveFillDark)"
    d="M-200,42 C120,65 320,15 600,42 C800,68 1050,18 1250,42 C1400,62 1530,28 1640,42 L1640,99999 L-200,99999 Z" />
            </svg>
 
     </div>
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
             <span class="material-icons text-sm leading-none">{{ isLoggedIn ? 'waving_hand' : 'anchor' }}</span>
             <span>{{ isLoggedIn ? 'Welcome back' : 'Boat Dealership Platform' }}</span>
         </div>

         <h1
             v-if="isLoggedIn"
             class="mb-6 font-black leading-[1.06] tracking-tight text-gray-950 dark:text-white text-3xl sm:text-5xl lg:text-6xl"
         >
             Welcome back,<br />
             <span class="text-primary-600 dark:text-primary-400">{{ welcomeFirstName }}</span>
         </h1>
         <h1
             v-else
             class="mb-6 font-black leading-[1.06] tracking-tight text-gray-950 dark:text-white text-3xl sm:text-5xl lg:text-6xl"
         >
             Run your dealership,<br />
             <span class="text-primary-600 dark:text-primary-400">from lead to close</span>
         </h1>

         <p v-if="isLoggedIn" class="max-w-lg text-lg lg:text-xl leading-relaxed text-gray-600 dark:text-gray-400">
             <template v-if="hasActiveWorkspace">
                 Your Helmful workspace{{ singleWorkspace ? ` (${singleWorkspace.name})` : 's' }} {{ singleWorkspace ? 'is' : 'are' }} ready. Open the app to run the floor, or manage billing and team access from your account.
             </template>
             <template v-else>
                 You are signed in. Head to your account dashboard to create a workspace, accept a team invitation, or choose a plan to get started.
             </template>
         </p>
         <p v-else class="max-w-lg text-lg lg:text-xl leading-relaxed text-gray-600 dark:text-gray-400">
             Track leads, manage inventory, handle service, and close deals — all in one system built specifically for boat dealerships. No spreadsheets. No disconnected tools.
         </p>


        <div
            v-if="!isLoggedIn"
            class="mt-7 grid grid-cols-2
            rounded-md
            bg-gray-400/8 dark:bg-white/5
            backdrop-blur-sm
            border border-gray-300/60 dark:border-gray-600/40
            overflow-hidden">

            <!-- Item -->
            <div class="flex items-center gap-2 px-4 py-3
                        text-sm lg:text-md font-mono text-gray-700 dark:text-gray-300">
                <span class="material-icons text-sm lg:text-md text-primary-500">contact_phone</span>
                Leads & pipeline
            </div>

            <!-- Item -->
            <div class="flex items-center gap-2 px-4 py-3
                        border-l border-gray-300/60 dark:border-gray-600/40
                        text-sm lg:text-md font-mono text-gray-700 dark:text-gray-300">
                <span class="material-icons text-sm lg:text-md text-primary-500">directions_boat</span>
                Inventory & listings
            </div>

            <!-- Item -->
            <div class="flex items-center gap-2 px-4 py-3
                        border-t border-gray-300/60 dark:border-gray-600/40
                        text-sm lg:text-md font-mono text-gray-700 dark:text-gray-300">
                <span class="material-icons text-sm lg:text-md text-primary-500">receipt_long</span>
                Deals & invoices
            </div>

            <!-- Item -->
            <div class="flex items-center gap-2 px-4 py-3
                        border-t border-l border-gray-300/60 dark:border-gray-600/40
                        text-sm lg:text-md font-mono text-gray-700 dark:text-gray-300">
                <span class="material-icons text-sm lg:text-md text-primary-500">build_circle</span>
                Service & work orders
            </div>

        </div>

         <!-- CTAs -->
         <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
             <template v-if="isLoggedIn">
                 <a
                     v-if="hasActiveWorkspace && openAppHref && openAppIsExternal"
                     :href="openAppHref"
                     :target="externalLinkTarget"
                     :rel="externalLinkRel"
                     class="inline-flex items-center justify-center gap-2 rounded-md bg-primary-600 px-8 py-4 text-sm lg:text-md font-bold uppercase tracking-wide text-white shadow-lg shadow-primary-200/60 dark:shadow-primary-900/40 transition hover:bg-primary-500"
                 >
                     <span class="material-icons text-base leading-none">rocket_launch</span>
                     <span>Open app</span>
                     <span v-if="!pwaForLinks" class="material-icons text-base leading-none opacity-80">open_in_new</span>
                 </a>
                 <Link
                     v-else-if="hasActiveWorkspace && openAppHref"
                     :href="openAppHref"
                     class="inline-flex items-center justify-center gap-2 rounded-md bg-primary-600 px-8 py-4 text-sm lg:text-md font-bold uppercase tracking-wide text-white shadow-lg shadow-primary-200/60 dark:shadow-primary-900/40 transition hover:bg-primary-500"
                 >
                     <span class="material-icons text-base leading-none">rocket_launch</span>
                     <span>Open app</span>
                 </Link>
                 <Link
                     :href="route('dashboard')"
                     class="inline-flex items-center justify-center gap-2 rounded-md
                            bg-white/60 dark:bg-white/5
                            backdrop-blur-sm
                            border border-gray-300/70 dark:border-gray-600/50
                            px-8 py-4
                            text-sm lg:text-md font-bold uppercase tracking-wide
                            text-gray-700 dark:text-gray-300
                            transition hover:bg-white/80 dark:hover:bg-white/10 hover:border-primary-400 hover:text-primary-700 dark:hover:border-primary-600 dark:hover:text-white"
                 >
                     <span class="material-icons text-base leading-none">manage_accounts</span>
                     <span>Manage account</span>
                 </Link>
             </template>
             <template v-else>
                 <Link
                     :href="route('checkout.plans')"
                     class="inline-flex items-center justify-center gap-2 rounded-md bg-primary-600 px-8 py-4 text-sm lg:text-md font-bold uppercase tracking-wide text-white shadow-lg shadow-primary-200/60 dark:shadow-primary-900/40 transition hover:bg-primary-500"
                 >
                     <span>Start free trial</span>
                     <span class="material-icons text-base leading-none">arrow_forward</span>
                 </Link>
                 <Link
                     :href="route('contact')"
                     class="inline-flex items-center justify-center gap-2 rounded-md
                            bg-white/60 dark:bg-white/5
                            backdrop-blur-sm
                            border border-gray-300/70 dark:border-gray-600/50
                            px-8 py-4
                            text-sm lg:text-md font-bold uppercase tracking-wide
                            text-gray-700 dark:text-gray-300
                            transition hover:bg-white/80 dark:hover:bg-white/10 hover:border-primary-400 hover:text-primary-700 dark:hover:border-primary-600 dark:hover:text-white"
                 >
                     <span class="material-icons text-base leading-none">call</span>
                     <span>Talk to our team</span>
                 </Link>
             </template>
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
                 v-for="(item, idx) in displayedHeroHighlights"
                 :key="item.title"
                 :class="[ 'relative lg:pl-2', idx > 0 ? 'mt-6 border-t border-gray-200/70 dark:border-white/8 pt-6' : '', ]"
             >
          

                 <div class="flex items-start gap-4">
                     <!-- Icon tile — inner glass tile -->
                     <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md
                                 bg-primary-50/80 dark:bg-primary-950/50
                                 backdrop-blur-sm
                                 border border-primary-200/60 dark:border-primary-700/40">
                         <span class="material-icons text-lg lg:text-xl leading-none text-primary-500 dark:text-primary-400">{{ item.icon }}</span>
                     </div>
                     <div>
                         <h2 class="mb-1.5 text-md lg:text-xl font-bold uppercase tracking-wide text-gray-900 dark:text-white">
                             {{ item.title }}
                         </h2>
                         <p class="text-md lg:text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                             {{ item.description }}
                         </p>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </div>

 

 <!-- ── Bottom fade into next section ─────────────────────────── -->
 <!-- <div class="pointer-events-none absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-gray-50 dark:from-gray-950 to-transparent" aria-hidden="true" /> -->
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
                    </div>

                    <div class="mt-6">
                        <PlanAllTiersIncluded
                            :title="allTiers.title"
                            :subtitle="allTiers.subtitle"
                            :features="allTiers.features"
                            section-id="plan-features"
                            embedded
                        />
                    </div>

                    <div class="mt-10 flex justify-center">
                        <div class="inline-flex items-center rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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

                    <div :class="pricingGridClass">
                        <div
                            v-for="(plan, index) in pricingPlans"
                            :key="index"
                            class="relative rounded-2xl border border-gray-200 bg-white p-8 shadow-lg transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                            :class="plan.popular ? 'ring-2 ring-primary-500 dark:ring-primary-400' : ''"
                        >
                            <div
                                v-if="plan.popular"
                                class="absolute right-[-1px] top-[-1px] rounded-bl-xl rounded-tr-xl bg-primary-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white"
                            >
                                Most popular
                            </div>

                            <div class="mb-6">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ plan.name }}</h3>
                                <p class="mt-2 text-base text-gray-600 dark:text-gray-400">{{ plan.description }}</p>
                            </div>

                            <div class="mb-8">
                                <template v-if="plan.coming_soon">
                                    <p class="text-4xl font-bold tracking-tight text-gray-700 dark:text-gray-200">
                                        Coming soon
                                    </p>
                                    <p class="mt-2 text-base text-gray-500 dark:text-gray-400">
                                        This plan is not available for purchase yet.
                                    </p>
                                </template>
                                <template v-else>
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
                                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{
                                            planSeatsIncluded(plan)
                                        }}</span>
                                        {{ planSeatsIncluded(plan) === 1 ? 'seat' : 'seats' }} included
                                    </p>
                                </template>
                            </div>

                            <Link
                                v-if="!plan.coming_soon"
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
                            <div
                                v-else
                                class="mb-8 block w-full cursor-default rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-3.5 text-center text-base font-semibold text-gray-500 dark:border-gray-600 dark:bg-gray-800/80 dark:text-gray-400"
                            >
                                Coming soon
                            </div>

                            <div class="space-y-4">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    What’s included
                                </p>
                                <ul class="space-y-3">
                                    <li
                                        v-for="(title, fIndex) in planFeatureTitles(plan.features)"
                                        :key="fIndex"
                                        class="flex items-start gap-3"
                                    >
                                        <span class="material-icons mt-0.5 shrink-0 text-lg leading-none text-primary-600 dark:text-primary-400"
                                            >check_circle</span
                                        >
                                        <span class="text-base text-gray-700 dark:text-gray-300">{{ title }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div
                    class="flex justify-center  mt-16"
                    >

                    <div
                    class=" flex items-start justify-center gap-3 rounded-xl border border-primary-200/60 bg-primary-50/80 px-5 py-4 text-center dark:border-primary-800/60 dark:bg-primary-950/40 sm:px-6"
                    >
                        <span class="material-icons mt-0.5 hidden shrink-0 text-primary-600 dark:text-primary-400 sm:inline">group</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300 sm:text-base">
                            <span class="font-semibold text-gray-900 dark:text-white"
                                >$15/month</span
                            >
                            per additional seat after included seats.
                        </p>
                    </div>
                    </div>

                    <div class="mt-10 text-center">
                        <p class="text-base text-gray-600 dark:text-gray-400 font-bold">
                            All plans include a 14-day free trial. No credit card required.
                        </p>
                        <div class="mt-4 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-6">
                            <Link
                                :href="route('checkout.plans')"
                                class="text-base font-semibold text-primary-600 hover:underline dark:text-primary-400"
                            >
                                View all plans →
                            </Link>
                            <Link
                                :href="pricingFeaturesUrl"
                                class="text-base font-semibold text-gray-700 hover:text-primary-600 hover:underline dark:text-gray-300 dark:hover:text-primary-400"
                            >
                                See full feature list →
                            </Link>
                        </div>
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
                        <Link
                            v-for="post in blogPosts"
                            :key="post.id"
                            :href="post.link || route('blogPostShow', post.slug)"
                            class="group block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="relative h-48 overflow-hidden">
                                <img
                                    :src="postCoverImage(post)"
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
                                        class="inline-block rounded-full bg-black/60 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-sm"
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
                                    <!-- <span class="inline-flex items-center gap-1">
                                        <span class="material-icons text-lg leading-none">schedule</span>
                                        {{ post.readTime }}
                                    </span> -->
                                </div>

                                <h3
                                    class="mb-3 line-clamp-2 text-xl font-bold text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400"
                                >
                                    {{ post.title }}
                                </h3>

                                <p class="mb-4 line-clamp-3 text-base leading-relaxed text-gray-600 dark:text-gray-400">
                                    {{ post.excerpt }}
                                </p>

                                <!-- <div class="flex items-center justify-end border-t border-gray-100 pt-4 dark:border-gray-700"> -->

                                    <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                                        <span class="flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-400">
                                            <span class="material-icons text-lg leading-none">schedule</span>
                                            {{ post.readTime }}
                                        </span>

                                    <span
                                        class="inline-flex items-center gap-1 text-base font-semibold text-primary-600 transition group-hover:gap-2 dark:text-primary-400"
                                    >
                                        Read more
                                        <span class="material-icons text-lg leading-none">arrow_forward</span>
                                    </span>
                                </div>
                            </div>
                        </Link>
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

            <FeaturePageCta
                badge="Get started"
                badge-icon="rocket_launch"
                title="Ready to see it in action?"
                description="Talk with our team about how Helmful fits your dealership or start with pricing."
                primary-label="Contact us"
                primary-route="contact"
                secondary-label="View pricing"
                secondary-route="checkout.plans"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
/* Wave animation — back wave drifts right, front wave drifts left */
@keyframes waveSlideRight {
    from { transform: translateX(0); }
    to   { transform: translateX(150px); }
}
@keyframes waveSlideLeft {
    from { transform: translateX(0); }
    to   { transform: translateX(-150px); }
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
@keyframes wave-drift {
    0%   { transform: translateX(0%); }
    100% { transform: translateX(33.333%); } /* slides one full "viewport" worth */
}
</style>

