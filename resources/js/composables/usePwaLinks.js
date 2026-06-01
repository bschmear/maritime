import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * True when the app is running as an installed PWA (standalone) or the server marked the visit as PWA.
 */
export function isPwaLinkContext() {
    if (typeof window === 'undefined') {
        return false;
    }

    if (window.matchMedia('(display-mode: standalone)').matches) {
        return true;
    }

    if (window.matchMedia('(display-mode: window-controls-overlay)').matches) {
        return true;
    }

    if (typeof window.navigator.standalone === 'boolean' && window.navigator.standalone) {
        return true;
    }

    return document.cookie.split('; ').some((row) => row.trim() === 'pwa_mode=1');
}

/**
 * In PWA mode, external links stay in the same window (no target="_blank").
 */
export function usePwaLinks() {
    const page = usePage();

    const pwaForLinks = computed(() => Boolean(page.props.pwa) || isPwaLinkContext());

    const externalLinkTarget = computed(() => (pwaForLinks.value ? '_self' : '_blank'));

    const externalLinkRel = computed(() => (pwaForLinks.value ? undefined : 'noopener noreferrer'));

    return {
        pwaForLinks,
        externalLinkTarget,
        externalLinkRel,
    };
}

/**
 * Fallback for static/markup links that do not bind :target from usePwaLinks().
 */
export function installPwaSameTabLinks() {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener(
        'click',
        (event) => {
            if (!isPwaLinkContext()) {
                return;
            }

            const anchor = event.target.closest('a[target="_blank"]');
            if (!anchor) {
                return;
            }

            if (anchor.hasAttribute('download')) {
                return;
            }

            const href = anchor.getAttribute('href');
            if (!href || href === '#') {
                return;
            }

            event.preventDefault();
            window.location.assign(anchor.href);
        },
        true,
    );
}
