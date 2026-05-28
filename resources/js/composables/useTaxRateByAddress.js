import { ref } from 'vue';

/**
 * Normalize billing state to a compact jurisdiction code (e.g. FL) for QBO / integrations.
 *
 * @param {string} state
 * @returns {string}
 */
export function normalizeTaxJurisdictionCode(state) {
    const raw = String(state ?? '').trim();
    if (!raw) {
        return '';
    }
    if (/^[A-Za-z]{2}$/.test(raw)) {
        return raw.toUpperCase();
    }
    return raw;
}

/**
 * Build a human-readable tax jurisdiction label from address fields (display only).
 *
 * @param {object} fields
 * @returns {string}
 */
export function buildTaxJurisdictionFromAddress(fields = {}) {
    const city = String(fields.city ?? '').trim();
    const st = String(fields.state ?? fields.stateCode ?? '').trim();
    const pc = String(fields.postal_code ?? fields.postalCode ?? '').trim();
    const country = String(fields.country ?? fields.countryCode ?? '').trim();

    if (city && st) {
        const base = [city, st, pc].filter(Boolean).join(', ');
        return country && country !== 'US' ? `${base} (${country})` : base;
    }
    if (st && pc) return `${st} ${pc}`;
    if (st) return country && country !== 'US' ? `${st} (${country})` : st;
    return '';
}

/**
 * Apply tax lookup API response to a form (rate + jurisdiction label + code).
 *
 * @param {object} form
 * @param {object|null} data
 * @param {{ state?: string, city?: string, postal_code?: string, country?: string }} [fallback]
 */
export function applyTaxLookupToForm(form, data, fallback = {}) {
    if (!data) {
        return;
    }

    if (data.tax_rate != null && !Number.isNaN(Number(data.tax_rate))) {
        form.tax_rate = Number(data.tax_rate);
    }

    const label = data.jurisdiction_label
        ?? buildTaxJurisdictionFromAddress({
            city: fallback.city,
            state: fallback.state,
            postal_code: fallback.postal_code,
            country: fallback.country,
        });
    if (label) {
        form.tax_jurisdiction = label;
    }

    const code = data.jurisdiction_code
        ?? normalizeTaxJurisdictionCode(fallback.state ?? '');
    if (code) {
        form.tax_jurisdiction_code = code;
    }
}

/**
 * Composable for fetching a sales tax rate from billing address fields or a saved location.
 *
 * @param {string} routeName - Named Ziggy route for address-based lookup
 * @param {string|null} locationRouteName - Named route for location_id lookup (defaults to routeName)
 */
export function useTaxRateByAddress(routeName = 'estimates.address-tax-rate', locationRouteName = null) {
    const isFetching = ref(false);
    const locationRoute = locationRouteName ?? routeName;

    const fetchJson = async (url) => {
        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        return response.json();
    };

    /**
     * @param {object|string} address
     * @returns {Promise<{tax_rate: number|null, jurisdiction_code: string|null, jurisdiction_label: string|null}|null>}
     */
    const fetchTaxRate = async (address) => {
        const fields = typeof address === 'string'
            ? { state: address }
            : address;

        if (!fields?.state) return null;

        isFetching.value = true;
        try {
            const url = new URL(route(routeName), window.location.origin);
            url.searchParams.append('state', fields.state);
            if (fields.city) url.searchParams.append('city', fields.city);
            if (fields.postal_code) url.searchParams.append('postal_code', fields.postal_code);
            if (fields.line1) url.searchParams.append('line1', fields.line1);
            if (fields.country) url.searchParams.append('country', fields.country);
            if (fields.latitude) url.searchParams.append('latitude', fields.latitude);
            if (fields.longitude) url.searchParams.append('longitude', fields.longitude);

            const data = await fetchJson(url);
            return {
                tax_rate: data.tax_rate ?? null,
                jurisdiction_code: data.jurisdiction_code ?? null,
                jurisdiction_label: data.jurisdiction_label ?? null,
            };
        } catch (error) {
            console.error('Failed to fetch tax rate by address:', error);
            return null;
        } finally {
            isFetching.value = false;
        }
    };

    /**
     * @returns {Promise<{tax_rate: number|null, jurisdiction_code: string|null, jurisdiction_label: string|null}|null>}
     */
    const fetchTaxRateByLocation = async (locationId) => {
        if (!locationId) return null;

        isFetching.value = true;
        try {
            const url = new URL(route(locationRoute), window.location.origin);
            url.searchParams.append('location_id', String(locationId));
            const data = await fetchJson(url);
            return {
                tax_rate: data.tax_rate ?? null,
                jurisdiction_code: data.jurisdiction_code ?? null,
                jurisdiction_label: data.jurisdiction_label ?? null,
            };
        } catch (error) {
            console.error('Failed to fetch tax rate by location:', error);
            return null;
        } finally {
            isFetching.value = false;
        }
    };

    return {
        fetchTaxRate,
        fetchTaxRateByLocation,
        isFetching,
        buildTaxJurisdictionFromAddress,
        normalizeTaxJurisdictionCode,
        applyTaxLookupToForm,
    };
}
