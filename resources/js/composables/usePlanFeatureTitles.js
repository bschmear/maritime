/**
 * @param {unknown} features
 * @returns {string[]}
 */
export function planFeatureTitles(features) {
    if (!Array.isArray(features)) {
        return [];
    }

    return features
        .map((item) => {
            if (typeof item === 'string') {
                return item.trim();
            }

            if (item && typeof item === 'object') {
                return String(item.title ?? '').trim();
            }

            return '';
        })
        .filter(Boolean);
}
