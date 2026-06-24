export function getResourceRouteParamName(recordType) {
    if (recordType === 'boat-shows') {
        return 'boatShow';
    }
    if (recordType === 'boat-show-events') {
        return 'event';
    }
    if (recordType === 'boat-show-layouts') {
        return 'layout';
    }
    if (recordType === 'asset-options') {
        return 'assetOption';
    }

    let routeType = recordType;
    if (recordType.includes('.')) {
        const parts = recordType.split('.');
        routeType = parts[parts.length - 1];
    }

    if (routeType === 'subsidiaries') {
        return 'subsidiary';
    }
    if (routeType === 'contactaddresses') {
        return 'contactaddress';
    }
    if (routeType === 'delivery-locations') {
        return 'deliveryLocation';
    }
    if (routeType === 'bill-payments') {
        return 'billPayment';
    }
    if (routeType === 'billpayments') {
        return 'billPayment';
    }

    // Plurals ending in consonant + ies (e.g. opportunities → opportunity). A lone trailing
    // .replace(/s$/, '') would yield "opportunitie" and break Ziggy route binding.
    if (routeType.endsWith('ies')) {
        return routeType.slice(0, -3) + 'y';
    }

    return routeType.replace(/s$/, '');
}

export function buildResourceRouteParams(recordType, recordId, extra = {}) {
    const paramName = getResourceRouteParamName(recordType);
    return { ...extra, [paramName]: recordId };
}

const irregularPlurals = {
    Subsidiary: 'subsidiaries',
    subsidiary: 'subsidiaries',
    ContactAddress: 'contactaddresses',
    contactaddress: 'contactaddresses',
    DeliveryLocation: 'delivery-locations',
    deliverylocation: 'delivery-locations',
    delivery_location: 'delivery-locations',
    BillPayment: 'bill-payments',
    billpayment: 'bill-payments',
    BoatShow: 'boat-shows',
    boatshow: 'boat-shows',
    BoatShowEvent: 'boat-show-events',
    boatshowevent: 'boat-show-events',
    BoatShowLayout: 'boat-show-layouts',
    boatshowlayout: 'boat-show-layouts',
};

/** Map a schema typeDomain (e.g. "Asset", "Customer") to the tenant route plural segment. */
export function getDomainPlural(domain) {
    if (!domain) {
        return '';
    }
    if (irregularPlurals[domain]) {
        return irregularPlurals[domain];
    }
    const lowercase = String(domain).toLowerCase();
    if (/[^aeiou]y$/.test(lowercase)) {
        return `${lowercase.slice(0, -1)}ies`;
    }
    return lowercase.endsWith('s') ? lowercase : `${lowercase}s`;
}

/**
 * Build the show-page URL for a related record field (schema type "record").
 *
 * @param {string} typeDomain - e.g. "Asset", "Customer", "AssetVariant"
 * @param {number|string} recordId
 * @param {{ assetId?: number|string }} [context] - required for AssetVariant nested routes
 */
/** Morph model class → Ziggy show route (when getDomainPlural does not match). */
const MORPH_SHOW_ROUTE_BY_MODEL = {
    'App\\Domain\\BoatShowEvent\\Models\\BoatShowEvent': {
        route: 'boat-show-events.show',
        recordType: 'boat-show-events',
    },
};

/**
 * Build the show-page URL for a task morph relatable (relatable_type + relatable_id).
 *
 * @param {string} morphType - Laravel morph class, e.g. App\Domain\Lead\Models\Lead
 * @param {number|string} recordId
 * @param {{ domain?: string }} [morphConfig] - entry from fields schema morphable_types
 */
export function buildMorphShowUrl(morphType, recordId, morphConfig = null) {
    if (!morphType || recordId == null || recordId === '') {
        return null;
    }

    try {
        const mapped = MORPH_SHOW_ROUTE_BY_MODEL[morphType];
        if (mapped) {
            return route(mapped.route, buildResourceRouteParams(mapped.recordType, recordId));
        }

        const domain = morphConfig?.domain;
        if (domain) {
            return buildRecordShowUrl(domain, recordId);
        }

        return null;
    } catch {
        return null;
    }
}

export function buildRecordShowUrl(typeDomain, recordId, context = {}) {
    if (!typeDomain || recordId == null || recordId === '') {
        return null;
    }

    if (typeDomain === 'AssetVariant') {
        const assetId = context.assetId;
        if (!assetId) {
            return null;
        }
        return route('assets.variants.show', {
            asset: assetId,
            variant: recordId,
        });
    }

    const routePlural = getDomainPlural(typeDomain);
    const routeName = `${routePlural}.show`;

    return route(routeName, buildResourceRouteParams(routePlural, recordId));
}
