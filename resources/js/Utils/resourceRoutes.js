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

    return routeType.replace(/s$/, '');
}

export function buildResourceRouteParams(recordType, recordId, extra = {}) {
    const paramName = getResourceRouteParamName(recordType);
    return { ...extra, [paramName]: recordId };
}
