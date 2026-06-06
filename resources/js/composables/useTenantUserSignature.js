import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Saved signature for the logged-in tenant staff user (autofill on internal forms).
 */
export function useTenantUserSignature() {
    const page = usePage();

    const savedSignature = computed(() => page.props.tenant_user_signature ?? null);
    const hasSavedSignature = computed(() => savedSignature.value !== null);

    return {
        savedSignature,
        hasSavedSignature,
    };
}
