/** Millimetre helpers for length fields stored as integers. */

const MM_PER_IN = 25.4;

/**
 * @param {number | null | undefined} mm
 * @returns {{ feet: number, inches: number } | null}
 */
export function mmToImperialParts(mm) {
    if (mm == null || mm === '' || !Number.isFinite(Number(mm)) || Number(mm) < 0) {
        return null;
    }
    const n = Math.round(Number(mm) / MM_PER_IN);
    let feet = Math.floor(n / 12);
    let inches = n - feet * 12;
    if (inches === 12) {
        feet += 1;
        inches = 0;
    }
    return { feet, inches };
}

/**
 * @param {number | null | undefined} feet
 * @param {number | null | undefined} inches
 * @returns {number | null}
 */
export function imperialFeetInchesToMm(feet, inches) {
    const fEmpty = feet === '' || feet == null;
    const iEmpty = inches === '' || inches == null;
    if (fEmpty && iEmpty) {
        return null;
    }
    const f = fEmpty ? 0 : Number(feet);
    const i = iEmpty ? 0 : Number(inches);
    if (!Number.isFinite(f) || !Number.isFinite(i)) {
        return null;
    }
    if (f < 0 || i < 0 || i > 11) {
        return null;
    }
    const totalIn = f * 12 + i;
    if (totalIn <= 0) {
        return null;
    }
    return Math.round(totalIn * MM_PER_IN);
}

/**
 * @param {number | null | undefined} mm
 * @returns {string}
 */
export function formatLengthMmImperial(mm) {
    if (mm == null || mm === '' || !Number.isFinite(Number(mm)) || Number(mm) < 0) {
        return '—';
    }
    const parts = mmToImperialParts(mm);
    if (!parts) {
        return '—';
    }
    return `${parts.feet} ft ${parts.inches} in`;
}

/**
 * @param {number | null | undefined} mm
 * @returns {string}
 */
export function formatLengthMmMetric(mm) {
    if (mm == null || mm === '' || !Number.isFinite(Number(mm)) || Number(mm) < 0) {
        return '—';
    }
    const n = Number(mm);
    if (n < 1000) {
        return `${Math.round(n)} mm`;
    }
    const metres = n / 1000;
    const decimals = metres >= 100 ? 1 : 2;
    const s = parseFloat(metres.toFixed(decimals));
    return `${s} m`;
}

/**
 * @param {number | null | undefined} mm
 * @param {'imperial' | 'metric' | 'both'} [mode='imperial']
 * @returns {string}
 */
export function formatLengthMmForDisplay(mm, mode = 'imperial') {
    const imp = formatLengthMmImperial(mm);
    const met = formatLengthMmMetric(mm);
    if (imp === '—' && met === '—') {
        return '—';
    }
    if (mode === 'imperial') {
        return imp;
    }
    if (mode === 'metric') {
        return met;
    }
    if (imp === '—') {
        return met;
    }
    if (met === '—') {
        return imp;
    }
    return `${imp} · ${met}`;
}
