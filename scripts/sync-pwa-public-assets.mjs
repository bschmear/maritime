/**
 * After `vite build`, the PWA plugin emits the web manifest under public/build/ and workbox
 * precaches it as `manifest.webmanifest` (URL /manifest.webmanifest). Copy to public root.
 * Favicons: Blade uses /assets/icons/; GET /favicon.ico is served by FaviconController.
 */
import { copyFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const fromManifest = join(root, 'public/build/manifest.webmanifest');
const toManifest = join(root, 'public/manifest.webmanifest');

if (existsSync(fromManifest)) {
    copyFileSync(fromManifest, toManifest);
} else {
    console.warn('sync-pwa-public-assets: missing', fromManifest, '(skip manifest copy)');
}
