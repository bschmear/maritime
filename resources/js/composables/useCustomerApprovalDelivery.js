import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

/**
 * Email / email+SMS delivery modal for customer approval sends (estimates, service tickets, etc.).
 */
export function useCustomerApprovalDelivery({ postRoute, recordId, customerEmail, smsOffer }) {
    const page = usePage();
    const showModal = ref(false);
    const delivery = ref('email');
    const sendForm = useForm({ delivery: 'email' });

    const emailPreview = computed(() => {
        if (page.props.tenant_sandbox_mode) {
            return page.props.auth?.user?.email ?? '';
        }

        const email = typeof customerEmail === 'function' ? customerEmail() : customerEmail?.value ?? customerEmail;

        return email ?? '';
    });

    const modalSubtitle = computed(() =>
        page.props.tenant_sandbox_mode
            ? 'Sandbox is on: choose how you want to receive the test. Email and SMS go to you, not the customer.'
            : 'Choose how to notify the customer.',
    );

    const openModal = () => {
        delivery.value = 'email';
        showModal.value = true;
    };

    const closeModal = () => {
        showModal.value = false;
    };

    const resolveRecordId = () => (typeof recordId === 'function' ? recordId() : recordId);

    const confirmSend = ({ onSuccess, onError, preserveScroll = true } = {}) => {
        const id = resolveRecordId();
        if (!id) {
            return;
        }

        sendForm.delivery = delivery.value;
        sendForm.post(postRoute(id), {
            preserveScroll,
            preserveState: true,
            // Avoid re-fetching the full show page in production (large payload / slow round-trip).
            only: ['flash'],
            onSuccess: (page) => {
                onSuccess?.(page);
            },
            onError: (errors) => {
                onError?.(errors);
            },
            onFinish: () => {
                if (!sendForm.hasErrors) {
                    closeModal();
                }
            },
        });
    };

    const deliveryError = computed(() => {
        const d = sendForm.errors.delivery;
        const e = sendForm.errors.error;
        const message = d || e || '';

        return Array.isArray(message) ? message[0] : message;
    });

    const resolvedSmsOffer = computed(() => {
        if (typeof smsOffer === 'function') {
            return smsOffer();
        }

        return smsOffer?.value ?? smsOffer ?? { offered: false, hint: null };
    });

    return {
        showModal,
        delivery,
        sendForm,
        emailPreview,
        modalSubtitle,
        deliveryError,
        smsOffer: resolvedSmsOffer,
        openModal,
        closeModal,
        confirmSend,
    };
}
