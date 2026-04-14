<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, required: false },
    companyName: { type: String, default: 'Acme Corp' },
    companyLogo: { type: String, default: null },
});

const page = usePage();

const customer = computed(() => page.props.auth?.customer ?? null);

/** From server when logged into portal; subsidiary logo first, then account. */
const portalBrand = computed(() => customer.value?.portal_brand ?? null);

const sidebarLogoUrl = computed(
    () =>
        portalBrand.value?.subsidiary_logo_url ||
        portalBrand.value?.account_logo_url ||
        props.companyLogo ||
        null,
);

/** Only show a title under the logo when the customer has a subsidiary name. */
const sidebarTitleName = computed(() => {
    const n = portalBrand.value?.subsidiary_display_name;
    return typeof n === 'string' && n.trim() !== '' ? n.trim() : null;
});

const sidebarLogoAlt = computed(() => sidebarTitleName.value || 'Customer portal');

const mobileMenuOpen = ref(false);

const navItems = ref([
    { name: 'Overview', href: 'portal.index', icon: 'dashboard' },
    { name: 'Estimates', href: 'portal.estimates', icon: 'request_quote' },
    { name: 'Invoices', href: 'portal.invoices', icon: 'receipt_long' },
    { name: 'Service Tickets', href: 'portal.servicetickets', icon: 'build_circle' },
    { name: 'Documents', href: 'portal.documents', icon: 'folder_open' },
]);

const isActive = (href) => {
    try {
        return route().current(href) || route().current(href + '.*');
    } catch {
        return false;
    }
};
</script>

<template>
    <div class="flex min-h-screen bg-gray-100 font-sans text-gray-900">
        <!-- Sidebar -->
        <aside
            class="w-[248px] min-h-screen bg-gray-900 flex flex-col fixed top-0 left-0 z-50 transition-transform duration-200 ease-in-out flex-shrink-0 lg:translate-x-0"
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo / Brand -->
            <div class="flex items-center gap-3 px-5 pt-6 pb-5">
                <div class="flex-shrink-0">
                    <img
                        v-if="sidebarLogoUrl"
                        :src="sidebarLogoUrl"
                        :alt="sidebarLogoAlt"
                        class="w-[38px] h-[38px] rounded-lg object-contain bg-white p-0.5"
                    />
                    <div
                        v-else
                        class="w-[38px] h-[38px] rounded-lg bg-gradient-to-br from-secondary-400 to-secondary-700 flex items-center justify-center"
                    >
                        <span class="text-white text-lg font-semibold leading-none">
                            {{ sidebarTitleName?.charAt(0) ?? 'C' }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-col gap-px overflow-hidden min-w-0">
                    <span
                        v-if="sidebarTitleName"
                        class="font-semibold text-sm text-white truncate tracking-tight"
                    >
                        {{ sidebarTitleName }}
                    </span>
                    <span
                        class="text-[10px] font-normal text-white/40 uppercase tracking-widest"
                        :class="{ 'mt-0.5': sidebarTitleName }"
                    >
                        Customer Portal
                    </span>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-white/[0.08] mx-5"></div>

            <!-- Navigation -->
            <nav class="flex-1 p-3 flex flex-col gap-0.5">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="route(item.href)"
                    @click="mobileMenuOpen = false"
                    class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg no-underline text-[13.5px] font-medium text-white/65 transition-all duration-150 relative cursor-pointer border-none bg-transparent w-full text-left hover:bg-white/[0.06] hover:text-white/90"
                    :class="{ '!bg-white/10 !text-white': isActive(item.href) }"
                >
                    <span class="material-icons text-lg flex-shrink-0">{{ item.icon }}</span>
                    <span class="flex-1">{{ item.name }}</span>
                    <span
                        v-if="isActive(item.href)"
                        class="w-[5px] h-[5px] rounded-full bg-secondary-400 flex-shrink-0"
                    ></span>
                </Link>
            </nav>

            <!-- Bottom: Customer info + Sign out -->
            <div class="p-3 pb-5 flex flex-col gap-2">
                <div class="h-px bg-white/[0.08] mb-2"></div>
                <div v-if="customer" class="px-3 pb-2">
                    <p class="text-xs text-white/50 truncate">{{ customer.email }}</p>
                </div>
                <Link
                    :href="route('portal.logout')"
                    method="post"
                    as="button"
                    class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg no-underline text-[13.5px] font-medium text-white/35 transition-all duration-150 cursor-pointer border-none bg-transparent w-full text-left hover:bg-white/[0.06] hover:text-white/65"
                >
                    <span class="material-icons text-lg flex-shrink-0">logout</span>
                    <span class="flex-1">Sign Out</span>
                </Link>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 bg-black/40 z-[45] backdrop-blur-sm"
            @click="mobileMenuOpen = false"
        ></div>

        <!-- Main content area -->
        <div class="flex-1 ml-0 lg:ml-[248px] flex flex-col min-h-screen">
            <!-- Top bar -->
            <header class="h-[60px] bg-white border-b border-gray-200 flex items-center gap-4 px-6 sticky top-0 z-40">
                <button
                    class="lg:hidden bg-transparent border-none cursor-pointer text-gray-500 flex items-center p-1 rounded-md hover:bg-gray-100 hover:text-gray-900"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <span class="material-icons">{{ mobileMenuOpen ? 'close' : 'menu' }}</span>
                </button>
                <div class="flex-1 text-lg font-semibold text-gray-900 tracking-tight">
                    <slot name="title">
                        <span v-if="title">{{ title }}</span>
                    </slot>
                </div>
                <div class="flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </header>

            <!-- Optional page header slot -->
            <div v-if="$slots.header" class="px-6 pt-6">
                <slot name="header" />
            </div>

            <!-- Page content -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
