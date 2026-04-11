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
