import axios from 'axios';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const DISMISS_STORAGE_KEY = 'web_push_opt_in_dismissed_at';
const DISMISS_DURATION_MS = 7 * 24 * 60 * 60 * 1000;

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
}

export function useWebPush() {
    const page = usePage();
    const isSupported = ref(false);
    const permission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'default');
    const isSubscribed = ref(false);
    const isLoading = ref(false);
    const errorMessage = ref(null);

    const vapidPublicKey = computed(() => page.props.vapid_public_key ?? null);
    const serverEnabled = computed(() => Boolean(vapidPublicKey.value));

    const canPrompt = computed(() => {
        if (!isSupported.value || !serverEnabled.value) {
            return false;
        }

        if (permission.value === 'denied' || isSubscribed.value) {
            return false;
        }

        try {
            const dismissedAt = Number(localStorage.getItem(DISMISS_STORAGE_KEY) || 0);
            if (dismissedAt && Date.now() - dismissedAt < DISMISS_DURATION_MS) {
                return false;
            }
        } catch {
            // ignore storage errors
        }

        return true;
    });

    const detectSupport = () => {
        isSupported.value = (
            typeof window !== 'undefined'
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && typeof Notification !== 'undefined'
        );
    };

    const refreshStatus = async () => {
        if (!isSupported.value || !serverEnabled.value) {
            isSubscribed.value = false;
            return;
        }

        try {
            const { data } = await axios.get(route('notifications.push.status'));
            isSubscribed.value = Boolean(data.subscribed);
        } catch {
            isSubscribed.value = false;
        }
    };

    const subscribe = async () => {
        if (!isSupported.value || !serverEnabled.value) {
            return false;
        }

        isLoading.value = true;
        errorMessage.value = null;

        try {
            const result = await Notification.requestPermission();
            permission.value = result;

            if (result !== 'granted') {
                return false;
            }

            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
                });
            }

            const json = subscription.toJSON();

            await axios.post(route('notifications.push.subscribe'), {
                endpoint: json.endpoint,
                keys: json.keys,
                content_encoding: 'aesgcm',
            });

            isSubscribed.value = true;
            localStorage.removeItem(DISMISS_STORAGE_KEY);

            return true;
        } catch (err) {
            errorMessage.value = err.response?.data?.message || err.message || 'Could not enable push notifications.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const unsubscribe = async () => {
        if (!isSupported.value) {
            return false;
        }

        isLoading.value = true;
        errorMessage.value = null;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await axios.delete(route('notifications.push.unsubscribe'), {
                    data: { endpoint: subscription.endpoint },
                });
                await subscription.unsubscribe();
            }

            isSubscribed.value = false;

            return true;
        } catch (err) {
            errorMessage.value = err.response?.data?.message || err.message || 'Could not disable push notifications.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const dismissPrompt = () => {
        try {
            localStorage.setItem(DISMISS_STORAGE_KEY, String(Date.now()));
        } catch {
            // ignore storage errors
        }
    };

    return {
        isSupported,
        permission,
        isSubscribed,
        isLoading,
        errorMessage,
        vapidPublicKey,
        serverEnabled,
        canPrompt,
        detectSupport,
        refreshStatus,
        subscribe,
        unsubscribe,
        dismissPrompt,
    };
}
