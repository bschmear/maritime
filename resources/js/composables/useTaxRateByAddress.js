import { ref } from 'vue';

/**
 * Composable for fetching a sales tax rate from billing address fields.
 *
 * Calls the unified GeneralController::getTaxRate endpoint, sending the full
 * address for future county-level accuracy when Stripe Tax is integrated.
 *
 * @param {string} routeName - Named Ziggy route (defaults to estimates.address-tax-rate)
 */
export function useTaxRateByAddress(routeName = 'estimates.address-tax-rate') {
    const isFetching = ref(false);

    /**
     * @param {object|string} address - Either a full address object
     *   { state, city, postal_code, latitude, longitude }
     *   or a plain state string for backwards compatibility.
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
            if (fields.city)         url.searchParams.append('city',         fields.city);
            if (fields.postal_code)  url.searchParams.append('postal_code',  fields.postal_code);
            if (fields.line1)        url.searchParams.append('line1',       fields.line1);
            if (fields.country)      url.searchParams.append('country',     fields.country);
            if (fields.latitude)     url.searchParams.append('latitude',     fields.latitude);
            if (fields.longitude)    url.searchParams.append('longitude',    fields.longitude);

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            return data.tax_rate ?? null;
        } catch (error) {
            console.error('Failed to fetch tax rate by address:', error);
            return null;
        } finally {
            isFetching.value = false;
        }
    };

    return { fetchTaxRate, isFetching };
}
