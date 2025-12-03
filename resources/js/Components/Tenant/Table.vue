<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
    },
    recordType: {
        type: String,
        default: '',
    },
});

const columns = computed(() => {
    if (!props.schema || !props.schema.columns) {
        return [];
    }
    return props.schema.columns;
});

const getRecordValue = (record, key) => {
    return record[key] ?? '';
};

const getShowUrl = (recordId) => {
    return route(`${props.recordType}.show`, recordId);
};

const getEditUrl = (recordId) => {
    return route(`${props.recordType}.edit`, recordId);
};
</script>

<template>
    <section class="bg-gray-50 dark:bg-gray-900 w-full">
        <div class="w-full">
            <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
                <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                    <div class="w-full md:w-1/2">
                        <form class="flex items-center">
                            <label for="simple-search" class="sr-only">Search</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Search" />
                            </div>
                        </form>
                    </div>
                    <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                        <Link :href="route(recordType + '.create')" class="flex items-center justify-center text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                            <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add {{ recordType }}
                        </Link>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3">
                                    {{ column.label }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="record in records.data" :key="record.id" class="border-b dark:border-gray-700">
                                <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                                    <span v-if="column.key === 'id'" class="font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {{ getRecordValue(record, column.key) }}
                                    </span>
                                    <span v-else class="text-gray-900 dark:text-white">
                                        {{ getRecordValue(record, column.key) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 flex items-center justify-end">
                                    <button :id="`dropdown-button-${record.id}`" :data-dropdown-toggle="`dropdown-${record.id}`" class="inline-flex items-center p-0.5 text-sm font-medium text-center text-gray-500 hover:text-gray-800 rounded-lg focus:outline-none dark:text-gray-400 dark:hover:text-gray-100" type="button">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                        </svg>
                                    </button>
                                    <div :id="`dropdown-${record.id}`" class="hidden z-10 w-44 bg-white rounded divide-y divide-gray-100 shadow dark:bg-gray-700 dark:divide-gray-600">
                                        <ul class="py-1 text-sm text-gray-700 dark:text-gray-200" :aria-labelledby="`dropdown-button-${record.id}`">
                                            <li>
                                                <Link :href="getShowUrl(record.id)" class="block py-2 px-4 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Show</Link>
                                            </li>
                                            <li>
                                                <Link :href="getEditUrl(record.id)" class="block py-2 px-4 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Edit</Link>
                                            </li>
                                        </ul>
                                        <div class="py-1">
                                            <a href="#" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="records.data.length === 0">
                                <td :colspan="columns.length + 1" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                    No records found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="records.links && records.links.length > 3" class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-4" aria-label="Table navigation">
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        Showing
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.from }}</span>
                        to
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.to }}</span>
                        of
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.total }}</span>
                    </span>
                    <div class="inline-flex items-stretch -space-x-px">
                        <template v-for="(link, index) in records.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'flex items-center justify-center text-sm py-2 px-3 leading-tight',
                                    link.active
                                        ? 'z-10 text-primary-600 bg-primary-50 border border-primary-300 hover:bg-primary-100 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white',
                                    index === 0 ? 'rounded-l-lg' : '',
                                    index === records.links.length - 1 ? 'rounded-r-lg' : ''
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                :class="[
                                    'flex items-center justify-center text-sm py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400',
                                    index === 0 ? 'rounded-l-lg' : '',
                                    index === records.links.length - 1 ? 'rounded-r-lg' : ''
                                ]"
                            />
                        </template>
                    </div>
                </nav>
            </div>
        </div>
    </section>
</template>
