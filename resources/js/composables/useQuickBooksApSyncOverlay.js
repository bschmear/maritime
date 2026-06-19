import { getCurrentInstance } from 'vue';

export const QUICKBOOKS_AP_SYNC_LOADING_MESSAGE =
    'Bills and bill payments are synced with QuickBooks. Creating the record in QuickBooks and will import it when complete.';

export function quickBooksApSyncFailureMessage(entityLabel, detail = null) {
    let message =
        `QuickBooks sync failed. The ${entityLabel} was saved in Helmful, but we could not confirm it in QuickBooks.`;
    if (detail) {
        message += ` ${detail}`;
    }
    return `${message} Please open QuickBooks Online and check whether the ${entityLabel} is there before trying again.`;
}

/**
 * Full-screen loading overlay + failure toasts for AP record create → QuickBooks push.
 *
 * @param {{ enabled?: import('vue').Ref<boolean>|boolean, entityLabel?: string }} options
 */
export function useQuickBooksApSyncOverlay(options = {}) {
    const instance = getCurrentInstance();

    const root = () => instance?.appContext?.app?._instance?.proxy;

    const isEnabled = () => {
        const value = options.enabled;
        if (value == null) {
            return false;
        }
        return typeof value === 'object' && 'value' in value ? Boolean(value.value) : Boolean(value);
    };

    const entityLabel = () => options.entityLabel || 'record';

    const showLoading = (message = QUICKBOOKS_AP_SYNC_LOADING_MESSAGE) => {
        root()?.showLoading?.(message);
    };

    const hideLoading = () => {
        root()?.hideLoading?.();
    };

    const showToast = (type, message) => {
        if (!message) {
            return;
        }
        root()?.createToast?.(type, String(message));
    };

    const beginCreateSync = () => {
        if (!isEnabled()) {
            return;
        }
        showLoading();
    };

    const endCreateSync = () => {
        hideLoading();
    };

    const handleCreateResponse = (data) => {
        endCreateSync();
        const sync = data?.quickbooks_sync;
        if (sync && sync.success === false) {
            showToast('error', quickBooksApSyncFailureMessage(entityLabel(), sync.message || null));
        }
    };

    const handleCreateFlash = (flash) => {
        if (flash?.quickbooks_sync_error) {
            showToast('error', flash.quickbooks_sync_error);
        }
    };

    return {
        beginCreateSync,
        endCreateSync,
        handleCreateResponse,
        handleCreateFlash,
        quickBooksApSyncFailureMessage,
    };
}
