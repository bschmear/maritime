/** Matches stored mention tokens: @[Display Name](user:123) */
export const MENTION_TOKEN_PATTERN = /@\[([^\]]+)\]\(user:(\d+)\)/g;

/**
 * Convert stored body to friendly text for the composer / plain display.
 */
export function displayBodyFromStorage(body) {
    if (!body) {
        return '';
    }
    return body.replace(MENTION_TOKEN_PATTERN, '@$1');
}

/**
 * Encode @Name segments from the composer into storage tokens (first match per mention).
 *
 * @param {string} text
 * @param {{ displayName: string, userId: number }[]} mentions
 */
export function encodeMentionsForStorage(text, mentions) {
    let result = text;
    for (const m of mentions) {
        const display = `@${m.displayName}`;
        const token = `@[${m.displayName}](user:${m.userId})`;
        const idx = result.indexOf(display);
        if (idx !== -1) {
            result = result.slice(0, idx) + token + result.slice(idx + display.length);
        }
    }

    return result;
}
