<script setup>
import { computed, inject, provide, ref, watch } from 'vue';
import Sortable from 'sortablejs';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    routeCatalog: {
        type: Array,
        default: () => [],
    },
    rolePermissionKeys: {
        type: Array,
        default: () => [],
    },
    readOnly: {
        type: Boolean,
        default: false,
    },
    depth: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['update:modelValue']);

const collapsedKeys = props.depth === 0
    ? ref(new Set())
    : inject('navigationMenuEditorCollapsed', ref(new Set()));

if (props.depth === 0) {
    provide('navigationMenuEditorCollapsed', collapsedKeys);
}

const localItems = ref(normalizeItems(props.modelValue));
const listRef = ref(null);
let sortable = null;

watch(
    () => props.modelValue,
    (items) => {
        const next = normalizeItems(items);
        if (serialized(localItems.value) === serialized(next)) {
            return;
        }

        localItems.value = next;
    },
    { deep: true },
);

function serialized(items) {
    return JSON.stringify(items ?? []);
}

function normalizeItems(items) {
    return JSON.parse(JSON.stringify(items ?? [])).map((item) => ({
        ...item,
        children: Array.isArray(item.children) ? item.children : [],
    }));
}

function emitChange() {
    emit('update:modelValue', normalizeItems(localItems.value));
}

function permissionGranted(item) {
    if (!item.permission_key || props.rolePermissionKeys.length === 0) {
        return true;
    }

    return props.rolePermissionKeys.includes(item.permission_key);
}

function resolvePermissionKey(routeName) {
    if (!routeName) {
        return null;
    }

    const match = props.routeCatalog.find((entry) => entry.route === routeName);
    return match?.permission_key ?? null;
}

function updateItem(index, patch) {
    const next = [...localItems.value];
    const current = { ...next[index], ...patch };

    if (Object.prototype.hasOwnProperty.call(patch, 'route_name')) {
        current.permission_key = resolvePermissionKey(patch.route_name);
        current.permission_granted_for_role = permissionGranted(current);
    }

    if (!Array.isArray(current.children)) {
        current.children = [];
    }

    next[index] = current;
    localItems.value = next;
    emitChange();
}

function addGroup() {
    localItems.value = [
        ...localItems.value,
        {
            label: 'New group',
            route_name: null,
            permission_key: null,
            permission_granted_for_role: true,
            children: [],
        },
    ];
    emitChange();
}

function addLink() {
    const firstRoute = props.routeCatalog[0];
    const routeName = firstRoute?.route ?? null;
    const permissionKey = resolvePermissionKey(routeName);

    localItems.value = [
        ...localItems.value,
        {
            label: firstRoute?.label ?? 'New link',
            route_name: routeName,
            permission_key: permissionKey,
            permission_granted_for_role: permissionGranted({ permission_key: permissionKey }),
            children: [],
        },
    ];
    emitChange();
}

function removeItem(index) {
    const next = [...localItems.value];
    next.splice(index, 1);
    localItems.value = next;
    emitChange();
}

function updateChildren(index, children) {
    const next = [...localItems.value];
    next[index] = {
        ...next[index],
        children: normalizeItems(children),
    };
    localItems.value = next;
    emitChange();
}

function itemKey(index) {
    return `${props.depth}-${index}`;
}

function hasChildren(item) {
    return Array.isArray(item.children) && item.children.length > 0;
}

function isCollapsed(index) {
    return collapsedKeys.value.has(itemKey(index));
}

function toggleCollapsed(index) {
    const key = itemKey(index);
    const next = new Set(collapsedKeys.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    collapsedKeys.value = next;
}

function collectGroupKeys(items, depth = 0) {
    const keys = [];

    items.forEach((item, index) => {
        if (!hasChildren(item)) {
            return;
        }

        keys.push(`${depth}-${index}`);
        keys.push(...collectGroupKeys(item.children, depth + 1));
    });

    return keys;
}

function collapseAllGroups() {
    collapsedKeys.value = new Set(collectGroupKeys(localItems.value));
}

function expandAllGroups() {
    collapsedKeys.value = new Set();
}

function childCountLabel(count) {
    return count === 1 ? '1 item' : `${count} items`;
}

function initSortable() {
    sortable?.destroy();
    sortable = null;

    if (!listRef.value || props.readOnly) {
        return;
    }

    sortable = Sortable.create(listRef.value, {
        animation: 150,
        handle: '.drag-handle',
        draggable: '.menu-editor-row',
        onEnd: (evt) => {
            const items = [...localItems.value];
            const [moved] = items.splice(evt.oldIndex, 1);
            items.splice(evt.newIndex, 0, moved);
            localItems.value = items;
            emitChange();
        },
    });
}

watch(listRef, () => initSortable(), { flush: 'post' });
watch(() => localItems.value.length, () => initSortable(), { flush: 'post' });

const paddingClass = computed(() => {
    if (props.depth === 0) {
        return '';
    }

    return 'ml-4 border-l border-gray-200 pl-4 dark:border-gray-700';
});
</script>

<template>
    <div :class="paddingClass">
        <div
            v-if="depth === 0"
            class="mb-3 flex flex-wrap items-center gap-2"
        >
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="collapseAllGroups"
            >
                <span class="material-icons text-sm">unfold_less</span>
                Collapse all groups
            </button>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="expandAllGroups"
            >
                <span class="material-icons text-sm">unfold_more</span>
                Expand all groups
            </button>
        </div>

        <ul ref="listRef" class="space-y-2">
            <li
                v-for="(item, index) in localItems"
                :key="`${depth}-${index}-${item.label}-${item.route_name ?? 'group'}`"
                class="menu-editor-row space-y-2"
            >
                <div
                    class="flex flex-wrap items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40"
                    :class="{ 'opacity-60': item.permission_key && !permissionGranted(item) }"
                >
                    <button
                        v-if="hasChildren(item)"
                        type="button"
                        class="mt-2 rounded p-0.5 text-gray-500 hover:bg-gray-200 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-100"
                        :title="isCollapsed(index) ? 'Expand group' : 'Collapse group'"
                        @click="toggleCollapsed(index)"
                    >
                        <span
                            class="material-icons text-base transition-transform"
                            :class="isCollapsed(index) ? '' : 'rotate-90'"
                        >
                            chevron_right
                        </span>
                    </button>
                    <span
                        v-else
                        class="mt-2 inline-block w-6 shrink-0"
                        aria-hidden="true"
                    />

                    <button
                        v-if="!readOnly"
                        type="button"
                        class="drag-handle mt-2 cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        title="Drag to reorder"
                    >
                        <span class="material-icons text-base">drag_indicator</span>
                    </button>
                    <span
                        v-else
                        class="mt-2 inline-block w-6 shrink-0"
                        aria-hidden="true"
                    />

                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                :value="item.label"
                                type="text"
                                class="min-w-[10rem] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                :readonly="readOnly"
                                @input="updateItem(index, { label: $event.target.value })"
                            />

                            <select
                                :value="item.route_name ?? ''"
                                class="min-w-[12rem] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                :disabled="readOnly"
                                @change="updateItem(index, { route_name: $event.target.value || null })"
                            >
                                <option value="">Group (no link)</option>
                                <option
                                    v-for="entry in routeCatalog"
                                    :key="entry.route"
                                    :value="entry.route"
                                >
                                    {{ entry.group_path?.length ? `${entry.group_path.join(' › ')} › ` : '' }}{{ entry.label }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span v-if="item.route_name" class="text-gray-500 dark:text-gray-400">{{ item.route_name }}</span>
                            <span
                                v-if="hasChildren(item) && isCollapsed(index)"
                                class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                            >
                                {{ childCountLabel(item.children.length) }}
                            </span>
                            <span
                                v-if="item.permission_key && rolePermissionKeys.length"
                                class="inline-flex items-center rounded-full px-2 py-0.5 font-medium"
                                :class="permissionGranted(item)
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'"
                                :title="permissionGranted(item)
                                    ? 'This role has access'
                                    : 'This role does not have the required permission'"
                            >
                                {{ permissionGranted(item) ? 'Role has access' : 'Missing permission' }}
                            </span>
                        </div>
                    </div>

                    <button
                        v-if="!readOnly"
                        type="button"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-200 hover:text-red-600 dark:hover:bg-gray-700"
                        title="Remove"
                        @click="removeItem(index)"
                    >
                        <span class="material-icons text-base">delete</span>
                    </button>
                </div>

                <NavigationMenuEditor
                    v-show="hasChildren(item) && !isCollapsed(index)"
                    :model-value="item.children"
                    :route-catalog="routeCatalog"
                    :role-permission-keys="rolePermissionKeys"
                    :read-only="readOnly"
                    :depth="depth + 1"
                    @update:model-value="updateChildren(index, $event)"
                />
            </li>
        </ul>

        <div v-if="depth > 0 && !readOnly" class="mt-2 flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-dashed border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                @click="addGroup"
            >
                Add subgroup
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-dashed border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                @click="addLink"
            >
                Add link
            </button>
        </div>

        <div v-if="depth === 0 && !readOnly" class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="addGroup"
            >
                Add group
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="addLink"
            >
                Add link
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'NavigationMenuEditor',
};
</script>
