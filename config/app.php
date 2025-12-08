<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'domain' => env('APP_DOMAIN', 'localhost'),
    'app_url' => env('APP_TENANT'),
    'api_url' => env('APP_API'),
    'admin_url' => env('APP_ADMIN'),

    'timezone' => 'UTC',
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
    "extra_seats" => [
        "monthly_price_id" => env('EXTRA_SEAT_MONTHLY_ID'),
        "yearly_price_id" => env('EXTRA_SEAT_YEARLY_ID'),
        "monthly_price" => env('EXTRA_SEAT_MONTHLY_PRICE'),
        "yearly_price" => env('EXTRA_SEAT_YEARLY_PRICE')
    ]
];