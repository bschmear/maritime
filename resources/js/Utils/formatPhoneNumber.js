/**
 * Format a phone string as (XXX) XXX-XXXX (US-style, first 10 digits).
 */
export function formatPhoneNumber(value) {
    if (!value) return '';
    const numbers = String(value).replace(/\D/g, '');
    if (numbers.length <= 3) {
        return numbers;
    }
    if (numbers.length <= 6) {
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3)}`;
    }
    return `(${numbers.slice(0, 3)}) ${numbers.slice(3, 6)}-${numbers.slice(6, 10)}`;
}
