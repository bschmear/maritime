<script setup>
import { computed } from 'vue';

const props = defineProps({
    permissionsByDomain: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const selectedSet = computed(() => new Set(props.modelValue));

function setIds(nextIds) {
    emit('update:modelValue', nextIds);
}

function togglePermission(id, checked) {
    const next = new Set(props.modelValue);
    if (checked) {
        next.add(id);
    } else {
        next.delete(id);
    }
    setIds(Array.from(next));
}

function selectAllInDomain(domain) {
    const next = new Set(props.modelValue);
    (domain.permissions || []).forEach((p) => next.add(p.id));
    setIds(Array.from(next));
}

function clearDomain(domain) {
    const remove = new Set((domain.permissions || []).map((p) => p.id));
    setIds(props.modelValue.filter((id) => !remove.has(id)));
}

const actionLabels = {
    view: 'View',
    create: 'Create',
    edit: 'Edit',
    delete: 'Delete',
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="domain in permissionsByDomain"
            :key="domain.domain"
            class="rounded-lg border border-gray-300 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-800"
        >
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ domain.domainLabel }}
                </h4>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="text-xs font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                        @click="selectAllInDomain(domain)"
                    >
                        Select all
                    </button>
                    <button
                        type="button"
                        class="text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                        @click="clearDomain(domain)"
                    >
                        Clear
                    </button>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="perm in domain.permissions"
                    :key="perm.id"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"
                >
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        :checked="selectedSet.has(perm.id)"
                        @change="togglePermission(perm.id, $event.target.checked)"
                    />
                    <span class="text-gray-800 dark:text-gray-200">
                        {{ actionLabels[perm.action] || perm.action }}
                    </span>
                </label>
            </div>
        </div>
        <p v-if="!permissionsByDomain.length" class="text-sm text-gray-500 dark:text-gray-400">
            No permission catalog found. Run
            <code class="rounded bg-gray-200 px-1 py-0.5 text-xs dark:bg-gray-700">php artisan permissions:sync --all-tenants</code>
            (or
            <code class="rounded bg-gray-200 px-1 py-0.5 text-xs dark:bg-gray-700">permissions:sync</code>
            inside a tenant context) for this tenant.
        </p>
    </div>
</template>
