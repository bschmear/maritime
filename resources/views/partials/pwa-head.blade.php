{{-- PWA: manifest + meta (generated assets live in /public/build/ via vite-plugin-pwa) --}}
{{-- Browsers also request /favicon.ico; served by FaviconController on every host. --}}
<link rel="icon" type="image/x-icon" href="/brand/icons/favicon.ico" />
<link rel="icon" type="image/png" sizes="32x32" href="/brand/icons/favicon-32x32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="/brand/icons/favicon-16x16.png" />
<link rel="manifest" href="/manifest.webmanifest" />
<meta name="theme-color" content="#1f2937" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
<link rel="apple-touch-icon" href="/brand/icons/apple-touch-icon.png" />
