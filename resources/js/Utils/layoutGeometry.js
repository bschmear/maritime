/** @typedef {{ x: number, y: number }} LayoutPoint */

/**
 * @param {number} w
 * @param {number} h
 * @returns {LayoutPoint[]}
 */
export function defaultRectPerimeter(w, h) {
    return [
        { x: 0, y: 0 },
        { x: w, y: 0 },
        { x: w, y: h },
        { x: 0, y: h },
    ];
}

/**
 * @param {LayoutPoint[]} polygon
 * @param {number} w
 * @param {number} h
 */
export function isDefaultRectPerimeter(polygon, w, h) {
    if (!Array.isArray(polygon) || polygon.length !== 4) {
        return false;
    }
    const expected = defaultRectPerimeter(w, h);

    return expected.every((pt, i) => {
        const p = polygon[i];

        return p && Math.round(p.x) === pt.x && Math.round(p.y) === pt.y;
    });
}

/**
 * @param {number} px
 * @param {number} py
 * @param {LayoutPoint[]} polygon
 */
export function pointInPolygon(px, py, polygon) {
    if (!polygon?.length) {
        return true;
    }

    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const xi = polygon[i].x;
        const yi = polygon[i].y;
        const xj = polygon[j].x;
        const yj = polygon[j].y;
        const intersect =
            yi > py !== yj > py && px < ((xj - xi) * (py - yi)) / (yj - yi + 0.0) + xi;
        if (intersect) {
            inside = !inside;
        }
    }

    return inside;
}

/**
 * @param {{ x: number, y: number, w: number, h: number }} footprint
 * @param {LayoutPoint[]} polygon
 */
export function footprintOutsidePolygon(footprint, polygon) {
    if (!polygon?.length) {
        return false;
    }

    const corners = [
        { x: footprint.x, y: footprint.y },
        { x: footprint.x + footprint.w, y: footprint.y },
        { x: footprint.x + footprint.w, y: footprint.y + footprint.h },
        { x: footprint.x, y: footprint.y + footprint.h },
    ];

    return corners.some((c) => !pointInPolygon(c.x, c.y, polygon));
}

/**
 * Closest point on polygon edges; insert only when within maxDistFt of an edge.
 * @param {number} x ft
 * @param {number} y ft
 * @param {LayoutPoint[]} polygon
 * @param {number} maxDistFt
 */
export function nearestEdgeInsertIfClose(x, y, polygon, maxDistFt = 4) {
    if (!polygon?.length) {
        return null;
    }

    let best = null;
    let bestDist = Infinity;

    for (let i = 0; i < polygon.length; i++) {
        const a = polygon[i];
        const b = polygon[(i + 1) % polygon.length];
        const abx = b.x - a.x;
        const aby = b.y - a.y;
        const lenSq = abx * abx + aby * aby;
        let t = lenSq === 0 ? 0 : ((x - a.x) * abx + (y - a.y) * aby) / lenSq;
        t = Math.max(0, Math.min(1, t));
        const px = a.x + t * abx;
        const py = a.y + t * aby;
        const dx = x - px;
        const dy = y - py;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < bestDist) {
            bestDist = dist;
            best = { index: i + 1, point: { x: Math.round(px), y: Math.round(py) } };
        }
    }

    if (best && bestDist <= maxDistFt) {
        return best;
    }

    return null;
}
