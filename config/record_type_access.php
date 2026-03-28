<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Superadmin role slugs
    |--------------------------------------------------------------------------
    |
    | Tenant role slugs that may access every record type below (optional).
    | Uses the `slug` column on the tenant `roles` table.
    |
    */
    'superadmin_slugs' => [
        // 'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Record type → allowed role slugs
    |--------------------------------------------------------------------------
    |
    | Keys must match App\Enums\RecordType case values (e.g. "survey").
    | Omitted types are denied for everyone except superadmin_slugs.
    |
    */
    'types' => [
        'survey' => [
            // 'admin',
            // 'manager',
        ],
    ],
];
