<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    /** Which people index is active: `contacts`, `leads`, or `customers`. */
    activePage: {
        type: String,
        required: true,
        validator: (v) => ['contacts', 'leads', 'customers'].includes(v),
    },
});

const links = [
    { key: 'contacts', label: 'All', route: 'contacts.index' },
    { key: 'leads', label: 'Leads', route: 'leads.index' },
    { key: 'customers', label: 'Customers', route: 'customers.index' },
];

function isActive(key) {
    return props.activePage === key;
}

function linkClass(active) {
    return [
        'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
        active
            ? 'bg-primary-600 text-white dark:bg-primary-500'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Link
            v-for="item in links"
            :key="item.key"
            :href="route(item.route)"
            :class="linkClass(isActive(item.key))"
        >
            {{ item.label }}
        </Link>
    </div>
</template>
