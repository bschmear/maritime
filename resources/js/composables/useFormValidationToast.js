import { getCurrentInstance, nextTick } from 'vue';
import { toastValidationErrors, validationErrorsToMessage } from '@/Utils/toastValidation';

function humanizeFieldKey(key) {
    return key
        .replace(/_id$/, '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Build labeled messages from an Inertia/Laravel error bag (for inline summaries).
 *
 * @param {Record<string, string|string[]>|undefined} errors
 * @param {Record<string, { label?: string }>|null} [fieldsSchema]
 * @returns {string[]}
 */
export function buildFormErrorMessages(errors, fieldsSchema = null) {
    const messages = [];

    for (const [key, value] of Object.entries(errors ?? {})) {
        if (!value) {
            continue;
        }
        const text = Array.isArray(value) ? value[0] : String(value);
        if (!text) {
            continue;
        }
        const label = fieldsSchema?.[key]?.label ?? humanizeFieldKey(key);
        messages.push(key === 'general' ? text : `${label}: ${text}`);
    }

    return messages;
}

/**
 * Toast + scroll helpers for Inertia form validation failures.
 *
 * @param {(() => Record<string, { label?: string }>|null)|Record<string, { label?: string }>|null} [getFieldsSchema]
 */
export function useFormValidationToast(getFieldsSchema = () => null) {
    const instance = getCurrentInstance();

    const resolveFieldsSchema = (override) => {
        if (override) {
            return override;
        }
        if (typeof getFieldsSchema === 'function') {
            return getFieldsSchema();
        }

        return getFieldsSchema;
    };

    const showToast = (type, message) => {
        instance?.proxy?.$toast?.(type, message);
    };

    const scrollToFirstFormError = (errors, selector = '[data-form-validation-error]') => {
        nextTick(() => {
            const summary = document.querySelector(selector);
            if (summary) {
                summary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }
            const firstKey = Object.keys(errors ?? {}).find((k) => errors[k]);
            if (firstKey && firstKey !== 'general') {
                document.getElementById(firstKey)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    };

    const handleSubmitErrors = (errors, options = {}) => {
        const schema = resolveFieldsSchema(options.fieldsSchema);
        toastValidationErrors(errors, schema, showToast);
        scrollToFirstFormError(errors, options.errorSelector);
    };

    const validationMessage = (errors, fieldsSchema) =>
        validationErrorsToMessage(errors, resolveFieldsSchema(fieldsSchema));

    const errorMessages = (errors, fieldsSchema) =>
        buildFormErrorMessages(errors, resolveFieldsSchema(fieldsSchema));

    const validationSubmitOptions = (options = {}) => ({
        preserveScroll: options.preserveScroll !== false,
        ...options,
        onError: (errors) => {
            handleSubmitErrors(errors, options);
            options.onError?.(errors);
        },
    });

    return {
        showToast,
        handleSubmitErrors,
        validationMessage,
        errorMessages,
        validationSubmitOptions,
        scrollToFirstFormError,
    };
}
