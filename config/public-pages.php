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

    'default_og_image' => env(
        'PUBLIC_OG_IMAGE',
        'https://dazhky881zoxr.cloudfront.net/public/posts/4df26f65-b9d3-47dc-aa19-58722f16ac6a.jpg',
    ),

];
