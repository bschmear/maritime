<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    recordType: {
        type: String,
        default: 'lead',
    },
    recordTitle: {
        type: String,
        default: 'lead',
    },
    pluralTitle: {
        type: String,
        default: 'leads',
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const quickBooksImportRef = ref(null);
const gearMenuOpen = ref(false);
const gearRootRef = ref(null);

/** From `table.json` → `settings.bulk_actions`, same shape as contact index. */
const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

const closeGearMenu = () => {
    gearMenuOpen.value = false;
};

const toggleGearMenu = () => {
    gearMenuOpen.value = !gearMenuOpen.value;
};

const onDocumentClick = (e) => {
    if (!gearMenuOpen.value) {
        return;
    }
    const el = gearRootRef.value;
    if (el && !el.contains(e.target)) {
        gearMenuOpen.value = false;
    }
};

const handleBulkAction = (item) => {
    const action = item.action;
    if (action === 'importFromQuickbooks') {
        closeGearMenu();
        quickBooksImportRef.value?.openImportModal?.();
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div
                    v-if="bulkActions.length"
                    class="mt-4 flex flex-wrap items-center justify-end gap-2"
                >
                    <div
                        ref="gearRootRef"
                        class="relative shrink-0"
                    >
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            :aria-expanded="gearMenuOpen"
                            aria-haspopup="menu"
                            title="Actions"
                            aria-label="Open actions menu"
                            @click.stop="toggleGearMenu"
                        >
                            <span class="material-icons text-[22px]">settings</span>
                        </button>
                        <div
                            v-if="gearMenuOpen"
                            class="absolute right-0 top-full z-50 mt-1.5 min-w-[220px] overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                            role="menu"
                        >
                            <button
                                v-for="(row, idx) in bulkActions"
                                :key="idx"
                                type="button"
                                role="menuitem"
                                class="flex w-full px-4 py-2.5 text-left text-sm text-gray-800 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-700/80"
                                @click="handleBulkAction(row)"
                            >
                                {{ row.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" :record-title="recordTitle" :plural-title="pluralTitle" />

        <QuickBooksImport ref="quickBooksImportRef" record-type="lead" />
    </TenantLayout>
</template>

