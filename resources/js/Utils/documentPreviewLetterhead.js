/**
 * Shared subsidiary + location resolution for customer-facing document previews
 * (invoices, contracts, service tickets, estimates, deliveries, etc.).
 */

export function resolvePreviewSubsidiary(record) {
    if (!record) {
        return null;
    }

    return record.subsidiary ?? record.transaction?.subsidiary ?? null;
}

export function resolvePreviewLocation(record) {
    if (!record) {
        return null;
    }

    return record.location ?? record.transaction?.location ?? null;
}

export function previewSubsidiaryName(record, fallback = 'Company Name') {
    const name = resolvePreviewSubsidiary(record)?.display_name?.trim();
    return name || fallback;
}

export function locationBlockFromObject(loc) {
    if (!loc) {
        return null;
    }

    const line1 = String(loc.address_line_1 ?? loc.address_line1 ?? '').trim();
    const line2 = String(loc.address_line_2 ?? loc.address_line2 ?? '').trim();
    const city = String(loc.city ?? '').trim();
    const state = String(loc.state ?? '').trim();
    const postal = String(loc.postal_code ?? loc.postalCode ?? '').trim();
    const phone = String(loc.phone ?? '').trim();
    const email = String(loc.email ?? '').trim();

    if (!line1 && !city && !phone && !email) {
        return null;
    }

    return { line1, line2, city, state, postal, phone, email };
}

export function previewLocationBlock(record) {
    return locationBlockFromObject(resolvePreviewLocation(record));
}

export function previewLocationPhone(record, accountPhone = null) {
    const fromLocation = previewLocationBlock(record)?.phone;
    if (fromLocation) {
        return fromLocation;
    }

    return accountPhone || null;
}
