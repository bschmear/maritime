import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appName = env.VITE_APP_NAME || 'Laravel';
    const shortName =
        appName.length > 12 ? appName.replace(/\s+/g, ' ').slice(0, 12).trim() : appName;

    return {
        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            VitePWA({
                // Emit SW at `public/sw.js` → URL /sw.js so default max scope is `/` (Herd/nginx
                // won’t get Service-Worker-Allowed from .htaccess). Web manifest stays in
                // public/build/ — linked from Blade as /build/manifest.webmanifest.
                base: '/',
                scope: '/',
                outDir: 'public',
                buildBase: '/',
                // Emitted to Vite outDir as manifest.webmanifest → public/build/manifest.webmanifest.
                // Precache entry must be build/manifest.webmanifest (same URL as <link rel="manifest">).

                registerType: 'autoUpdate',
                injectRegister: 'auto',
                /**
                 * Icons under public/; no precache focus — installability only.
                 */
                includeManifestIcons: false,
                workbox: {
                    globPatterns: [],
                    runtimeCaching: [],
                    // Default globDirectory is `outDir` (public/). Point at Vite output only so
                    // workbox does not treat all of public/ as the precache root (huge, breaks build).
                    globDirectory: path.join(__dirname, 'public/build'),
                    // Laravel + Inertia: no `index.html` in precache; default SPA handler breaks.
                    navigateFallback: null,
                    // Avoid workbox’s index.html / directory heuristics.
                    directoryIndex: null,
                },

                manifest: {
                    name: appName,
                    short_name: shortName,
                    description: 'Progressive web app',
                    start_url: '/?pwa=1',
                    scope: '/',
                    display: 'standalone',
                    theme_color: '#1f2937',
                    background_color: '#1f2937',
                    orientation: 'any',
                    icons: [
                        {
                            src: '/assets/icons/android-chrome-192x192.png',
                            sizes: '192x192',
                            type: 'image/png',
                        },
                        {
                            src: '/assets/icons/android-chrome-512x512.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'any',
                        },
                        {
                            src: '/assets/icons/android-chrome-512x512.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'maskable',
                        },
                    ],
                },

                // Dev SW is only served on the Vite dev origin, not on *.test / Laravel. Enabling
                // it causes GET /build/dev-sw.js?dev-sw → 404 on Herd. Use `npm run build` to test PWA.
                devOptions: {
                    enabled: false,
                },
            }),
        ],
    };
});
