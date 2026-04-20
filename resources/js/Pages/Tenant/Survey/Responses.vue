<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserInitialsAvatar from '@/Components/Tenant/UserInitialsAvatar.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

function userDisplayName(user) {
    if (!user) return '';
    if (user.name != null && String(user.name).trim() !== '') return String(user.name).trim();
    return (
        (user.display_name && String(user.display_name).trim())
        || [user.first_name, user.last_name].filter(Boolean).join(' ').trim()
        || user.email
        || ''
    );
}

/** Laravel serializes assignedTo as nested `assigned_to` (object); legacy payloads may use assigned_to_user. */
function assigneeUser(response) {
    if (!response) return null;
    if (response.assigned_to_user && typeof response.assigned_to_user === 'object') {
        return response.assigned_to_user;
    }
    const at = response.assigned_to;
    if (at && typeof at === 'object' && (at.email !== undefined || at.display_name !== undefined || at.name !== undefined)) {
        return at;
    }
    return null;
}

const props = defineProps({
    responses:   { type: Object,          required: true },
    survey:      { type: Object,          default: null },
    users:       { type: Array,           default: () => [] },
    filterUser:  { type: [String, Number], default: 'all' },
    currentUser: { type: Object,          required: true },
});

const pageTitle = computed(() =>
    props.survey ? `Responses: ${props.survey.title}` : 'All Survey Responses'
);

const breadcrumbItems = computed(() => {
    const items = [
        { label: 'Home',    href: route('dashboard') },
        { label: 'Surveys', href: route('surveysIndex') },
    ];
    if (props.survey) {
        items.push({ label: props.survey.title, href: route('surveysShow', { id: props.survey.uuid }) });
        items.push({ label: 'Responses' });
    } else {
        items.push({ label: 'All Responses' });
    }
    return items;
});

const typeConfig = {
    lead:     { label: 'Lead',      classes: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    feedback: { label: 'Feedback',  classes: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    followup: { label: 'Follow Up', classes: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' },
};

const getTypeConfig = (type) =>
    typeConfig[type] ?? { label: 'Custom', classes: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' };

const ownerConfig = (ownerType) => {
    if (!ownerType || typeof ownerType !== 'string') return null;
    const short = ownerType.includes('\\') ? ownerType.split('\\').pop() : ownerType;
    const map = {
        Contact: { icon: 'person', routeName: 'contacts.show', param: 'contact' },
        Lead: { icon: 'trending_up', routeName: 'leads.show', param: 'lead' },
        Vendor: { icon: 'storefront', routeName: 'vendors.show', param: 'vendor' },
    };
    return map[short] ?? null;
};

const formatDate = (dateStr, timezone, format) => {
    if (!dateStr) return null;
    const tz = timezone ?? 'America/Chicago';
    return new Date(dateStr).toLocaleString('en-US', {
        timeZone: tz,
        ...(format === 'date'
            ? { month: 'short', day: 'numeric', year: 'numeric' }
            : { hour: 'numeric', minute: '2-digit' }),
    });
};

const respondentName = (r) =>
    [r.first_name, r.last_name].filter(Boolean).join(' ') || null;

const updateFilterUser = (raw) => {
    const userId = raw === '0' || raw === 0 ? 'all' : raw;
    router.get(
        route(props.survey ? 'surveyResponsesByUuid' : 'surveyResponses'),
        {
            ...(props.survey ? { id: props.survey.uuid } : {}),
            ...(userId !== 'all' ? { filteruser: userId } : {}),
        },
        { preserveState: true, replace: true },
    );
};

function isContactSourceable(r) {
    const id = r.sourceable_id ?? r.sourceable?.id;
    if (!id) return false;
    const t = r.sourceable_type;
    if (!t && r.sourceable) return true;
    return t === 'Contact' || (typeof t === 'string' && t.endsWith('\\Contact'));
}

function contactLinkHref(r) {
    const id = r.sourceable?.id ?? r.sourceable_id;
    if (!id || !isContactSourceable(r)) return null;
    return route('contacts.show', { contact: id });
}

function showConvertActions(r) {
    return Boolean(r.email && !r.converted && !isContactSourceable(r));
}

const convertResponse = (responseId, target) => {
    router.post(route('surveyResponseConvert'), { response_id: responseId, target });
};

const paginate = (url) => {
    if (url) router.get(url, {}, { preserveState: true });
};
</script>

<template>
    <Head :title="pageTitle" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="w-full p-4 space-y-4">

            <!-- Stat cards (survey context only) -->
            <div v-if="survey" class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 shrink-0">
                        <span class="material-icons text-[20px]">forum</span>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ responses.total.toLocaleString() }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total responses</p>
                    </div>
                </div>
                <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 shrink-0">
                        <span class="material-icons text-[20px]">label</span>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ getTypeConfig(survey.type).label }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Survey type</p>
                    </div>
                </div>
                <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0"
                         :class="survey.status ? 'bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'">
                        <span class="material-icons text-[20px]">check_circle</span>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ survey.status ? 'Active' : 'Draft' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                    </div>
                </div>
                <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                    <UserInitialsAvatar :name="userDisplayName(survey.user) || 'Unknown'" />
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">
                            {{ userDisplayName(survey.user) || 'Unknown' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Created by</p>
                    </div>
                </div>
            </div>

            <!-- Main table card -->
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden flex flex-col">

                <!-- Header -->
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ pageTitle }}</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="survey">
                            <a :href="route('surveysShow', { id: survey.uuid })"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <span class="material-icons text-[14px]">poll</span>View survey
                            </a>
                            <a :href="route('surveyResponses')"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <span class="material-icons text-[14px]">list</span>All responses
                            </a>
                        </template>
                        <template v-else>
                            <a :href="route('surveysIndex')"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <span class="material-icons text-[14px]">arrow_back</span>Back to surveys
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Assigned to filter (same idea as Surveys index user filter) -->
                <div
                    v-if="users.length > 1"
                    class="px-5 py-3 border-b border-gray-50 dark:border-gray-700/60 flex flex-wrap items-center gap-3"
                >
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Assigned to</label>
                    <select
                        class="input-style max-w-xs sm:max-w-sm"
                        :value="filterUser === 'all' || filterUser === '' || filterUser == null ? '0' : String(filterUser)"
                        @change="updateFilterUser($event.target.value)"
                    >
                        <option value="0">All users</option>
                        <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ userDisplayName(u) }}</option>
                    </select>
                </div>

                <!-- Empty state -->
                <div v-if="responses.data.length === 0" class="flex flex-col items-center justify-center py-20 px-4">
                    <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                        <span class="material-icons text-[32px] text-gray-400 dark:text-gray-500">inbox</span>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">No responses yet</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm mb-5">
                        {{ survey ? "This survey hasn't received any responses yet." : "No survey responses have been submitted yet." }}
                    </p>
                    <a v-if="survey" :href="route('surveysShow', { id: survey.uuid })"
                       class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                        <span class="material-icons text-[16px]">visibility</span>View survey
                    </a>
                </div>

                <!-- Table -->
                <div v-else class="overflow-x-auto grow">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Submitted</th>
                                <th v-if="!survey" class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Survey</th>
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Type</th>
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Respondent</th>
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Email</th>
                                <!-- <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Linked to</th> -->
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Assigned to</th>
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                            <tr v-for="response in responses.data" :key="response.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">

                                <!-- Submitted -->
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">
                                        {{ formatDate(response.submitted_at, currentUser.timezone, 'date') ?? '—' }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ formatDate(response.submitted_at, currentUser.timezone, 'time') ?? '' }}
                                    </p>
                                </td>

                                <!-- Survey (all responses view) -->
                                <td v-if="!survey" class="px-5 py-3.5">
                                    <a :href="route('surveyResponsesByUuid', { id: response.survey.uuid })"
                                       class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ response.survey.title }}
                                    </a>
                                </td>

                                <!-- Type -->
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="getTypeConfig(response.survey.type).classes">
                                        {{ getTypeConfig(response.survey.type).label }}
                                    </span>
                                </td>

                                <!-- Respondent -->
                                <td class="px-5 py-3.5">
                                    <div v-if="respondentName(response)" class="flex items-center gap-2">
                                        <UserInitialsAvatar :name="respondentName(response)" small />
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ respondentName(response) }}</span>
                                    </div>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500 italic">Anonymous</span>
                                </td>

                                <!-- Email -->
                                <td class="px-5 py-3.5">
                                    <a v-if="response.email" :href="`mailto:${response.email}`"
                                       class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ response.email }}
                                    </a>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500 italic">—</span>
                                </td>

                                <!-- Linked to -->
                                <!-- <td class="px-5 py-3.5">
                                    <a v-if="response.owner_type && response.owner_id && ownerConfig(response.owner_type)"
                                       :href="route(ownerConfig(response.owner_type).routeName, { id: response.owner_id })"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                        <span class="material-icons text-[14px]">{{ ownerConfig(response.owner_type).icon }}</span>
                                        {{ response.owner_type }}
                                    </a>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500 italic">Public</span>
                                </td> -->

                                <!-- Assigned to (relation is serialized as assigned_to object) -->
                                <td class="px-5 py-3.5">
                                    <div v-if="assigneeUser(response)" class="flex items-center gap-2 min-w-0">
                                        <UserInitialsAvatar
                                            :name="userDisplayName(assigneeUser(response))"
                                            small
                                        />
                                        <span class="text-sm text-gray-900 dark:text-white truncate">
                                            {{ userDisplayName(assigneeUser(response)) }}
                                        </span>
                                    </div>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500 italic">—</span>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-1">
                                        <a :href="route('surveyResponseShow', { sid: response.survey.uuid, rid: response.id })"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                           title="View response">
                                            <span class="material-icons text-[18px]">visibility</span>
                                        </a>
                                        <a
                                            v-if="contactLinkHref(response)"
                                            :href="contactLinkHref(response)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-900/30 transition-colors"
                                            title="View linked contact">
                                            <span class="material-icons text-[18px]">person</span>
                                        </a>
                                        <button
                                            v-if="showConvertActions(response) && response.survey.type === 'lead'"
                                            type="button"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-purple-500 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors"
                                            title="Convert to lead"
                                            @click="convertResponse(response.id, 'lead')">
                                            <span class="material-icons text-[18px]">person_add</span>
                                        </button>
                                        <button
                                            v-if="showConvertActions(response)"
                                            type="button"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors"
                                            title="Convert to contact"
                                            @click="convertResponse(response.id, 'contact')">
                                            <span class="material-icons text-[18px]">contact_mail</span>
                                        </button>
                                        <span
                                            v-if="response.converted && !contactLinkHref(response)"
                                            class="inline-flex items-center justify-center w-8 h-8 text-green-500 dark:text-green-400"
                                            title="Converted">
                                            <span class="material-icons text-[18px]">check_circle</span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="responses.data.length > 0" class="px-5 py-3.5 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Showing <span class="font-semibold text-gray-900 dark:text-white">{{ responses.from }}</span>
                        to <span class="font-semibold text-gray-900 dark:text-white">{{ responses.to }}</span>
                        of <span class="font-semibold text-gray-900 dark:text-white">{{ responses.total }}</span> responses
                    </span>
                    <div class="flex gap-1">
                        <template v-for="link in responses.links" :key="link.label">
                            <button v-if="link.url"
                                    class="px-3 py-1 text-xs rounded-lg border transition-colors"
                                    :class="link.active
                                        ? 'bg-primary-600 text-white border-primary-600'
                                        : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                    @click="paginate(link.url)"
                                    v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>