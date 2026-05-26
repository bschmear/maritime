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
