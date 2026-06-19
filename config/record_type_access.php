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
    | Types omitted from this list are allowed for all roles. Listed types
    | require a matching role slug (or superadmin_slugs).
    |
    */
    'types' => [
        'survey' => [
            // 'admin',
            // 'manager',
        ],
        'financing' => [
            'admin',
            'manager',
        ],
        'bill' => [
            'admin',
            'manager',
        ],
        'billpayment' => [
            'admin',
            'manager',
        ],
    ],
];
