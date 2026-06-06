/**
 * @param {{ line1?: string, line2?: string, city_state_zip?: string, country?: string }} parts
 * @param {'single' | 'multiline'} layout
 */
export function formatMsoCustomerAddress(parts, layout = 'multiline') {
    const line1 = String(parts?.line1 ?? '').trim();
    const line2 = String(parts?.line2 ?? '').trim();
    const cityStateZip = String(parts?.city_state_zip ?? '').trim();
    const country = String(parts?.country ?? '').trim();

    const segments = [line1, line2, cityStateZip, country].filter(Boolean);

    if (layout === 'single') {
        return segments.join(', ');
    }

    return segments.join('\n');
}

/**
 * @param {{ signature?: { method?: string, url?: string, typed_signature?: string } | null, display_name?: string }} user
 */
export function msoSignatureFieldPatch(user) {
    const signature = user?.signature;
    if (!signature?.method && !signature?.url && !signature?.typed_signature) {
        return {
            value: '',
            signature_method: null,
            signature_url: null,
        };
    }

    if (signature.method === 'type') {
        return {
            value: String(signature.typed_signature ?? ''),
            signature_method: 'type',
            signature_url: null,
        };
    }

    return {
        value: String(signature.typed_signature ?? user?.display_name ?? ''),
        signature_method: 'draw',
        signature_url: signature.url ?? null,
    };
}
