import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { isPwaLinkContext } from '@/composables/usePwaLinks';

/**
 * Visibility helpers for the optional fixed mobile action bar (small screens + PWA).
 */
export function useMobileActionBar() {
    const page = usePage();

    const isPwa = computed(() => Boolean(page.props.pwa) || isPwaLinkContext());

    /** Hide duplicate header actions when the bottom bar is shown. */
    const headerActionsClass = computed(() => (isPwa.value ? 'hidden' : 'hidden sm:flex sm:w-auto sm:flex-wrap'));

    /** Bar is visible below `sm` or in PWA on any viewport. */
    const barVisibilityClass = computed(() => (isPwa.value ? '' : 'sm:hidden'));

    return {
        isPwa,
        headerActionsClass,
        barVisibilityClass,
    };
}
