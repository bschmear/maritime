<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Mailchimp from '@/Components/Tenant/Mailchimp.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
        default: 'contact',
    },
    recordTitle: {
        type: String,
        default: 'contact',
    },
    pluralTitle: {
        type: String,
        default: 'contacts',
    },
    roleFilter: {
        type: String,
        default: null,
    },
});

const mailchimpRef = ref(null);
const quickBooksImportRef = ref(null);
const selectedTableIds = ref([]);
const gearMenuOpen = ref(false);
const gearRootRef = ref(null);

const ENUM_CONTACT_TYPE = 'App\\Enums\\Entity\\ContactType';
const ENUM_CONTACT_STATUS = 'App\\Enums\\Entity\\ContactStatus';
const ENUM_CONTACT_STAGE = 'App\\Enums\\Entity\\ContactStage';

const mailchimpEntityType = computed(() => (props.roleFilter === 'lead' ? 'lead' : 'contact'));

const mailchimpRecordtype = computed(() => props.enumOptions[ENUM_CONTACT_TYPE] ?? {});
const mailchimpStatuses = computed(() => props.enumOptions[ENUM_CONTACT_STATUS] ?? {});
const mailchimpPriorities = computed(() => props.enumOptions[ENUM_CONTACT_STAGE] ?? {});

/** From `table.json` → `settings.bulk_actions`, same shape as schema. */
const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

const onTableSelectionChange = (ids) => {
    selectedTableIds.value = ids;
};

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

    if (action === 'syncWithMailchimp') {
        closeGearMenu();
        mailchimpRef.value?.openSyncModal?.();
        return;
    }

    if (action === 'importFromQuickbooks') {
        closeGearMenu();
        quickBooksImportRef.value?.openImportModal?.();
        return;
    }

    if (action === 'bulkDelete') {
        closeGearMenu();
        if (!selectedTableIds.value.length) {
            window.alert('Select at least one contact first.');
            return;
        }
        if (!window.confirm(`Delete ${selectedTableIds.value.length} selected contact(s)? This cannot be undone.`)) {
            return;
        }
        router.post(route('contacts.bulk-destroy'), { ids: selectedTableIds.value }, {
            preserveScroll: true,
        });
        return;
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const roleLinks = [
    { key: null, label: 'All' },
    { key: 'lead', label: 'Leads' },
    { key: 'customer', label: 'Customers' },
];

const roleLinkClass = (key) => {
    const active = (props.roleFilter ?? null) === key;
    return [
        'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
        active
            ? 'bg-primary-600 text-white dark:bg-primary-500'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
};
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-for="item in roleLinks"
                            :key="item.key ?? 'all'"
                            :href="item.key ? route('contacts.index', { role: item.key }) : route('contacts.index')"
                            :class="roleLinkClass(item.key)"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                    <div
                        v-if="bulkActions.length"
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

        <Table
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
            @selection-change="onTableSelectionChange"
        />

        <Mailchimp
            ref="mailchimpRef"
            :type="mailchimpEntityType"
            :table-selected-ids="selectedTableIds"
            :statuses="mailchimpStatuses"
            :priorities="mailchimpPriorities"
            :recordtype="mailchimpRecordtype"
        />

        <QuickBooksImport ref="quickBooksImportRef" :record-type="mailchimpEntityType" />
    </TenantLayout>
</template>
