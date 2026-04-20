<?php

return [
    'providers' => [
        'stripe' => [
            'label' => 'Stripe',
            'supports' => ['cards', 'ach'],
            'onboarding' => 'redirect',
        ],
    ],
];
