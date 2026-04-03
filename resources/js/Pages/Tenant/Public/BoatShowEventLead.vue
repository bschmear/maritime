<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: { type: Object, required: true },
    assets: {
        type: Object,
        default: () => ({ boats: [], engines: [], trailers: [] }),
    },
    account: { type: Object, default: null },
    logoUrl: { type: String, default: null },
});

const allRows = computed(() => {
    const { boats = [], engines = [], trailers = [] } = props.assets;
    return [...boats, ...engines, ...trailers];
});

const selectedIds = ref([]);
const submitted   = ref(false);

function buildLeadNotes() {
    const showName = props.event.boat_show?.display_name ?? '—';
    const eventName = props.event.display_name ?? '—';
    const rows = allRows.value;
    const selected = selectedIds.value
        .map((id) => rows.find((r) => Number(r.id) === Number(id)))
        .filter(Boolean);
    const assetBlock =
        selected.length > 0
            ? selected
                  .map((r) => {
                      const label = r.display_name ?? `Asset #${r.id}`;
                      return r.make ? `• ${label} — ${r.make}` : `• ${label}`;
                  })
                  .join('\n')
            : '• None selected';
    return `Boat show: ${showName}\nEvent: ${eventName}\n\nInterested in:\n${assetBlock}`;
}

const form = useForm({
    first_name:       '',
    last_name:        '',
    email:            '',
    phone:            '',
    notes:            '',
    has_trade_in:     false,
    marketing_opt_in: false,
    asset_ids:        [],
});

const toggleAsset = (id, checked) => {
    const n = Number(id);
    if (checked) {
        if (!selectedIds.value.includes(n)) selectedIds.value = [...selectedIds.value, n];
    } else {
        selectedIds.value = selectedIds.value.filter((x) => x !== n);
    }
};

const submit = () => {
    form.asset_ids = selectedIds.value;
    form.notes = buildLeadNotes();
    form.post(route('boat-show-events.public.lead.store', { uuid: props.event.uuid }), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
        },
    });
};

const resetForm = () => {
    submitted.value = false;
    selectedIds.value = [];
    form.reset();
};
</script>

<template>
    <Head :title="`Lead — ${event.display_name ?? 'Boat show'}`" />
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto flex max-w-2xl flex-col items-center gap-4 px-4 py-8">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="max-h-14 w-auto object-contain" />
                <div class="text-center">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ event.display_name }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tell us how we can help</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-8">

            <!-- Success / Thank You State -->
            <div v-if="submitted" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-8 text-center space-y-6">
                <div class="flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/40 mx-auto">
                    <span class="material-icons text-4xl text-green-600 dark:text-green-400">check_circle</span>
                </div>

                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Thanks for stopping by!</h2>
                    <p class="text-gray-500 dark:text-gray-400">
                        We've received your information and a member of our team will be in touch with you soon.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <a
                        :href="route('boat-show-events.public.showcase', { uuid: event.uuid })"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors w-full sm:w-auto justify-center"
                    >
                        <span class="material-icons text-base leading-none">arrow_back</span>
                        Back to Event
                    </a>

                    <button
                        @click="resetForm"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition-colors w-full sm:w-auto justify-center"
                    >
                        <span class="material-icons text-base leading-none">person_add</span>
                        Submit Another Lead
                    </button>
                </div>
            </div>

            <!-- Form State -->
            <template v-else>
                <div
                    v-if="form.hasErrors"
                    class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200"
                >
                    Please fix the errors below and try again.
                </div>

                <form
                    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">First name *</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                required
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Last name *</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                required
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.phone }}</p>
                    </div>

                    <div v-if="allRows.length">
                        <p class="mb-2 text-sm font-medium text-gray-900 dark:text-white">Which assets are you interested in?</p>
                        <ul class="space-y-2">
                            <li v-for="row in allRows" :key="row.id" class="flex items-start gap-2">
                                <input
                                    :id="`asset-${row.id}`"
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300 dark:border-gray-600"
                                    :checked="selectedIds.includes(row.id)"
                                    @change="toggleAsset(row.id, $event.target.checked)"
                                />
                                <label :for="`asset-${row.id}`" class="text-sm text-gray-800 dark:text-gray-200">
                                    {{ row.display_name }}
                                    <span v-if="row.make" class="text-gray-500 dark:text-gray-400"> — {{ row.make }}</span>
                                </label>
                            </li>
                        </ul>
                        <p v-if="form.errors.asset_ids" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.asset_ids }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input v-model="form.has_trade_in" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        I have a trade-in
                    </label>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input v-model="form.marketing_opt_in" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        I agree to receive marketing communications (optional)
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-blue-600 py-3 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Submitting…' : 'Submit' }}
                    </button>
                </form>
            </template>
        </main>
    </div>
</template>