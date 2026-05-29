import { ref, computed } from 'vue';
import { getCurrentInstance } from 'vue';

const DISMISS_KEY = 'pwa_install_dismissed';
const DISMISS_DAYS = 14;

function readDismissed() {
    if (typeof localStorage === 'undefined') {
        return false;
    }

    const raw = localStorage.getItem(DISMISS_KEY);
    if (!raw) {
        return false;
    }

    const dismissedAt = Number(raw);
    if (!Number.isFinite(dismissedAt)) {
        localStorage.removeItem(DISMISS_KEY);
        return false;
    }

    const maxAge = DISMISS_DAYS * 24 * 60 * 60 * 1000;
    if (Date.now() - dismissedAt > maxAge) {
        localStorage.removeItem(DISMISS_KEY);
        return false;
    }

    return true;
}

const dismissed = ref(typeof window !== 'undefined' ? readDismissed() : false);
const showInstructions = ref(false);
const nativeInstallAvailable = ref(false);
const isMobileViewport = ref(
    typeof window !== 'undefined'
        ? window.matchMedia('(max-width: 1023px)').matches
        : false,
);

function isStandalone() {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true
    );
}

function isIos() {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function notify(type, message) {
    const instance = getCurrentInstance();
    instance?.appContext.config.globalProperties.$toast?.(type, message);
}

function onBeforeInstallPrompt() {
    nativeInstallAvailable.value = true;
    // Do not call preventDefault — let the browser show its native install UI.
}

function onAppInstalled() {
    nativeInstallAvailable.value = true;
    dismissed.value = true;
    showInstructions.value = false;
}

if (typeof window !== 'undefined') {
    const mobileQuery = window.matchMedia('(max-width: 1023px)');
    mobileQuery.addEventListener('change', (event) => {
        isMobileViewport.value = event.matches;
    });
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);
}

export function usePwaInstall() {
    const showManualInstall = computed(() => {
        if (dismissed.value || isStandalone() || nativeInstallAvailable.value) {
            return false;
        }

        return isMobileViewport.value && isIos();
    });

    const promptInstall = async () => {
        if (isStandalone()) {
            notify('info', 'App is already installed.');
            return null;
        }

        if (nativeInstallAvailable.value) {
            return false;
        }

        if (isIos()) {
            showInstructions.value = true;
            return null;
        }

        return false;
    };

    const dismiss = () => {
        dismissed.value = true;
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
        showInstructions.value = false;
    };

    const closeInstructions = () => {
        showInstructions.value = false;
    };

    return {
        showManualInstall,
        showInstructions,
        promptInstall,
        dismiss,
        closeInstructions,
    };
}
