<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public marketing site SEO defaults
    |--------------------------------------------------------------------------
    |
    | Used by App\Support\PublicPageMeta for Inertia meta + JSON-LD in app.blade.php.
    |
    */

    'site_name' => env('PUBLIC_SITE_NAME', env('APP_NAME', 'Helmful')),

    'default_og_image' => env('PUBLIC_OG_IMAGE', '/assets/icons/android-chrome-512x512.png'),

];
