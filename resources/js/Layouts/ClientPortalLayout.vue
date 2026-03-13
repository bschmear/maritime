<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: false },
    companyName: { type: String, default: 'Acme Corp' },
    companyLogo: { type: String, default: null },
});

const mobileMenuOpen = ref(false);

const navItems = ref([
    { name: 'Overview', href: 'portal', icon: 'dashboard' },
    { name: 'Invoices', href: 'portal.invoices', icon: 'receipt_long' },
    { name: 'Estimates', href: 'portal.estimates', icon: 'request_quote' },
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
    <div class="portal-root">
        <!-- Sidebar -->
        <aside class="portal-sidebar" :class="{ 'sidebar-open': mobileMenuOpen }">
            <!-- Logo / Brand -->
            <div class="sidebar-brand">
                <div class="brand-logo">
                    <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="logo-img" />
                    <div v-else class="logo-placeholder">
                        <span class="logo-initial">{{ companyName?.charAt(0) ?? 'C' }}</span>
                    </div>
                </div>
                <div class="brand-text">
                    <span class="brand-name">{{ companyName }}</span>
                    <span class="brand-label">Client Portal</span>
                </div>
            </div>

            <!-- Divider -->
            <div class="sidebar-divider"></div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="route(item.href)"
                    @click="mobileMenuOpen = false"
                    :class="['nav-item', { 'nav-item--active': isActive(item.href) }]"
                >
                    <span class="material-icons nav-icon">{{ item.icon }}</span>
                    <span class="nav-label">{{ item.name }}</span>
                    <span v-if="isActive(item.href)" class="nav-pip"></span>
                </Link>
            </nav>

            <!-- Bottom: Sign out -->
            <div class="sidebar-footer">
                <div class="sidebar-divider"></div>
                <Link :href="route('logout')" method="post" as="button" class="nav-item nav-item--muted">
                    <span class="material-icons nav-icon">logout</span>
                    <span class="nav-label">Sign Out</span>
                </Link>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div
            v-if="mobileMenuOpen"
            class="mobile-overlay"
            @click="mobileMenuOpen = false"
        ></div>

        <!-- Main content area -->
        <div class="portal-body">
            <!-- Top bar (mobile + page title) -->
            <header class="portal-topbar">
                <button class="mobile-toggle lg:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
                    <span class="material-icons">{{ mobileMenuOpen ? 'close' : 'menu' }}</span>
                </button>
                <div class="topbar-title">
                    <slot name="title">
                        <span v-if="title">{{ title }}</span>
                    </slot>
                </div>
                <div class="topbar-actions">
                    <slot name="actions" />
                </div>
            </header>

            <!-- Optional page header slot -->
            <div v-if="$slots.header" class="portal-page-header">
                <slot name="header" />
            </div>

            <!-- Page content -->
            <main class="portal-main">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Serif+Display:ital@0;1&display=swap');
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');

/* ── Tokens ─────────────────────────────────────────── */
:root {
    --sidebar-w: 248px;
    --topbar-h: 60px;

    --c-bg: #f4f3ef;
    --c-sidebar: #1a1a2e;
    --c-sidebar-hover: rgba(255,255,255,0.06);
    --c-sidebar-active: rgba(255,255,255,0.10);
    --c-active-pip: #e8c87a;
    --c-accent: #c9a84c;
    --c-text: #1a1a2e;
    --c-text-muted: #6b6b80;
    --c-border: #e2e0d8;
    --c-white: #ffffff;

    --radius: 10px;
    --font-body: 'DM Sans', sans-serif;
    --font-display: 'DM Serif Display', serif;
}

/* ── Root layout ─────────────────────────────────────── */
.portal-root {
    display: flex;
    min-height: 100vh;
    background: var(--c-bg);
    font-family: var(--font-body);
    color: var(--c-text);
}

/* ── Sidebar ─────────────────────────────────────────── */
.portal-sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--c-sidebar);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 50;
    transition: transform 0.25s ease;
    flex-shrink: 0;
}

/* ── Brand ───────────────────────────────────────────── */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 24px 20px 20px;
}

.brand-logo {
    flex-shrink: 0;
}

.logo-img {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    object-fit: contain;
    background: var(--c-white);
    padding: 2px;
}

.logo-placeholder {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--c-accent), #b8860b);
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-initial {
    font-family: var(--font-display);
    font-size: 18px;
    color: #fff;
    line-height: 1;
}

.brand-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
    overflow: hidden;
}

.brand-name {
    font-weight: 600;
    font-size: 14px;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.01em;
}

.brand-label {
    font-size: 10px;
    font-weight: 400;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* ── Divider ─────────────────────────────────────────── */
.sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: 0 20px;
}

/* ── Nav ─────────────────────────────────────────────── */
.sidebar-nav {
    flex: 1;
    padding: 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    color: rgba(255,255,255,0.65);
    transition: background 0.15s, color 0.15s;
    position: relative;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}

.nav-item:hover {
    background: var(--c-sidebar-hover);
    color: rgba(255,255,255,0.9);
}

.nav-item--active {
    background: var(--c-sidebar-active) !important;
    color: #fff !important;
}

.nav-item--muted {
    color: rgba(255,255,255,0.35);
}

.nav-item--muted:hover {
    color: rgba(255,255,255,0.65);
}

.nav-icon {
    font-size: 18px;
    flex-shrink: 0;
}

.nav-label {
    flex: 1;
}

.nav-pip {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--c-active-pip);
    flex-shrink: 0;
}

/* ── Sidebar footer ──────────────────────────────────── */
.sidebar-footer {
    padding: 12px 12px 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sidebar-footer .sidebar-divider {
    margin: 0 0 8px;
}

/* ── Body ─────────────────────────────────────────────── */
.portal-body {
    flex: 1;
    margin-left: var(--sidebar-w);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ── Topbar ──────────────────────────────────────────── */
.portal-topbar {
    height: var(--topbar-h);
    background: var(--c-white);
    border-bottom: 1px solid var(--c-border);
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 40;
}

.mobile-toggle {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--c-text-muted);
    display: flex;
    align-items: center;
    padding: 4px;
    border-radius: 6px;
}

.mobile-toggle:hover {
    background: var(--c-bg);
    color: var(--c-text);
}

.topbar-title {
    flex: 1;
    font-family: var(--font-display);
    font-size: 18px;
    color: var(--c-text);
    letter-spacing: -0.01em;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Page header ─────────────────────────────────────── */
.portal-page-header {
    padding: 24px 24px 0;
}

/* ── Main ────────────────────────────────────────────── */
.portal-main {
    flex: 1;
    padding: 24px;
}

/* ── Mobile overlay ──────────────────────────────────── */
.mobile-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 45;
    backdrop-filter: blur(2px);
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 1023px) {
    .portal-sidebar {
        transform: translateX(-100%);
    }

    .portal-sidebar.sidebar-open {
        transform: translateX(0);
    }

    .portal-body {
        margin-left: 0;
    }
}
</style>
