<script setup>
import { computed, inject, provide, ref, watch } from 'vue';
import Sortable from 'sortablejs';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    routeCatalog: { type: Array, default: () => [] },
    rolePermissionKeys: { type: Array, default: () => [] },
    readOnly: { type: Boolean, default: false },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

function serialized(items) { return JSON.stringify(items ?? []); }

function normalizeItems(items) {
    return JSON.parse(JSON.stringify(items ?? [])).map((item) => ({
        ...item,
        children: Array.isArray(item.children) ? item.children : [],
    }));
}

function hasChildren(item) {
    return Array.isArray(item.children) && item.children.length > 0;
}

function collectGroupKeys(items, depth = 0) {
    const keys = [];
    items.forEach((item, index) => {
        if (!hasChildren(item)) return;
        keys.push(`${depth}-${index}`);
        keys.push(...collectGroupKeys(item.children, depth + 1));
    });
    return keys;
}

const localItems = ref(normalizeItems(props.modelValue));

const collapsedKeys = props.depth === 0
    ? ref(new Set(collectGroupKeys(localItems.value)))
    : inject('navigationMenuEditorCollapsed', ref(new Set()));

if (props.depth === 0) provide('navigationMenuEditorCollapsed', collapsedKeys);
const listRef = ref(null);
let sortable = null;

watch(() => props.modelValue, (items) => {
    const next = normalizeItems(items);
    if (serialized(localItems.value) === serialized(next)) return;
    localItems.value = next;
}, { deep: true });

function emitChange() { emit('update:modelValue', normalizeItems(localItems.value)); }

function permissionGranted(item) {
    if (!item.permission_key || props.rolePermissionKeys.length === 0) return true;
    return props.rolePermissionKeys.includes(item.permission_key);
}

function resolvePermissionKey(routeName) {
    if (!routeName) return null;
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
    if (!Array.isArray(current.children)) current.children = [];
    next[index] = current;
    localItems.value = next;
    emitChange();
}

function addGroup() {
    localItems.value = [...localItems.value, {
        label: 'New group', route_name: null, permission_key: null,
        permission_granted_for_role: true, children: [],
    }];
    emitChange();
}

function addLink() {
    const firstRoute = props.routeCatalog[0];
    const routeName = firstRoute?.route ?? null;
    const permissionKey = resolvePermissionKey(routeName);
    localItems.value = [...localItems.value, {
        label: firstRoute?.label ?? 'New link', route_name: routeName,
        permission_key: permissionKey,
        permission_granted_for_role: permissionGranted({ permission_key: permissionKey }),
        children: [],
    }];
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
    next[index] = { ...next[index], children: normalizeItems(children) };
    localItems.value = next;
    emitChange();
}

function itemKey(index) { return `${props.depth}-${index}`; }
function isCollapsed(index) { return collapsedKeys.value.has(itemKey(index)); }

function toggleCollapsed(index) {
    const key = itemKey(index);
    const next = new Set(collapsedKeys.value);
    next.has(key) ? next.delete(key) : next.add(key);
    collapsedKeys.value = next;
}

function collapseAllGroups() { collapsedKeys.value = new Set(collectGroupKeys(localItems.value)); }
function expandAllGroups() { collapsedKeys.value = new Set(); }
function childCountLabel(count) { return count === 1 ? '1 item' : `${count} items`; }

function initSortable() {
    sortable?.destroy();
    sortable = null;
    if (!listRef.value || props.readOnly) return;
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

const indentClass = computed(() =>
    props.depth === 0 ? '' : 'ml-5 border-l-2 border-gray-200 pl-3 dark:border-gray-700'
);
</script>

<template>
    <div :class="indentClass">

        <!-- Top toolbar (depth 0 only) -->
        <div v-if="depth === 0" class="mb-2 flex items-center justify-between gap-2">
            <div class="flex gap-1.5">
                <button
                    v-if="!readOnly"
                    type="button"
                    class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="addGroup"
                >
                    <span class="material-icons text-[14px]">create_new_folder</span>
                    Group
                </button>
                <button
                    v-if="!readOnly"
                    type="button"
                    class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="addLink"
                >
                    <span class="material-icons text-[14px]">add_link</span>
                    Link
                </button>
            </div>
            <div class="flex gap-1">
                <button
                    type="button"
                    class="inline-flex items-center gap-0.5 rounded px-1.5 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    title="Collapse all groups"
                    @click="collapseAllGroups"
                >
                    <span class="material-icons text-[14px]">unfold_less</span>
                    Collapse
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-0.5 rounded px-1.5 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    title="Expand all groups"
                    @click="expandAllGroups"
                >
                    <span class="material-icons text-[14px]">unfold_more</span>
                    Expand
                </button>
            </div>
        </div>

        <!-- Item list -->
        <ul ref="listRef" class="space-y-2">
            <li
                v-for="(item, index) in localItems"
                :key="`${depth}-${index}-${item.label}-${item.route_name ?? 'group'}`"
                class="menu-editor-row"
            >
                <!-- Row -->
                <div
                    class="flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50/80 px-2 py-1.5 shadow-sm transition-colors hover:border-gray-300 hover:bg-white dark:border-gray-600 dark:bg-gray-800/60 dark:hover:border-gray-500 dark:hover:bg-gray-800"
                    :class="{ 'opacity-50': item.permission_key && !permissionGranted(item) }"
                >
                    <!-- Drag handle -->
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="drag-handle shrink-0 cursor-grab text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                        title="Drag to reorder"
                    >
                        <span class="material-icons text-[16px]">drag_indicator</span>
                    </button>
                    <span v-else class="w-4 shrink-0" />

                    <!-- Collapse toggle -->
                    <button
                        v-if="hasChildren(item)"
                        type="button"
                        class="shrink-0 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        :title="isCollapsed(index) ? 'Expand' : 'Collapse'"
                        @click="toggleCollapsed(index)"
                    >
                        <span
                            class="material-icons text-[16px] transition-transform duration-150"
                            :class="isCollapsed(index) ? '' : 'rotate-90'"
                        >chevron_right</span>
                    </button>
                    <span v-else class="w-4 shrink-0" />

                    <!-- Label input -->
                    <input
                        :value="item.label"
                        type="text"
                        class="w-36 shrink-0 rounded border border-transparent bg-transparent px-1.5 py-0.5 text-xs text-gray-900 hover:border-gray-300 focus:border-primary-400 focus:outline-none focus:ring-0 dark:text-white dark:hover:border-gray-600 dark:focus:border-primary-500"
                        :class="{ 'cursor-default': readOnly }"
                        :readonly="readOnly"
                        @input="updateItem(index, { label: $event.target.value })"
                    />

                    <!-- Route select -->
                    <select
                        :value="item.route_name ?? ''"
                        class="min-w-0 flex-1 rounded border border-transparent bg-transparent px-1.5 py-0.5 text-xs text-gray-700 hover:border-gray-300 focus:border-primary-400 focus:outline-none focus:ring-0 dark:text-gray-300 dark:hover:border-gray-600"
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

                    <!-- Permission badge -->
                    <span
                        v-if="item.permission_key && rolePermissionKeys.length"
                        class="shrink-0 rounded-full px-1.5 py-0.5 text-xs font-medium"
                        :class="permissionGranted(item)
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
                        :title="permissionGranted(item) ? 'Role has access' : 'Missing permission'"
                    >
                        {{ permissionGranted(item) ? '✓' : '!' }}
                    </span>

                    <!-- Collapsed child count -->
                    <span
                        v-if="hasChildren(item) && isCollapsed(index)"
                        class="shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                    >
                        {{ childCountLabel(item.children.length) }}
                    </span>

                    <!-- Remove -->
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="shrink-0 rounded p-0.5 text-gray-300 hover:bg-red-50 hover:text-red-500 dark:text-gray-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                        title="Remove"
                        @click="removeItem(index)"
                    >
                        <span class="material-icons text-[15px]">close</span>
                    </button>
                </div>

                <!-- Children -->
                <NavigationMenuEditor
                    v-show="hasChildren(item) && !isCollapsed(index)"
                    class="mt-2"
                    :model-value="item.children"
                    :route-catalog="routeCatalog"
                    :role-permission-keys="rolePermissionKeys"
                    :read-only="readOnly"
                    :depth="depth + 1"
                    @update:model-value="updateChildren(index, $event)"
                />
            </li>
        </ul>

        <!-- Nested add buttons -->
        <div v-if="depth > 0 && !readOnly" class="mt-1 flex gap-1.5 pl-0.5">
            <button
                type="button"
                class="inline-flex items-center gap-0.5 rounded border border-dashed border-gray-300 px-1.5 py-0.5 text-xs text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:border-gray-500"
                @click="addGroup"
            >
                <span class="material-icons text-[12px]">add</span>
                Subgroup
            </button>
            <button
                type="button"
                class="inline-flex items-center gap-0.5 rounded border border-dashed border-gray-300 px-1.5 py-0.5 text-xs text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:border-gray-500"
                @click="addLink"
            >
                <span class="material-icons text-[12px]">add</span>
                Link
            </button>
        </div>
    </div>
</template>

<script>
export default { name: 'NavigationMenuEditor' };
</script>