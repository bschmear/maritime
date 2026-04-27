#!/usr/bin/env node
/**
 * Converts scraped `<ul class="opts">` HTML into JSON objects `{ "slug": "Label", ... }`.
 *
 * Manufacturers: checkbox `value` + anchor `make-all-link`.
 * Boat types: checkbox `value` + anchor `class-power-link` | `class-sail-link` | `class-pwc-link` | `class-small-link`.
 * Hull types: checkbox `value` + HullShape row + anchor href …hulltype… or `model-link`.
 * Hull materials: checkbox `value` + HullMaterial row + anchor href `/boats/hull-…` (not hulltype) or `model-link`.
 *
 * Default run (no args):
 *   - manufacturer_alt.json → manufacturer_alt.map.json + app/Domain/BoatMake/Schema/manufacturers.json
 *   - app/Domain/BoatMake/Schema/boat_type.json → app/Domain/BoatMake/Schema/boat_types.json
 *   - app/Domain/BoatMake/Schema/hull_type.json → app/Domain/BoatMake/Schema/hull_types.json
 *   - app/Domain/BoatMake/Schema/hull_material.json → app/Domain/BoatMake/Schema/hull_materials.json
 *
 * Custom single file:
 *   node scripts/manufacturer-alt-to-json.mjs path/to/input.html out.json
 *   (link pattern is inferred from filename: hull_material, hull_type, boat_type, else make-all-link)
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(__dirname, '..');

const defaultManufacturerInput = join(
    repoRoot,
    'resources/js/Pages/Tenant/BoatMake/manufacturer_alt.json'
);
const defaultManufacturerOutputMap = join(
    repoRoot,
    'resources/js/Pages/Tenant/BoatMake/manufacturer_alt.map.json'
);
const defaultManufacturerOutputSchema = join(
    repoRoot,
    'app/Domain/BoatMake/Schema/manufacturers.json'
);

const defaultBoatTypeInput = join(repoRoot, 'app/Domain/BoatMake/Schema/boat_type.json');
const defaultBoatTypeOutput = join(repoRoot, 'app/Domain/BoatMake/Schema/boat_types.json');

const defaultHullTypeInput = join(repoRoot, 'app/Domain/BoatMake/Schema/hull_type.json');
const defaultHullTypeOutput = join(repoRoot, 'app/Domain/BoatMake/Schema/hull_types.json');

const defaultHullMaterialInput = join(repoRoot, 'app/Domain/BoatMake/Schema/hull_material.json');
const defaultHullMaterialOutput = join(repoRoot, 'app/Domain/BoatMake/Schema/hull_materials.json');

/** @type {{ source: string, test: (block: string) => RegExpMatchArray | null }} */
const LINK_MODES = {
    make: {
        source: 'make-all-link',
        test: (block) =>
            /<a\b[^>]*class="[^"]*make-all-link[^"]*"[^>]*>([\s\S]*?)<\/a>/i.exec(block),
    },
    boatType: {
        source: 'class-(power|sail|pwc|small)-link',
        test: (block) =>
            /<a\b[^>]*class="[^"]*class-(?:power|sail|pwc|small)-link[^"]*"[^>]*>([\s\S]*?)<\/a>/i.exec(
                block
            ),
    },
    hullType: {
        source: 'HullShape row + anchor href …hulltype… or class model-link',
        test: (block) =>
            /<a\b[^>]*href="[^"]*hulltype[^"]*"[^>]*>([\s\S]*?)<\/a>/i.exec(block) ??
            /<a\b[^>]*class="[^"]*\bmodel-link\b[^"]*"[^>]*>([\s\S]*?)<\/a>/i.exec(block),
    },
    hullMaterial: {
        source: 'HullMaterial row + anchor href /boats/hull-… (not hulltype) or model-link',
        test: (block) =>
            /<a\b[^>]*href="[^"]*\/boats\/hull-[^"]*"[^>]*>([\s\S]*?)<\/a>/i.exec(block) ??
            /<a\b[^>]*class="[^"]*\bmodel-link\b[^"]*"[^>]*>([\s\S]*?)<\/a>/i.exec(block),
    },
};

function decodeHtmlEntities(raw) {
    if (!raw) {
        return '';
    }
    return raw
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;/g, "'")
        .replace(/&apos;/gi, "'")
        .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n)))
        .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCodePoint(parseInt(h, 16)));
}

function stripTags(s) {
    return s.replace(/<[^>]+>/g, '').trim();
}

/**
 * @param {string} html
 * @param {'make' | 'boatType' | 'hullType' | 'hullMaterial'} mode
 */
function parseOptsListHtml(html, mode) {
    const linkTest = LINK_MODES[mode].test;
    const map = Object.create(null);
    const duplicates = [];

    const liRe = /<li\b[^>]*>([\s\S]*?)<\/li>/gi;
    let m;
    while ((m = liRe.exec(html)) !== null) {
        const block = m[1];
        if (mode === 'hullType' && !/\bHullShape/i.test(block)) {
            continue;
        }
        if (mode === 'hullMaterial' && !/\bHullMaterial/i.test(block)) {
            continue;
        }
        const valueMatch = block.match(/\bvalue="([^"]*)"/);
        if (!valueMatch) {
            continue;
        }
        const value = valueMatch[1];
        const linkMatch = linkTest(block);
        const rawLabel = linkMatch ? linkMatch[1] : '';
        const label = decodeHtmlEntities(stripTags(rawLabel));

        if (map[value] !== undefined && map[value] !== label) {
            duplicates.push({ key: value, previous: map[value], next: label });
        }
        map[value] = label;
    }

    return { map, duplicates };
}

function sortedJsonFromMap(map) {
    const sortedKeys = Object.keys(map).sort((a, b) => a.localeCompare(b));
    const sorted = Object.fromEntries(sortedKeys.map((k) => [k, map[k]]));
    return { json: `${JSON.stringify(sorted, null, 2)}\n`, count: sortedKeys.length };
}

function inferModeFromPath(inputPath) {
    const n = basename(inputPath).toLowerCase();
    if (n.includes('hull_material') || n.includes('hull-material')) {
        return 'hullMaterial';
    }
    if (n.includes('hull_type') || n.includes('hull-type')) {
        return 'hullType';
    }
    if (n.includes('boat_type') || n.includes('boat-type')) {
        return 'boatType';
    }
    return 'make';
}

function warnDuplicates(duplicates, label) {
    if (duplicates.length > 0) {
        console.warn(
            `[${label}] Warning: ${duplicates.length} duplicate value(s); last label wins. Sample:`,
            duplicates.slice(0, 3)
        );
    }
}

const inputPathArg = process.argv[2];
const outputPathArg = process.argv[3];

if (inputPathArg && outputPathArg) {
    const inputPath = resolve(inputPathArg);
    const outPath = resolve(outputPathArg);
    const mode = inferModeFromPath(inputPath);
    const html = readFileSync(inputPath, 'utf8');
    const { map, duplicates } = parseOptsListHtml(html, mode);
    warnDuplicates(duplicates, basename(inputPath));
    const { json, count } = sortedJsonFromMap(map);
    writeFileSync(outPath, json, 'utf8');
    console.log(`Read: ${inputPath} (${LINK_MODES[mode].source})`);
    console.log(`Wrote ${count} entries → ${outPath}`);
} else {
    if (existsSync(defaultManufacturerInput)) {
        const mHtml = readFileSync(defaultManufacturerInput, 'utf8');
        const m = parseOptsListHtml(mHtml, 'make');
        warnDuplicates(m.duplicates, 'manufacturers');
        const mOut = sortedJsonFromMap(m.map);
        writeFileSync(defaultManufacturerOutputMap, mOut.json, 'utf8');
        writeFileSync(defaultManufacturerOutputSchema, mOut.json, 'utf8');
        console.log(`Read: ${defaultManufacturerInput}`);
        console.log(
            `Wrote ${mOut.count} manufacturer entries → ${defaultManufacturerOutputMap} and ${defaultManufacturerOutputSchema}`
        );
    } else {
        console.warn(`Skip manufacturers: missing input ${defaultManufacturerInput}`);
    }

    if (existsSync(defaultBoatTypeInput)) {
        const bHtml = readFileSync(defaultBoatTypeInput, 'utf8');
        const b = parseOptsListHtml(bHtml, 'boatType');
        warnDuplicates(b.duplicates, 'boat types');
        const bOut = sortedJsonFromMap(b.map);
        writeFileSync(defaultBoatTypeOutput, bOut.json, 'utf8');
        console.log(`Read: ${defaultBoatTypeInput}`);
        console.log(`Wrote ${bOut.count} boat type entries → ${defaultBoatTypeOutput}`);
    } else {
        console.warn(`Skip boat types: missing input ${defaultBoatTypeInput}`);
    }

    if (existsSync(defaultHullTypeInput)) {
        const hHtml = readFileSync(defaultHullTypeInput, 'utf8');
        const h = parseOptsListHtml(hHtml, 'hullType');
        warnDuplicates(h.duplicates, 'hull types');
        const hOut = sortedJsonFromMap(h.map);
        writeFileSync(defaultHullTypeOutput, hOut.json, 'utf8');
        console.log(`Read: ${defaultHullTypeInput}`);
        console.log(`Wrote ${hOut.count} hull type entries → ${defaultHullTypeOutput}`);
        if (hOut.count === 0) {
            console.warn(
                'No HullShape rows found. Hull scrape must use checkbox ids like HullShape-catamaran (boat-type <li> blocks are ignored).'
            );
        }
    } else {
        console.warn(`Skip hull types: missing input ${defaultHullTypeInput}`);
    }

    if (existsSync(defaultHullMaterialInput)) {
        const hmHtml = readFileSync(defaultHullMaterialInput, 'utf8');
        const hm = parseOptsListHtml(hmHtml, 'hullMaterial');
        warnDuplicates(hm.duplicates, 'hull materials');
        const hmOut = sortedJsonFromMap(hm.map);
        writeFileSync(defaultHullMaterialOutput, hmOut.json, 'utf8');
        console.log(`Read: ${defaultHullMaterialInput}`);
        console.log(`Wrote ${hmOut.count} hull material entries → ${defaultHullMaterialOutput}`);
        if (hmOut.count === 0) {
            console.warn(
                'No HullMaterial rows found. Scrape must use ids like HullMaterial-aluminum (HullShape / boat-type <li> blocks are ignored).'
            );
        }
    } else {
        console.warn(`Skip hull materials: missing input ${defaultHullMaterialInput}`);
    }
}
