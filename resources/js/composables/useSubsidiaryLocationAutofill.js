import { nextTick, onScopeDispose, watch } from 'vue';

/**
 * @param {import('@inertiajs/vue3').InertiaForm|Record<string, unknown>} form
 * @param {Record<string, { filterby?: string }>|(() => Record<string, { filterby?: string }>|null)|null} [fieldsSchema]
 * @param {{
 *   enabled?: boolean|(() => boolean),
 *   guard?: () => boolean,
 *   assumeFiltered?: boolean,
 * }} [options]
 */
export function useSubsidiaryLocationAutofill(form, fieldsSchema = null, options = {}) {
    let requestSeq = 0;
    let active = true;

    onScopeDispose(() => {
        active = false;
        requestSeq += 1;
    });

    const locationFilteredBySubsidiary = () => {
        if (options.assumeFiltered === true) {
            return true;
        }

        const schema = typeof fieldsSchema === 'function' ? fieldsSchema() : fieldsSchema;
        const def = schema?.location_id;
        if (!def) {
            return false;
        }

        return def.filterby === 'subsidiary_id';
    };

    const isEnabled = () => {
        const flag = options.enabled;
        if (typeof flag === 'function') {
            return flag();
        }
        if (flag === false) {
            return false;
        }

        return locationFilteredBySubsidiary();
    };

    const mayRun = () => {
        if (!active || !isEnabled()) {
            return false;
        }
        if (typeof options.guard === 'function' && !options.guard()) {
            return false;
        }

        return true;
    };

    watch(
        () => form.subsidiary_id,
        (newVal, oldVal) => {
            if (!mayRun()) {
                return;
            }

            const subsidiaryChanged = oldVal !== undefined && newVal !== oldVal;
            const seq = ++requestSeq;

            void (async () => {
                try {
                    if (subsidiaryChanged) {
                        await nextTick();
                        if (!mayRun() || seq !== requestSeq) {
                            return;
                        }
                        if (form.location_id != null && form.location_id !== '') {
                            form.location_id = null;
                        }
                    }

                    if (!newVal) {
                        return;
                    }

                    if (!subsidiaryChanged && form.location_id) {
                        return;
                    }

                    const soleLocationId = await fetchSoleLocationIdForSubsidiary(newVal);
                    if (!mayRun() || seq !== requestSeq) {
                        return;
                    }

                    if (soleLocationId != null && Number(form.location_id) !== soleLocationId) {
                        await nextTick();
                        if (!mayRun() || seq !== requestSeq) {
                            return;
                        }
                        form.location_id = soleLocationId;
                    }
                } catch {
                    // Ignore lookup failures; user can still pick a location manually.
                }
            })();
        },
        { immediate: true, flush: 'post' },
    );
}

/**
 * @param {string|number} subsidiaryId
 * @returns {Promise<number|null>}
 */
export async function fetchSoleLocationIdForSubsidiary(subsidiaryId) {
    if (subsidiaryId == null || subsidiaryId === '') {
        return null;
    }

    const subsidiary = Number(subsidiaryId);
    if (!Number.isFinite(subsidiary) || subsidiary <= 0) {
        return null;
    }

    const url = new URL(route('records.lookup'), window.location.origin);
    url.searchParams.append('type', 'location');
    url.searchParams.append('page', '1');
    url.searchParams.append('per_page', '2');
    url.searchParams.append('order_by', 'display_name');
    url.searchParams.append('order_direction', 'asc');
    url.searchParams.append('filters', JSON.stringify([{
        field: 'subsidiary_id',
        operator: 'equals',
        value: subsidiary,
    }]));

    const response = await fetch(url.toString(), {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return null;
    }

    const data = await response.json();
    const records = data.records ?? [];
    const total = data.meta?.total;

    if (total === 1 || (total == null && records.length === 1)) {
        const id = Number(records[0]?.id);
        return Number.isFinite(id) && id > 0 ? id : null;
    }

    return null;
}
