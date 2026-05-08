<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    accounts: {
        type: Array,
        default: () => [],
    },
    pending_invitations: {
        type: Array,
        default: () => [],
    },
});

const pwa = computed(() => Boolean(usePage().props.pwa));
const pwaForLinks = computed(() => {
    if (pwa.value) return true;
    if (typeof window === 'undefined') return false;
    if (window.matchMedia('(display-mode: standalone)').matches) return true;
    if (window.matchMedia('(display-mode: window-controls-overlay)').matches) return true;
    if (typeof window.navigator.standalone === 'boolean' && window.navigator.standalone) return true;
    return false;
});

const showModal = ref(false);
const modalType = ref('success');
const modalTitle = ref('');
const modalMessage = ref('');
const confirmAction = ref(null);
const isConfirmModal = ref(false);
const isProcessing = ref(false);

const getTenantUrl = (domain) => {
    if (!domain) return null;
    return `${window.location.protocol}//${domain}`;
};

const showConfirmModal = (title, message, onConfirm) => {
    modalTitle.value = title;
    modalMessage.value = message;
    confirmAction.value = onConfirm;
    isConfirmModal.value = true;
    showModal.value = true;
};

const showResultModal = (type, title, message, shouldReload = false) => {
    modalType.value = type;
    modalTitle.value = title;
    modalMessage.value = message;
    isConfirmModal.value = false;
    showModal.value = true;
    if (shouldReload) {
        setTimeout(() => {
            router.reload({ only: ['accounts', 'pending_invitations'] });
            closeModal();
        }, 2000);
    }
};

const closeModal = () => {
    showModal.value = false;
    confirmAction.value = null;
    isProcessing.value = false;
};

const handleConfirm = () => {
    if (confirmAction.value && !isProcessing.value) {
        isProcessing.value = true;
        confirmAction.value();
    }
};

const acceptInvitation = (invitation) => {
    showConfirmModal(
        'Accept Invitation',
        `Are you sure you want to accept the invitation to join ${invitation.account.name} as a ${invitation.role}?`,
        () => {
            router.post(route('invitations.accept', invitation.token), {}, {
                preserveScroll: true,
                onSuccess: () => showResultModal('success', 'Invitation Accepted!', `Welcome to ${invitation.account.name}!`, true),
                onError: (errors) => {
                    const msg = errors?.stripe?.[0] || errors?.plan?.[0] || errors?.seats?.[0] || errors?.accept?.[0] || 'Failed to accept invitation.';
                    showResultModal('error', 'Error', msg);
                    isProcessing.value = false;
                },
            });
        }
    );
};

const declineInvitation = (invitation) => {
    showConfirmModal(
        'Decline Invitation',
        `Are you sure you want to decline the invitation to join ${invitation.account.name}?`,
        () => {
            router.post(route('invitations.decline', invitation.token), {}, {
                preserveScroll: true,
                onSuccess: () => showResultModal('success', 'Invitation Declined', 'The invitation has been declined.', true),
                onError: (errors) => {
                    showResultModal('error', 'Error', errors?.decline?.[0] || 'Failed to decline invitation.');
                    isProcessing.value = false;
                },
            });
        }
    );
};

const cancelAccount = (account) => {
    showConfirmModal(
        'Cancel Subscription',
        `Are you sure you want to cancel your subscription for "${account.name}"? You'll retain access until the end of the billing period.`,
        () => {
            router.post(route('accounts.cancel', account.id), {}, {
                preserveScroll: true,
                onSuccess: () => showResultModal('success', 'Subscription Cancelled', 'You will retain access until the end of your current billing period.', true),
                onError: (errors) => {
                    showResultModal('error', 'Error', errors?.subscription?.[0] || errors?.stripe?.[0] || 'Failed to cancel subscription.');
                    isProcessing.value = false;
                },
                onFinish: () => { isProcessing.value = false; },
            });
        }
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight tracking-tight text-gray-900 dark:text-white sm:text-2xl">
                    {{ pwa ? 'Your apps' : 'Manage Accounts' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ pwa ? 'Open an account you have access to' : 'Manage accounts, invitations, and access' }}
                </p>
            </div>
        </template>

        <div class="grow bg-gray-50 py-10 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- ── Pending invitations ───────────────────────────────── -->
                <section v-if="!pwa && pending_invitations.length > 0">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Invitations</span>
                        <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-bold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                            {{ pending_invitations.length }} pending
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="invitation in pending_invitations"
                            :key="invitation.id"
                            class="group relative overflow-hidden rounded-xl border border-primary-200/60 bg-white p-5 dark:border-primary-800/40 dark:bg-gray-900"
                        >
                            <!-- accent bar -->
                            <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl bg-primary-500" />

                            <div class="flex flex-col gap-4 pl-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="material-icons text-base text-primary-500">mail_outline</span>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">
                                            {{ invitation.account.name }}
                                        </h4>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            {{ invitation.role }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Invited by {{ invitation.inviter?.name || 'Unknown' }}
                                        <span v-if="invitation.inviter?.email"> · {{ invitation.inviter.email }}</span>
                                        · {{ new Date(invitation.created_at).toLocaleDateString() }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-500"
                                        @click="acceptInvitation(invitation)"
                                    >
                                        <span class="material-icons text-base leading-none">check</span>
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                        @click="declineInvitation(invitation)"
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── Accounts ──────────────────────────────────────────── -->
                <section>
                    <!-- Header row — only shown for multi-account or empty -->
                    <div
                        v-if="accounts.length !== 1"
                        class="mb-4 flex items-center justify-between"
                    >
                        <span class="text-xs font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                            {{ pwa ? 'Tenants' : 'Accounts' }}
                        </span>
                        <Link
                            v-if="!pwa"
                            :href="route('checkout.plans')"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-500"
                        >
                            <span class="material-icons text-base leading-none">add</span>
                            New account
                        </Link>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="accounts.length === 0"
                        class="rounded-xl border border-dashed border-gray-300 bg-white p-16 text-center dark:border-gray-700 dark:bg-gray-900"
                    >
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <span class="material-icons text-2xl text-gray-400">domain</span>
                        </div>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ pwa ? 'No active tenant access' : 'No accounts yet' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-xs mx-auto">
                            {{ pwa
                                ? "You don't have an account with an active subscription, or a tenant domain isn't set up yet."
                                : 'Choose a plan to create your first account and invite your team.' }}
                        </p>
                        <Link
                            v-if="!pwa"
                            :href="route('checkout.plans')"
                            class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                        >
                            View plans
                            <span class="material-icons text-base leading-none">arrow_forward</span>
                        </Link>
                    </div>

                    <!-- ── SINGLE ACCOUNT — hero treatment ─────────────────── -->
                    <div v-else-if="accounts.length === 1">
                        <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">

                            <!-- Subtle grid background -->
                            <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true">
                                <div v-for="n in 5" :key="'v'+n"
                                    class="absolute top-0 bottom-0 w-px bg-primary-400/10"
                                    :style="{ left: `${n * 16.666}%` }" />
                                <div v-for="n in 3" :key="'h'+n"
                                    class="absolute left-0 right-0 h-px bg-primary-400/10"
                                    :style="{ top: `${n * 25}%` }" />
                            </div>

                            <!-- Top accent gradient -->
                            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-primary-400/60 to-transparent" />

                            <div class="relative p-8 sm:p-10">
                                <!-- Label -->
                                <div class="mb-6 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-primary-200/50 bg-primary-50 px-3 py-1 text-xs font-bold uppercase tracking-widest text-primary-700 dark:border-primary-800/40 dark:bg-primary-950/50 dark:text-primary-400">
                                        <span class="material-icons text-xs leading-none">anchor</span>
                                        Your account
                                    </span>
                                    <span
                                        v-if="accounts[0].is_owner"
                                        class="rounded-full bg-secondary-100 px-3 py-1 text-xs font-bold text-secondary-800 dark:bg-secondary-900/40 dark:text-secondary-300"
                                    >Owner</span>
                                    <span
                                        v-else
                                        class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >{{ accounts[0].user_role }}</span>
                                </div>

                                <!-- Account name + meta -->
                                <h3 class="text-3xl font-black tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                                    {{ accounts[0].name }}
                                </h3>
                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center gap-1">
                                        <span class="material-icons text-sm leading-none">group</span>
                                        {{ accounts[0].users_count }} {{ accounts[0].users_count === 1 ? 'user' : 'users' }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-icons text-sm leading-none">calendar_today</span>
                                        Since {{ new Date(accounts[0].created_at).toLocaleDateString() }}
                                    </span>
                                </div>

                                <!-- Domain pill -->
                                <div
                                    v-if="accounts[0].domain"
                                    class="mt-6 inline-flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <span class="material-icons text-sm text-gray-400">link</span>
                                    <span class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ accounts[0].domain }}</span>
                                </div>

                                <!-- Actions -->
                                <div class="mt-8 flex flex-wrap items-center gap-3">
                                    <a
                                        v-if="accounts[0].domain"
                                        :href="getTenantUrl(accounts[0].domain)"
                                        :target="pwaForLinks ? '_self' : '_blank'"
                                        :rel="pwaForLinks ? null : 'noopener noreferrer'"
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-primary-500"
                                    >
                                        <span class="material-icons text-base leading-none">rocket_launch</span>
                                        Open app
                                        <span v-if="!pwaForLinks" class="material-icons text-sm leading-none opacity-70">open_in_new</span>
                                    </a>
                                    <Link
                                        v-if="accounts[0].is_owner && !pwa"
                                        :href="route('accounts.show', accounts[0].id)"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary-400 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        <span class="material-icons text-base leading-none">settings</span>
                                        Manage
                                    </Link>
                                    <button
                                        v-if="accounts[0].is_owner && !pwa"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400"
                                        @click="cancelAccount(accounts[0])"
                                    >
                                        Cancel subscription
                                    </button>
                                    <Link
                                        v-if="!pwa"
                                        :href="route('checkout.plans')"
                                        class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-500 transition hover:border-gray-300 hover:text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        <span class="material-icons text-base leading-none">add</span>
                                        Add account
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── MULTIPLE ACCOUNTS — grid ────────────────────────── -->
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="group relative flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700"
                        >
                            <!-- top accent on hover -->
                            <div class="absolute top-0 left-0 right-0 h-0.5 scale-x-0 bg-gradient-to-r from-primary-400 to-primary-600 transition-transform duration-300 group-hover:scale-x-100" />

                            <div class="flex flex-1 flex-col p-5">
                                <div class="mb-4 flex items-start justify-between gap-2">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                        <span class="material-icons text-xl text-gray-400">business</span>
                                    </div>
                                    <span
                                        v-if="account.is_owner"
                                        class="rounded-full bg-secondary-100 px-2.5 py-0.5 text-xs font-bold text-secondary-800 dark:bg-secondary-900/40 dark:text-secondary-300"
                                    >Owner</span>
                                    <span
                                        v-else
                                        class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                    >{{ account.user_role }}</span>
                                </div>

                                <h4 class="text-base font-bold text-gray-900 dark:text-white">{{ account.name }}</h4>
                                <p v-if="!pwa" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ account.users_count }} {{ account.users_count === 1 ? 'user' : 'users' }}
                                    · Since {{ new Date(account.created_at).toLocaleDateString() }}
                                </p>

                                <div
                                    v-if="account.domain"
                                    class="mt-3 flex items-center gap-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <span class="material-icons text-sm text-gray-400">link</span>
                                    <span class="min-w-0 flex-1 truncate font-mono text-xs text-gray-600 dark:text-gray-400">{{ account.domain }}</span>
                                </div>

                                <div class="mt-4 flex flex-1 flex-col justify-end gap-2">
                                    <a
                                        v-if="account.domain"
                                        :href="getTenantUrl(account.domain)"
                                        :target="pwaForLinks ? '_self' : '_blank'"
                                        :rel="pwaForLinks ? null : 'noopener noreferrer'"
                                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                                    >
                                        Open
                                        <span v-if="!pwaForLinks" class="material-icons text-sm leading-none">open_in_new</span>
                                    </a>
                                    <Link
                                        v-if="account.is_owner && !pwa"
                                        :href="route('accounts.show', account.id)"
                                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        <span class="material-icons text-sm leading-none">settings</span>
                                        Manage
                                    </Link>
                                    <button
                                        v-if="account.is_owner && !pwa"
                                        type="button"
                                        class="w-full rounded-lg border border-red-100 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400"
                                        @click="cancelAccount(account)"
                                    >
                                        Cancel subscription
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>

        <!-- ── Modal ────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm"
                    aria-label="Close dialog"
                    @click="closeModal"
                />
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                    <!-- Header -->
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 dark:border-gray-800"
                        :class="isConfirmModal ? 'bg-white dark:bg-gray-900' : modalType === 'success' ? 'bg-secondary-50 dark:bg-secondary-950/20' : 'bg-red-50 dark:bg-red-950/20'"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                                :class="isConfirmModal ? 'bg-primary-100 dark:bg-primary-900/40' : modalType === 'success' ? 'bg-secondary-100 dark:bg-secondary-900/50' : 'bg-red-100 dark:bg-red-900/50'"
                            >
                                <span class="material-icons text-lg leading-none"
                                    :class="isConfirmModal ? 'text-primary-600 dark:text-primary-400' : modalType === 'success' ? 'text-secondary-600 dark:text-secondary-400' : 'text-red-600 dark:text-red-400'"
                                >{{ isConfirmModal ? 'help_outline' : modalType === 'success' ? 'check_circle' : 'error_outline' }}</span>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ modalTitle }}</h3>
                        </div>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 dark:hover:bg-gray-800" @click="closeModal">
                            <span class="material-icons text-xl leading-none">close</span>
                        </button>
                    </div>
                    <!-- Body -->
                    <div class="px-6 py-5">
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ modalMessage }}</p>
                    </div>
                    <!-- Footer -->
                    <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                        <button
                            v-if="isConfirmModal"
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            @click="closeModal"
                        >Cancel</button>
                        <button
                            v-if="isConfirmModal"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50"
                            :disabled="isProcessing"
                            @click="handleConfirm"
                        >
                            <svg v-if="isProcessing" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {{ isProcessing ? 'Processing…' : 'Confirm' }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition"
                            :class="modalType === 'success' ? 'bg-secondary-600 hover:bg-secondary-500' : 'bg-red-600 hover:bg-red-500'"
                            @click="closeModal"
                        >Close</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
