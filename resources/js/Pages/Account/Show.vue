<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    account: {
        type: Object,
        required: true,
    },
    users: {
        type: Array,
        required: true,
    },
    current_plan: {
        type: Object,
        default: null,
    },
    seat_usage: {
        type: Object,
        required: true,
    },
    plans: {
        type: Array,
        required: true,
    },
    additional_seat_cost: {
        type: Number,
        required: true,
    },
    pending_invitations: {
        type: Array,
        default: () => [],
    },
    current_user: {
        type: Object,
        required: true,
    },
});

const showAddUserModal = ref(false);
const showSwitchPlanModal = ref(false);
const newUserEmail = ref('');
const newUserRole = ref('member');
const selectedPlanId = ref('');
const billingCycle = ref('monthly');
const isSwitchingPlan = ref(false);
const isInvitingUser = ref(false);
const removingUserId = ref(null);
const updatingUserId = ref(null);
const resendingInvitation = ref(null);
const deletingInvitation = ref(null);

// Modal state
const showModal = ref(false);
const modalType = ref('success'); // 'success' or 'error'
const modalTitle = ref('');
const modalMessage = ref('');
const confirmAction = ref(null);
const isConfirmModal = ref(false);
const isProcessing = ref(false);

const breadcrumbItems = computed(() => {
    return [
        { label: 'Dashboard', href: route('dashboard') },
        { label: 'Account: ' + props.account.name },
    ];
});

// Show confirmation modal
const showConfirmModal = (title, message, onConfirm) => {
    modalTitle.value = title;
    modalMessage.value = message;
    confirmAction.value = onConfirm;
    isConfirmModal.value = true;
    showModal.value = true;
};

// Show result modal
const showResultModal = (type, title, message) => {
    modalType.value = type;
    modalTitle.value = title;
    modalMessage.value = message;
    isConfirmModal.value = false;
    showModal.value = true;
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
        closeModal();
    }
};

// User management
const inviteUser = () => {
    if (isInvitingUser.value) return;
    isInvitingUser.value = true;

    router.post(route('accounts.users.invite', props.account.id), {
        email: newUserEmail.value,
        role: newUserRole.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddUserModal.value = false;
            newUserEmail.value = '';
            newUserRole.value = 'member';
            isInvitingUser.value = false;
        },
        onError: (errors) => {
            console.error('Error inviting user:', errors);
            isInvitingUser.value = false;
        },
        onFinish: () => {
            isInvitingUser.value = false;
        }
    });
};

const removeUser = (user) => {
    showConfirmModal(
        'Remove User',
        `Are you sure you want to remove ${user.name} from this account? This action cannot be undone.`,
        () => {
            if (removingUserId.value) return;
            removingUserId.value = user.id;

            router.delete(route('accounts.users.destroy', {
                account: props.account.id,
                user: user.id
            }), {
                preserveScroll: true,
                onSuccess: () => {
                    removingUserId.value = null;
                    showResultModal('success', 'User Removed', `${user.name} has been successfully removed from the account.`);
                },
                onError: (errors) => {
                    console.error('Error removing user:', errors);
                    removingUserId.value = null;
                    showResultModal('error', 'Error', 'Failed to remove user. Please try again.');
                },
                onFinish: () => {
                    removingUserId.value = null;
                }
            });
        }
    );
};

const updateUserRole = (user, newRole) => {
    if (updatingUserId.value) return;
    updatingUserId.value = user.id;

    router.patch(route('accounts.users.update-role', {
        account: props.account.id,
        user: user.id
    }), {
        role: newRole,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            updatingUserId.value = null;
        },
        onError: (errors) => {
            console.error('Error updating user role:', errors);
            updatingUserId.value = null;
        },
        onFinish: () => {
            updatingUserId.value = null;
        }
    });
};

// Invitation management
const resendInvitation = (invitation) => {
    if (resendingInvitation.value) return;
    resendingInvitation.value = invitation.id;

    router.post(route('invitations.resend', invitation.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            resendingInvitation.value = null;
            showResultModal('success', 'Invitation Sent', `Invitation has been resent to ${invitation.email}.`);
        },
        onError: (errors) => {
            console.error('Error resending invitation:', errors);
            resendingInvitation.value = null;
            showResultModal('error', 'Error', 'Failed to resend invitation. Please try again.');
        },
        onFinish: () => {
            resendingInvitation.value = null;
        }
    });
};

const deleteInvitation = (invitation) => {
    showConfirmModal(
        'Delete Invitation',
        `Are you sure you want to delete the invitation for ${invitation.email}? This action cannot be undone.`,
        () => {
            if (deletingInvitation.value) return;
            deletingInvitation.value = invitation.id;

            router.delete(route('invitations.destroy', invitation.id), {
                preserveScroll: true,
                onSuccess: () => {
                    deletingInvitation.value = null;
                    showResultModal('success', 'Invitation Deleted', `The invitation for ${invitation.email} has been deleted.`);
                },
                onError: (errors) => {
                    console.error('Error deleting invitation:', errors);
                    deletingInvitation.value = null;
                    showResultModal('error', 'Error', 'Failed to delete invitation. Please try again.');
                },
                onFinish: () => {
                    deletingInvitation.value = null;
                }
            });
        }
    );
};

// Plan management
const switchPlan = () => {
    if (isSwitchingPlan.value) return;
    isSwitchingPlan.value = true;

    router.post(route('accounts.switch-plan', props.account.id), {
        plan_id: selectedPlanId.value,
        billing_cycle: billingCycle.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showSwitchPlanModal.value = false;
            isSwitchingPlan.value = false;
            showResultModal('success', 'Plan Switched!', 'Your subscription plan has been successfully updated and your billing has been adjusted.');
        },
        onError: (errors) => {
            console.error('Error switching plan:', errors);
            const errorMessage = errors?.stripe?.[0] ||
                               errors?.plan?.[0] ||
                               'Failed to switch plan. Please try again.';
            isSwitchingPlan.value = false;
            showResultModal('error', 'Error', errorMessage);
        },
        onFinish: () => {
            isSwitchingPlan.value = false;
        }
    });
};

const openSwitchPlanModal = () => {
    selectedPlanId.value = '';
    billingCycle.value = 'monthly';
    showSwitchPlanModal.value = true;
};

// Computed properties (keep all your existing computed properties)
const availablePlans = computed(() => {
    return props.plans.filter(plan => {
        if (plan.id === props.current_plan?.id) return false;
        if (billingCycle.value === 'yearly' && !plan.yearly_price) return false;
        if (billingCycle.value === 'monthly' && !plan.monthly_price) return false;
        return true;
    });
});

const canSwitchPlan = computed(() => {
    if (!selectedPlanId.value) return false;
    const selectedPlan = props.plans.find(p => p.id == selectedPlanId.value);
    if (!selectedPlan) return false;
    if (selectedPlan.id === props.current_plan?.id) {
        return billingCycle.value !== (props.current_plan?.billing_cycle || 'monthly') &&
               selectedPlan.yearly_price && selectedPlan.monthly_price;
    }
    return true;
});

const totalMonthlyCost = computed(() => {
    const selectedPlan = props.plans.find(p => p.id == selectedPlanId.value);
    if (!selectedPlan) return 0;
    const planCost = billingCycle.value === 'yearly'
        ? (selectedPlan.yearly_price ? Number(selectedPlan.yearly_price) / 12 : Number(selectedPlan.monthly_price))
        : Number(selectedPlan.monthly_price);
    const total = planCost + Number(props.seat_usage.additional_cost || 0);
    return Number(total.toFixed(2));
});

const totalYearlyCost = computed(() => {
    const selectedPlan = props.plans.find(p => p.id == selectedPlanId.value);
    if (!selectedPlan) return 0;
    const yearlyPlanCost = billingCycle.value === 'yearly'
        ? (selectedPlan.yearly_price ? Number(selectedPlan.yearly_price) : Number(selectedPlan.monthly_price) * 12)
        : Number(selectedPlan.monthly_price) * 12;
    const total = yearlyPlanCost + (Number(props.seat_usage.additional_cost || 0) * 12);
    return Number(total.toFixed(2));
});

const yearlySavingsPercent = computed(() => {
    const selectedPlan = props.plans.find(p => p.id == selectedPlanId.value);
    if (!selectedPlan || !selectedPlan.yearly_price) return 0;
    const monthlyTotal = Number(selectedPlan.monthly_price) * 12;
    const yearlyTotal = Number(selectedPlan.yearly_price);
    const savings = ((monthlyTotal - yearlyTotal) / monthlyTotal) * 100;
    return Math.round(savings);
});

const seatUsagePercentage = computed(() => {
    return Math.min(100, (props.seat_usage.current_users / props.seat_usage.seat_limit) * 100);
});

// Handle account cancellation
const cancelAccount = () => {
    showConfirmModal(
        'Cancel Subscription',
        `Are you sure you want to cancel your subscription for "${props.account.name}"? Your subscription will remain active until the end of the current billing period, after which you will lose access to the account and all its data. This action cannot be undone.`,
        () => {
            router.post(route('accounts.cancel', props.account.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    showResultModal(
                        'success',
                        'Subscription Cancelled',
                        'Your subscription has been cancelled. You will retain access until the end of your current billing period.'
                    );
                    router.reload({ only: ['account', 'current_plan', 'seat_usage'] });
                },
                onError: (errors) => {
                    console.error('Error cancelling account:', errors);
                    const errorMessage = errors?.subscription?.[0] || 
                                       errors?.stripe?.[0] || 
                                       'Failed to cancel subscription. Please try again.';
                    showResultModal('error', 'Error', errorMessage);
                    isProcessing.value = false;
                },
                onFinish: () => {
                    isProcessing.value = false;
                }
            });
        }
    );
};
</script>

    <template>
        <Head :title="`Account: ${account.name}`" />

        <AuthenticatedLayout>
            <template #header>
                <div class="col-span-full">
                    <Breadcrumb :items="breadcrumbItems" />

                    <div class="mt-4 lg:flex lg:flex-row lg:items-center lg:gap-2 lg:gap-3 lg:justify-between">
                        <!-- Header Section -->
                        <div class="mb-4">
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">
                                {{ account.name }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Manage your account settings, team members, and subscription
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div v-if="account.is_owner" class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                            <div>
                            <button
                                @click="showAddUserModal = true"
                                type="button"
                                class="w-full inline-flex items-center justify-center text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 sm:px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="hidden sm:inline">Invite User</span>
                                <span class="sm:hidden">Invite</span>
                            </button>
                        </div>
                            <div>
                            <button
                                @click="openSwitchPlanModal"
                                type="button"
                                class="w-full inline-flex items-center justify-center text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 sm:px-5 py-2.5 dark:bg-green-600 dark:hover:bg-green-700 focus:outline-none dark:focus:ring-green-800"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span class="hidden sm:inline">Switch Plan</span>
                                <span class="sm:hidden">Plan</span>
                            </button>
                            </div>
                            <div>
                            <button
                                @click="cancelAccount"
                                type="button"
                                class="w-full inline-flex items-center justify-center text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-4 sm:px-5 py-2.5 dark:bg-red-600 dark:hover:bg-red-700 focus:outline-none dark:focus:ring-red-800"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span class="hidden sm:inline">Cancel Subscription</span>
                                <span class="sm:hidden">Cancel</span>
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="space-y-6 mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 w-full dark:bg-gray-700">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Current Plan Card -->
                    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Plan</h5>
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ current_plan?.name || 'Free' }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ current_plan ? `$${current_plan.monthly_price}/month` : 'No active subscription' }}
                        </p>
                    </div>

                    <!-- Seat Usage Card -->
                    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400">Team Seats</h5>
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ seat_usage.current_users }}<span class="text-xl text-gray-500">/{{ seat_usage.seat_limit }}</span>
                        </p>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mb-2">
                            <div
                                class="h-2.5 rounded-full transition-all"
                                :class="seat_usage.over_limit > 0 ? 'bg-red-600' : 'bg-blue-600'"
                                :style="{ width: seatUsagePercentage + '%' }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400" v-if="seat_usage.over_limit > 0">
                            <span class="text-red-600 font-medium">{{ seat_usage.over_limit }} seats over limit</span>
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400" v-else>
                            {{ seat_usage.available_seats }} seats available
                        </p>
                    </div>

                    <!-- Additional Costs Card -->
                    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400">Additional Costs</h5>
                            <svg class="w-6 h-6" :class="seat_usage.additional_cost > 0 ? 'text-red-600 dark:text-red-500' : 'text-green-600 dark:text-green-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-3xl font-bold mb-2" :class="seat_usage.additional_cost > 0 ? 'text-red-600 dark:text-red-500' : 'text-green-600 dark:text-green-500'">
                            ${{ seat_usage.additional_cost.toFixed(2) }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400" v-if="seat_usage.additional_cost > 0">
                            {{ seat_usage.over_limit }} extra seats @ ${{ additional_seat_cost }}/seat
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400" v-else>
                            No extra charges
                        </p>
                    </div>
                </div>

                <!-- Team Members Section -->
                <div class="p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <div class="flex justify-between mb-6">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                        Team Members
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Manage who has access to this account
                                    </p>
                                </div>
                                <div>
                                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                                        {{ users.length }} members
                                    </span>
                                </div>
                            </div>
                            <div v-if="current_plan && current_plan.seat_extra" class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                            Unlimited Team Growth
                                        </h4>
                                        <div class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                            <p>You can invite unlimited team members. Additional users beyond your {{ current_plan.seat_limit }} included seats are charged ${{ current_plan.seat_extra }}/month each.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">User</th>
                                    <th scope="col" class="px-6 py-3">Email</th>
                                    <th scope="col" class="px-6 py-3">Role</th>
                                    <th scope="col" class="px-6 py-3">Joined</th>
                                    <th scope="col" class="px-6 py-3" v-if="account.is_owner">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                                >
                                    <th scope="row" class="flex items-center px-6 py-4 text-gray-900 whitespace-nowrap dark:text-white">
                                        <div class="relative inline-flex items-center justify-center w-10 h-10 overflow-hidden bg-blue-600 rounded-full dark:bg-blue-500">
                                            <span class="font-medium text-white">
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="pl-3">
                                            <div class="text-base font-semibold flex items-center gap-2">
                                                {{ user.name }}
                                                <span v-if="user.is_owner" class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-green-900 dark:text-green-300">
                                                    Owner
                                                </span>
                                            </div>
                                        </div>
                                    </th>
                                    <td class="px-6 py-4">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="account.is_owner && !user.is_owner" class="relative">
                                            <select
                                                :value="user.role"
                                                @change="updateUserRole(user, $event.target.value)"
                                                :disabled="updatingUserId === user.id"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <option value="member">Member</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <div v-if="updatingUserId === user.id" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <span v-else class="capitalize">{{ user.role }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ new Date(user.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4" v-if="account.is_owner">
                                        <button
                                            v-if="!user.is_owner"
                                            @click="removeUser(user)"
                                            :disabled="removingUserId === user.id"
                                            type="button"
                                            class="font-medium text-red-600 dark:text-red-500 hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span v-if="removingUserId === user.id" class="inline-flex items-center">
                                                <svg class="animate-spin -ml-1 mr-2 h-3 w-3 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Removing...
                                            </span>
                                            <span v-else>Remove</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pending Invitations Section -->
                <div v-if="pending_invitations.length > 0" class="p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                Pending Invitations
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Users who have been invited but haven't responded yet
                            </p>
                        </div>
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-yellow-900 dark:text-yellow-300">
                            {{ pending_invitations.length }} pending
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="invitation in pending_invitations"
                            :key="invitation.id"
                            class="flex items-center justify-between p-4 border border-yellow-200 bg-yellow-50 rounded-lg dark:bg-yellow-900/20 dark:border-yellow-800"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-yellow-200 dark:bg-yellow-800 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ invitation.email }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Invited as {{ invitation.role }} •
                                        Invited by {{ invitation.invited_by?.name || 'Unknown' }} •
                                        {{ new Date(invitation.created_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2" v-if="account.is_owner">
                                <button
                                    @click="resendInvitation(invitation)"
                                    :disabled="resendingInvitation === invitation.id"
                                    class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/40"
                                >
                                    <svg v-if="resendingInvitation === invitation.id" class="animate-spin -ml-1 mr-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {{ resendingInvitation === invitation.id ? 'Sending...' : 'Resend' }}
                                </button>

                                <button
                                    @click="deleteInvitation(invitation)"
                                    :disabled="deletingInvitation === invitation.id"
                                    class="inline-flex items-center px-3 py-1 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/40"
                                >
                                    <svg v-if="deletingInvitation === invitation.id" class="animate-spin -ml-1 mr-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    {{ deletingInvitation === invitation.id ? 'Deleting...' : 'Delete' }}
                                </button>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400" v-else>
                                Pending response
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invite User Modal -->
            <div v-show="showAddUserModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-full bg-gray-900 bg-opacity-50">
                <div class="relative w-full max-w-md max-h-full">
                    <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                        <button @click="showAddUserModal = false" type="button" class="absolute top-3 right-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <div class="px-6 py-6 lg:px-8">
                            <h3 class="mb-4 text-xl font-medium text-gray-900 dark:text-white">
                                Invite User to Account
                            </h3>
                            <form class="space-y-6" @submit.prevent="inviteUser">
                                <div>
                                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email Address</label>
                                    <input
                                        v-model="newUserEmail"
                                        type="email"
                                        id="email"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                        placeholder="user@example.com"
                                        required
                                    >
                                </div>
                                <div>
                                    <label for="role" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role</label>
                                    <select
                                        v-model="newUserRole"
                                        id="role"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                    >
                                        <option value="member">Member</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="flex gap-3">
                                    <button
                                        type="submit"
                                        :disabled="isInvitingUser"
                                        class="w-full text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                                    >
                                        <svg v-if="isInvitingUser" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ isInvitingUser ? 'Sending Invitation...' : 'Send Invitation' }}
                                    </button>
                                    <button @click="showAddUserModal = false" type="button" class="w-full text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Switch Plan Modal -->
            <div v-show="showSwitchPlanModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-full bg-gray-900 bg-opacity-50">
                <div class="relative w-full max-w-md max-h-full">
                    <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                        <button @click="showSwitchPlanModal = false" type="button" class="absolute top-3 right-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <div class="px-6 py-6 lg:px-8">
                            <h3 class="mb-4 text-xl font-medium text-gray-900 dark:text-white">
                                Switch Subscription Plan
                            </h3>
                            <form class="space-y-6" @submit.prevent="switchPlan">
                                <div>
                                    <label for="plan" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Select Plan</label>
                                    <select
                                        v-model="selectedPlanId"
                                        id="plan"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                    >
                                        <option value="">Select a plan...</option>
                                        <option
                                            v-for="plan in availablePlans"
                                            :key="plan.id"
                                            :value="plan.id"
                                        >
                                            {{ plan.name }} -
                                            <span v-if="billingCycle === 'monthly'">
                                                ${{ plan.monthly_price }}/month
                                            </span>
                                            <span v-else-if="plan.yearly_price">
                                                ${{ plan.yearly_price }}/year
                                            </span>
                                            <span v-else>
                                                ${{ (plan.monthly_price * 12).toFixed(2) }}/year
                                            </span>
                                            ({{ plan.seat_limit }} seats)
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="billing" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Billing Cycle</label>
                                    <select
                                        v-model="billingCycle"
                                        id="billing"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly" :disabled="!props.plans.some(p => p.yearly_price)">Yearly</option>
                                    </select>
                                </div>

                                <div v-if="selectedPlanId" class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Plan Summary</h4>
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-600 dark:text-gray-400">Billing cycle:</dt>
            <dd class="font-medium text-gray-900 dark:text-white capitalize">
                {{ billingCycle }}
                <span v-if="billingCycle === 'yearly' && yearlySavingsPercent > 0" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                    Save {{ yearlySavingsPercent }}%
                </span>
            </dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-600 dark:text-gray-400">Base cost:</dt>
            <dd class="font-medium text-gray-900 dark:text-white">
                <span v-if="billingCycle === 'monthly'">${{ Number(plans.find(p => p.id == selectedPlanId)?.monthly_price || 0).toFixed(2) }}/month</span>
                <span v-else>
                    ${{ Number((plans.find(p => p.id == selectedPlanId)?.yearly_price || plans.find(p => p.id == selectedPlanId)?.monthly_price * 12) || 0).toFixed(2) }}/year
                </span>
            </dd>
        </div>
        <div v-if="billingCycle === 'yearly'" class="flex justify-between">
            <dt class="text-gray-600 dark:text-gray-400">Monthly equivalent:</dt>
            <dd class="font-medium text-gray-900 dark:text-white">
                ${{ (Number((plans.find(p => p.id == selectedPlanId)?.yearly_price || plans.find(p => p.id == selectedPlanId)?.monthly_price * 12) || 0) / 12).toFixed(2) }}/month
            </dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-600 dark:text-gray-400">Seats included:</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ plans.find(p => p.id == selectedPlanId)?.seat_limit }}</dd>
        </div>
        <div v-if="seat_usage.over_limit > 0" class="flex justify-between">
            <dt class="text-gray-600 dark:text-gray-400">Additional seats:</dt>
            <dd class="font-medium text-red-600 dark:text-red-400">
                {{ seat_usage.over_limit }} × ${{ Number(additional_seat_cost || 0).toFixed(2) }}
                <span v-if="billingCycle === 'monthly'">= ${{ Number(seat_usage.additional_cost || 0).toFixed(2) }}/month</span>
                <span v-else>= ${{ (Number(seat_usage.additional_cost || 0) * 12).toFixed(2) }}/year</span>
            </dd>
        </div>
        <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-600">
            <dt class="font-semibold text-gray-900 dark:text-white">Total due {{ billingCycle === 'yearly' ? 'annually' : 'monthly' }}:</dt>
            <dd class="font-bold text-lg text-gray-900 dark:text-white">
                <span v-if="billingCycle === 'monthly'">${{ totalMonthlyCost.toFixed(2) }}/month</span>
                <span v-else>${{ totalYearlyCost.toFixed(2) }}/year</span>
            </dd>
        </div>
        <div v-if="billingCycle === 'yearly'" class="flex justify-between text-xs">
            <dt class="text-gray-500 dark:text-gray-500">Monthly breakdown:</dt>
            <dd class="text-gray-500 dark:text-gray-500">${{ totalMonthlyCost.toFixed(2) }}/month</dd>
        </div>
        <div v-if="billingCycle === 'yearly' && yearlySavingsPercent > 0" class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-600">
            <dt class="text-green-600 dark:text-green-400 font-medium">💰 You save:</dt>
            <dd class="text-green-600 dark:text-green-400 font-bold">
                ${{ ((Number(plans.find(p => p.id == selectedPlanId)?.monthly_price || 0) * 12) - Number(plans.find(p => p.id == selectedPlanId)?.yearly_price || 0)).toFixed(2) }}/year ({{ yearlySavingsPercent }}%)
            </dd>
        </div>
    </dl>
</div>

                                <div class="flex gap-3">
                                    <button
                                        type="submit"
                                        :disabled="!canSwitchPlan || isSwitchingPlan"
                                        class="w-full text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                                    >
                                        <svg v-if="isSwitchingPlan" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ isSwitchingPlan ? 'Switching Plan...' : 'Switch Plan' }}
                                    </button>
                                    <button @click="showSwitchPlanModal = false" type="button" class="w-full text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flowbite Modal -->
            <div v-if="showModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-full bg-gray-900 bg-opacity-50">
                <div class="relative w-full max-w-md max-h-full">
                    <!-- Modal content -->
                    <div class="relative bg-white rounded-lg shadow dark:bg-gray-800">
                        <!-- Modal header -->
                        <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t dark:border-gray-600"
                             :class="isConfirmModal ? 'bg-gray-50 dark:bg-gray-700' : modalType === 'success' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                            <div class="flex items-center space-x-3">
                                <!-- Success Icon -->
                                <div v-if="!isConfirmModal && modalType === 'success'" class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <!-- Error Icon -->
                                <div v-else-if="!isConfirmModal && modalType === 'error'" class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <!-- Confirm Icon -->
                                <div v-else class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ modalTitle }}
                                </h3>
                            </div>
                            <button
                                @click="closeModal"
                                type="button"
                                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                            >
                                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                                </svg>
                                <span class="sr-only">Close modal</span>
                            </button>
                        </div>
                        <!-- Modal body -->
                        <div class="p-4 md:p-5 space-y-4">
                            <p class="text-base leading-relaxed text-gray-700 dark:text-gray-300">
                                {{ modalMessage }}
                            </p>
                        </div>
                        <!-- Modal footer -->
                        <div class="flex items-center justify-end p-4 md:p-5 space-x-3 border-t border-gray-200 rounded-b dark:border-gray-600">
                            <button
                                v-if="isConfirmModal"
                                @click="closeModal"
                                type="button"
                                class="py-2.5 px-5 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700"
                            >
                                Cancel
                            </button>
                            <button
                                v-if="isConfirmModal"
                                @click="handleConfirm"
                                type="button"
                                class="text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800"
                            >
                                Confirm
                            </button>
                            <button
                                v-else
                                @click="closeModal"
                                type="button"
                                class="text-white focus:ring-4 focus:outline-none font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                                :class="modalType === 'success' 
                                    ? 'bg-green-700 hover:bg-green-800 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800' 
                                    : 'bg-red-700 hover:bg-red-800 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800'"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    </template>
