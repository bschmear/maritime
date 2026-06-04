<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { displayBodyFromStorage, encodeMentionsForStorage } from '@/Utils/taskCommentMentions.js';
import { useTenantPermissions } from '@/composables/useTenantPermissions.js';

const props = defineProps({
    taskId: { type: [Number, String], required: true },
    canComment: { type: Boolean, default: true },
});

const page = usePage();
const { canEditTask } = useTenantPermissions();
const mayComment = computed(() => props.canComment && canEditTask.value);

const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const comments = ref([]);
const loading = ref(false);
const posting = ref(false);
const error = ref('');
const draft = ref('');
const draftMentions = ref([]);
const textareaRef = ref(null);

const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionOptions = ref([]);
const mentionLoading = ref(false);
const mentionHighlight = ref(0);
const mentionStart = ref(null);

let mentionDebounce = null;

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleString();
}

async function loadComments() {
    if (!props.taskId) {
        return;
    }
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('tasks.comments.index', props.taskId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const p = data.comments;
        comments.value = p?.data ?? [];
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to load comments.';
        comments.value = [];
    } finally {
        loading.value = false;
    }
}

async function searchMentionUsers(q) {
    mentionLoading.value = true;
    try {
        const { data } = await axios.get(route('tasks.comments.mentionable-users'), {
            params: { search: q, limit: 12 },
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        mentionOptions.value = (data.users ?? []).filter(
            (u) => Number(u.id) !== Number(currentUserId.value),
        );
        mentionHighlight.value = 0;
    } catch {
        mentionOptions.value = [];
    } finally {
        mentionLoading.value = false;
    }
}

function closeMention() {
    mentionOpen.value = false;
    mentionQuery.value = '';
    mentionOptions.value = [];
    mentionStart.value = null;
}

function onDraftInput() {
    const el = textareaRef.value;
    if (!el) {
        return;
    }

    const pos = el.selectionStart ?? 0;
    const before = draft.value.slice(0, pos);
    const at = before.lastIndexOf('@');

    if (at === -1) {
        closeMention();
        return;
    }

    const fragment = before.slice(at + 1);
    if (fragment.includes(' ') || fragment.includes('\n') || fragment.includes('[')) {
        closeMention();
        return;
    }

    mentionStart.value = at;
    mentionOpen.value = true;
    mentionQuery.value = fragment;

    clearTimeout(mentionDebounce);
    mentionDebounce = setTimeout(() => searchMentionUsers(mentionQuery.value), 200);
}

function insertMention(user) {
    const el = textareaRef.value;
    if (!el || mentionStart.value === null) {
        return;
    }

    const start = mentionStart.value;
    const cursor = el.selectionStart ?? draft.value.length;
    const label = user.display_name || 'User';
    const display = `@${label} `;
    const before = draft.value.slice(0, start);
    const after = draft.value.slice(cursor);
    draft.value = `${before}${display}${after}`;
    draftMentions.value.push({ displayName: label, userId: user.id });
    closeMention();

    nextTick(() => {
        const nextPos = before.length + display.length;
        el.focus();
        el.setSelectionRange(nextPos, nextPos);
    });
}

function onDraftKeydown(e) {
    if (!mentionOpen.value) {
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (mentionOptions.value.length) {
            mentionHighlight.value = (mentionHighlight.value + 1) % mentionOptions.value.length;
        }
        return;
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (mentionOptions.value.length) {
            mentionHighlight.value =
                (mentionHighlight.value - 1 + mentionOptions.value.length) % mentionOptions.value.length;
        }
        return;
    }

    if (e.key === 'Enter' && mentionOptions.value.length) {
        e.preventDefault();
        insertMention(mentionOptions.value[mentionHighlight.value]);
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        closeMention();
    }
}

async function submitComment() {
    const body = draft.value.trim();
    if (!body || posting.value || !mayComment.value) {
        return;
    }

    posting.value = true;
    error.value = '';
    try {
        const payload = encodeMentionsForStorage(body, draftMentions.value);
        const { data } = await axios.post(
            route('tasks.comments.store', props.taskId),
            { body: payload },
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        );
        if (data.comment) {
            comments.value = [data.comment, ...comments.value];
        }
        draft.value = '';
        draftMentions.value = [];
        closeMention();
    } catch (e) {
        error.value = e.response?.data?.message || e.response?.data?.errors?.body?.[0] || 'Failed to post comment.';
    } finally {
        posting.value = false;
    }
}

watch(
    () => props.taskId,
    () => {
        loadComments();
    },
    { immediate: true },
);

onMounted(() => {
    loadComments();
});
</script>

<template>
    <section class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Comments
        </h3>

        <p v-if="error" class="mb-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>

        <div v-if="mayComment" class="relative mb-6">
            <label for="task-comment-draft" class="sr-only">Add a comment</label>
            <textarea
                id="task-comment-draft"
                ref="textareaRef"
                v-model="draft"
                rows="3"
                class="block w-full resize-y rounded-lg border border-gray-300 bg-gray-50 p-3 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                placeholder="Leave a comment… Type @ to mention a teammate"
                @input="onDraftInput"
                @keydown="onDraftKeydown"
            />

            <div
                v-if="mentionOpen"
                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800"
            >
                <p
                    v-if="mentionLoading"
                    class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400"
                >
                    Searching…
                </p>
                <p
                    v-else-if="!mentionOptions.length"
                    class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400"
                >
                    No users found
                </p>
                <button
                    v-for="(user, idx) in mentionOptions"
                    :key="user.id"
                    type="button"
                    class="flex w-full items-center px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                    :class="idx === mentionHighlight ? 'bg-primary-50 dark:bg-primary-900/30' : ''"
                    @mousedown.prevent="insertMention(user)"
                >
                    <span class="font-medium text-gray-900 dark:text-white">{{ user.display_name }}</span>
                </button>
            </div>

            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="posting || !draft.trim()"
                    @click="submitComment"
                >
                    {{ posting ? 'Posting…' : 'Post comment' }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
            Loading comments…
        </div>

        <ul v-else-if="comments.length" class="space-y-4">
            <li
                v-for="comment in comments"
                :key="comment.id"
                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40"
            >
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ comment.user?.display_name || 'User' }}
                    </span>
                    <time class="text-xs text-gray-500 dark:text-gray-400" :datetime="comment.created_at">
                        {{ formatWhen(comment.created_at) }}
                    </time>
                </div>
                <p class="whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">
                    <span v-html="comment.body_html" />
                </p>
            </li>
        </ul>

        <p v-else class="text-sm text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment.</p>
    </section>
</template>
