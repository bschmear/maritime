<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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

// Modal state
const showModal = ref(false);
const modalType = ref('success'); // 'success' or 'error'
const modalTitle = ref('');
const modalMessage = ref('');
const confirmAction = ref(null);
const isConfirmModal = ref(false);
const isProcessing = ref(false);

const getTenantUrl = (domain) => {
    if (!domain) return null;
    const protocol = window.location.protocol;
    return `${protocol}//${domain}`;
};

// Show confirmation modal
const showConfirmModal = (title, message, onConfirm) => {
    modalTitle.value = title;
    modalMessage.value = message;
    confirmAction.value = onConfirm;
    isConfirmModal.value = true;
    showModal.value = true;
};

// Show result modal
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

// Close modal
const closeModal = () => {
    showModal.value = false;
    confirmAction.value = null;
    isProcessing.value = false;
};

// Handle confirmation
const handleConfirm = () => {
    if (confirmAction.value && !isProcessing.value) {
        isProcessing.value = true;
        confirmAction.value();
    }
};

// Handle invitation acceptance
const acceptInvitation = (invitation) => {
    showConfirmModal(
        'Accept Invitation',
        `Are you sure you want to accept the invitation to join ${invitation.account.name} as a ${invitation.role}?`,
        () => {
            router.post(route('invitations.accept', invitation.token), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    showResultModal(
                        'success',
                        'Invitation Accepted!',
                        `Welcome to ${invitation.account.name}! You have successfully joined the account.`,
                        true
                    );
                },
                onError: (errors) => {
                    console.error('Error accepting invitation:', errors);
                    const errorMessage = errors?.stripe?.[0] ||
                                       errors?.plan?.[0] ||
                                       errors?.seats?.[0] ||
                                       errors?.accept?.[0] ||
                                       'Failed to accept invitation. Please try again.';
                    showResultModal('error', 'Error', errorMessage);
                    isProcessing.value = false;
                },
                onFinish: () => {
                    // Don't set isProcessing to false here, let the success/error handlers do it
                }
            });
        }
    );
};

// Handle invitation decline
const declineInvitation = (invitation) => {
    showConfirmModal(
        'Decline Invitation',
        `Are you sure you want to decline the invitation to join ${invitation.account.name}?`,
        () => {
            router.post(route('invitations.decline', invitation.token), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    showResultModal(
                        'success',
                        'Invitation Declined',
                        'The invitation has been declined.',
                        true
                    );
                },
                onError: (errors) => {
                    console.error('Error declining invitation:', errors);
                    const errorMessage = errors?.decline?.[0] || 'Failed to decline invitation. Please try again.';
                    showResultModal('error', 'Error', errorMessage);
                    isProcessing.value = false;
                },
                onFinish: () => {
                    // Don't set isProcessing to false here, let the success/error handlers do it
                }
            });
        }
    );
};

const cancelAccount = (account) => {
    showConfirmModal(
        'Cancel Subscription',
        `Are you sure you want to cancel your subscription for "${account.name}"? Your subscription will stay active until the end of the current billing period, then you will lose access to the account and its data.`,
        () => {
            router.post(route('accounts.cancel', account.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    showResultModal(
                        'success',
                        'Subscription Cancelled',
                        'Your subscription has been cancelled. You will retain access until the end of your current billing period.',
                        true
                    );
                },
                onError: (errors) => {
                    const errorMessage =
                        errors?.subscription?.[0] ||
                        errors?.stripe?.[0] ||
                        'Failed to cancel subscription. Please try again.';
                    showResultModal('error', 'Error', errorMessage);
                    isProcessing.value = false;
                },
                onFinish: () => {
                    isProcessing.value = false;
                },
            });
        },
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight tracking-tight text-gray-900 dark:text-white sm:text-2xl">
                    Dashboard
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage accounts, invitations, and tenant access
                </p>
            </div>
        </template>

        <div class="grow bg-gray-50 py-10 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Welcome -->
                <section
                    class="mb-10 rounded-2xl border border-gray-200 bg-primary-50 px-6 py-8 dark:border-gray-700 dark:bg-gray-900 sm:px-10"
                >
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                        <div class="max-w-xl">
                            <div
                                class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-200/50 bg-primary-100 px-3 py-1.5 text-sm font-medium text-primary-800 dark:border-primary-700/50 dark:bg-primary-900/40 dark:text-primary-300"
                            >
                                <span class="material-icons text-base leading-none">dashboard</span>
                                <span>Your workspace</span>
                            </div>
                            <h3 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                                Welcome back
                            </h3>
                            <p class="mt-2 text-base leading-relaxed text-gray-600 dark:text-gray-400">
                                Open your tenant apps, manage billing, and respond to team invitations from one place.
                            </p>
                        </div>
                        <div
                            class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800 sm:flex"
                        >
                            <span class="material-icons text-3xl leading-none text-primary-600 dark:text-primary-400"
                                >anchor</span
                            >
                        </div>
                    </div>
                </section>

                <!-- Pending invitations -->
                <section v-if="pending_invitations.length > 0" class="mb-10">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                                Invitations
                            </p>
                            <h3 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">Account invitations</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ pending_invitations.length }} pending invitation{{
                                    pending_invitations.length === 1 ? '' : 's'
                                }}
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center rounded-full border border-primary-200 bg-white px-3 py-1 text-xs font-semibold text-primary-800 dark:border-primary-800 dark:bg-primary-950/50 dark:text-primary-300"
                        >
                            Action required
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="invitation in pending_invitations"
                            :key="invitation.id"
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="mb-3 flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-900/40"
                                        >
                                            <span class="material-icons text-xl leading-none text-primary-600 dark:text-primary-400"
                                                >mail</span
                                            >
                                        </div>
                                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            Invited to {{ invitation.account.name }}
                                        </h4>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                            <p>
                                                <span class="font-medium text-gray-800 dark:text-gray-200">Role</span>
                                                — {{ invitation.role }}
                                            </p>
                                            <p>
                                                <span class="font-medium text-gray-800 dark:text-gray-200">Invited by</span>
                                                — {{ invitation.inviter?.name || 'Unknown' }}
                                                <span v-if="invitation.inviter?.email"> ({{ invitation.inviter.email }})</span>
                                            </p>
                                        </div>
                                        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                            <p>
                                                <span class="font-medium text-gray-800 dark:text-gray-200">Account owner</span>
                                                — {{ invitation.account.owner }}
                                            </p>
                                            <p>
                                                <span class="font-medium text-gray-800 dark:text-gray-200">Invited</span>
                                                — {{ new Date(invitation.created_at).toLocaleDateString() }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-3 sm:flex-row lg:flex-col lg:items-stretch">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-secondary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-secondary-700"
                                        @click="acceptInvitation(invitation)"
                                    >
                                        <span class="material-icons text-lg leading-none">check_circle</span>
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        @click="declineInvitation(invitation)"
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Accounts -->
                <section>
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                                Accounts
                            </p>
                            <h3 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">Your accounts</h3>
                        </div>
                        <Link
                            :href="route('checkout.plans')"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
                        >
                            <span class="material-icons text-lg leading-none">add</span>
                            Create account
                        </Link>
                    </div>

                    <div
                        v-if="accounts.length === 0"
                        class="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800/50"
                    >
                        <div
                            class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                        >
                            <span class="material-icons text-3xl leading-none text-gray-400 dark:text-gray-500"
                                >domain</span
                            >
                        </div>
                        <p class="text-base font-medium text-gray-900 dark:text-white">No accounts yet</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Choose a plan to create your first account and invite your team.
                        </p>
                        <Link
                            :href="route('checkout.plans')"
                            class="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
                        >
                            View plans
                            <span class="material-icons text-lg leading-none">arrow_forward</span>
                        </Link>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="flex flex-col rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ account.name }}
                                    </h4>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                        <span
                                            v-if="account.is_owner"
                                            class="inline-flex items-center rounded-full bg-secondary-100 px-2.5 py-0.5 text-xs font-medium text-secondary-800 dark:bg-secondary-900/40 dark:text-secondary-200"
                                        >
                                            Owner
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-200"
                                        >
                                            {{ account.user_role }}
                                        </span>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span
                                            >{{ account.users_count }}
                                            {{ account.users_count === 1 ? 'user' : 'users' }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/50"
                                >
                                    <span class="material-icons text-xl leading-none text-gray-500 dark:text-gray-400"
                                        >business</span
                                    >
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col gap-3">
                                <div
                                    v-if="account.domain"
                                    class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700/30"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tenant URL</p>
                                        <p class="truncate font-mono text-sm text-gray-900 dark:text-white">
                                            {{ account.domain }}
                                        </p>
                                    </div>
                                    <a
                                        :href="getTenantUrl(account.domain)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 transition hover:border-primary-400 hover:bg-primary-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-primary-500"
                                    >
                                        Open
                                        <span class="material-icons text-sm leading-none">open_in_new</span>
                                    </a>
                                </div>

                                <div v-if="account.is_owner" class="mt-auto space-y-2 pt-1">
                                    <Link
                                        :href="route('accounts.show', account.id)"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                                    >
                                        <span class="material-icons text-lg leading-none">settings</span>
                                        Manage account
                                    </Link>
                                    <button
                                        type="button"
                                        class="w-full rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60"
                                        @click="cancelAccount(account)"
                                    >
                                        Cancel subscription
                                    </button>
                                </div>
                                <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                    You are a
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ account.user_role }}</span>
                                    on this account.
                                </p>
                            </div>

                            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Created {{ new Date(account.created_at).toLocaleDateString() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="isConfirmModal ? 'dashboard-modal-title' : 'dashboard-modal-result-title'"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm"
                    aria-label="Close dialog"
                    @click="closeModal"
                />
                <div
                    class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 dark:border-gray-700"
                        :class="
                            isConfirmModal
                                ? 'bg-gray-50 dark:bg-gray-800/80'
                                : modalType === 'success'
                                  ? 'bg-secondary-50 dark:bg-secondary-950/30'
                                  : 'bg-red-50 dark:bg-red-950/20'
                        "
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                v-if="!isConfirmModal && modalType === 'success'"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-secondary-100 dark:bg-secondary-900/50"
                            >
                                <span class="material-icons text-secondary-600 dark:text-secondary-400">check_circle</span>
                            </div>
                            <div
                                v-else-if="!isConfirmModal && modalType === 'error'"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50"
                            >
                                <span class="material-icons text-red-600 dark:text-red-400">error_outline</span>
                            </div>
                            <div
                                v-else
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40"
                            >
                                <span class="material-icons text-primary-600 dark:text-primary-400">help_outline</span>
                            </div>
                            <h3
                                :id="isConfirmModal ? 'dashboard-modal-title' : 'dashboard-modal-result-title'"
                                class="text-lg font-bold text-gray-900 dark:text-white"
                            >
                                {{ modalTitle }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            aria-label="Close"
                            @click="closeModal"
                        >
                            <span class="material-icons text-xl leading-none">close</span>
                        </button>
                    </div>
                    <div class="px-6 py-5">
                        <p class="text-base leading-relaxed text-gray-600 dark:text-gray-300">
                            {{ modalMessage }}
                        </p>
                    </div>
                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                        <button
                            v-if="isConfirmModal"
                            type="button"
                            class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                        <button
                            v-if="isConfirmModal"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="isProcessing"
                            @click="handleConfirm"
                        >
                            <svg
                                v-if="isProcessing"
                                class="-ml-1 h-4 w-4 animate-spin text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            {{ isProcessing ? 'Processing…' : 'Confirm' }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition"
                            :class="
                                modalType === 'success'
                                    ? 'bg-secondary-600 hover:bg-secondary-700'
                                    : 'bg-red-600 hover:bg-red-700'
                            "
                            @click="closeModal"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
