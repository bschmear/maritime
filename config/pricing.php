<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Features included on every subscription tier
    |--------------------------------------------------------------------------
    |
    | Shown on /pricing under "All tiers include". Override in kiosk
    | (Pricing settings) or via the pricing_settings table.
    |
    */

    'all_tiers' => [
        'title' => 'All tiers include',
        'subtitle' => 'Core CRM and operations tools every Helmful workspace receives.',
        'features' => [
            [
                'title' => 'Leads, contacts & customers',
                'description' => 'Capture, assign, and manage your pipeline with full history on every record.',
            ],
            [
                'title' => 'Opportunities, estimates & contracts',
                'description' => 'Quote deals, send for approval and signature, and move sales forward in one place.',
            ],
            [
                'title' => 'Invoices & payments',
                'description' => 'Bill customers, record payments, and keep revenue tied to the deal.',
            ],
            [
                'title' => 'Inventory & assets',
                'description' => 'Track boats, units, specs, options, and consignment inventory across locations.',
            ],
            [
                'title' => 'Service tickets & work orders',
                'description' => 'Run the service department with tickets, labor, parts, and yard scheduling.',
            ],
            [
                'title' => 'Deliveries',
                'description' => 'Schedule deliveries, use checklists, and capture customer signatures on handoff.',
            ],
            [
                'title' => 'Tasks & documents',
                'description' => 'Stay on top of follow-ups with tasks and a central document library.',
            ],
            [
                'title' => 'Operations dashboard & reports',
                'description' => 'See action items, revenue, and financial and sales reports at a glance.',
            ],
            [
                'title' => 'Transactional email',
                'description' => 'Send branded emails for estimates, invoices, deliveries, and customer notifications.',
            ],
            [
                'title' => 'Team users & roles',
                'description' => 'Invite your staff with role-based access across your dealership.',
            ],
        ],
    ],

];
