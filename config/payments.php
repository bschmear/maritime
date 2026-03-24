<?php

return [
    'providers' => [
        'stripe' => [
            'label' => 'Stripe',
            'supports' => ['cards', 'ach'],
            'onboarding' => 'redirect',
        ],
        'quickbooks' => [
            'label' => 'QuickBooks Payments',
            'supports' => ['invoicing'],
            'onboarding' => 'oauth',
        ],
    ],
];
