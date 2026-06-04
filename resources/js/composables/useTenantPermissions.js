import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Tenant role permission keys from Inertia (`tenant_permissions`), e.g. `task.edit`.
 */
export function useTenantPermissions() {
    const page = usePage();

    const permissionSet = computed(() => new Set(page.props.tenant_permissions ?? []));

    const can = (key) => permissionSet.value.has(key);

    return {
        can,
        canViewTask: computed(() => can('task.view')),
        canCreateTask: computed(() => can('task.create')),
        canEditTask: computed(() => can('task.edit')),
        canDeleteTask: computed(() => can('task.delete')),
    };
}
