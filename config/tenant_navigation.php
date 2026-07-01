<?php

/**
 * Default tenant secondary navigation tree.
 *
 * Used to seed the default navigation_menus row for new tenants and as the
 * route catalog source for the navigation menu builder.
 *
 * Each node:
 * - label (required)
 * - route (optional) — Laravel route name; omit for grouping-only parents
 * - children (optional) — nested nodes
 */
return [
    ['label' => 'Overview', 'route' => 'dashboard'],

    [
        'label' => 'Sales',
        'children' => [
            ['label' => 'Overview', 'route' => 'sales.index'],
            ['label' => 'Opportunities', 'route' => 'opportunities.index'],
            ['label' => 'Estimates', 'route' => 'estimates.index'],
            ['label' => 'Contracts', 'route' => 'contracts.index'],
            ['label' => 'Transactions', 'route' => 'transactions.index'],
            ['label' => 'MSO', 'route' => 'mso.index'],
            ['label' => 'Invoices', 'route' => 'invoices.index'],
            ['label' => 'Payments', 'route' => 'payments.index'],
            [
                'label' => 'Bills',
                'route' => 'bills.index',
                'children' => [
                    ['label' => 'All Bills', 'route' => 'bills.index'],
                    ['label' => 'Bill Payments', 'route' => 'bill-payments.index'],
                    ['label' => 'Chart of accounts', 'route' => 'chart-of-accounts.index'],
                ],
            ],
        ],
    ],

    [
        'label' => 'Reports',
        'children' => [
            [
                'label' => 'Financial',
                'children' => [
                    ['label' => 'Profit & Loss', 'route' => 'reports.pnl'],
                    ['label' => 'Cash Flow', 'route' => 'reports.cash-flow'],
                    ['label' => 'Sales Tax Liability', 'route' => 'reports.sales-tax-liability'],
                    ['label' => 'Sales Tax Payable', 'route' => 'reports.sales-tax-payable'],
                    ['label' => 'Financing Report', 'route' => 'reports.financing'],
                ],
            ],
            [
                'label' => 'Sales',
                'children' => [
                    ['label' => 'Sales by Customer', 'route' => 'reports.sales-by-customer'],
                    ['label' => 'Sales by Item (Summary)', 'route' => 'reports.sales-by-item-summary'],
                    ['label' => 'Sales by Item (Detail)', 'route' => 'reports.sales-by-item-detail'],
                ],
            ],
        ],
    ],

    [
        'label' => 'Operations',
        'children' => [
            [
                'label' => 'Service Yard',
                'children' => [
                    ['label' => 'Overview', 'route' => 'serviceyard.index'],
                    ['label' => 'Service Tickets', 'route' => 'servicetickets.index'],
                    ['label' => 'Work Orders', 'route' => 'workorders.index'],
                    ['label' => 'Service Items', 'route' => 'serviceitems.index'],
                    ['label' => 'Scheduler', 'route' => 'serviceyard.scheduling'],
                ],
            ],
            ['label' => 'Warranty claims', 'route' => 'warrantyclaims.index'],
            [
                'label' => 'Deliveries',
                'children' => [
                    ['label' => 'All Deliveries', 'route' => 'deliveries.index'],
                    ['label' => 'Delivery Requests', 'route' => 'deliveries.requests.index'],
                    ['label' => 'Delivery Scheduler', 'route' => 'deliveries.delivery-schedule'],
                    ['label' => 'Common Locations', 'route' => 'delivery-locations.index'],
                    ['label' => 'Templates', 'route' => 'delivery-checklist-templates.index'],
                ],
            ],
            [
                'label' => 'Shipments',
                'route' => 'shipments.index',
                'requires_integration' => 'easypost',
            ],
            [
                'label' => 'Fleet',
                'children' => [
                    ['label' => 'All Units', 'route' => 'fleet.index'],
                    ['label' => 'Maintenance', 'route' => 'fleet.maintenance.index'],
                ],
            ],
            ['label' => 'Qualifications', 'route' => 'qualifications.index'],
        ],
    ],

    [
        'label' => 'Inventory',
        'children' => [
            ['label' => 'All Assets', 'route' => 'assets.index'],
            ['label' => 'All Units', 'route' => 'assets.units.global-index'],
            ['label' => 'Financing', 'route' => 'financings.index'],
            ['label' => 'Consignment agreements', 'route' => 'consignmentagreements.index'],
            ['label' => 'Asset Brands', 'route' => 'boatmakes.index'],
            ['label' => 'Asset Options', 'route' => 'asset-options.index'],
            ['label' => 'Asset Specifications', 'route' => 'asset-specs.index'],
            ['label' => 'Parts & Accessories', 'route' => 'inventoryitems.index'],
        ],
    ],

    [
        'label' => 'Relationships',
        'children' => [
            ['label' => 'Contacts', 'route' => 'contacts.index'],
            ['label' => 'Leads', 'route' => 'leads.index'],
            ['label' => 'Customers', 'route' => 'customers.index'],
            ['label' => 'Vendors', 'route' => 'vendors.index'],
            [
                'label' => 'Surveys',
                'children' => [
                    ['label' => 'All Surveys', 'route' => 'surveysIndex'],
                    ['label' => 'Create', 'route' => 'surveysCreate'],
                    ['label' => 'Responses', 'route' => 'surveyResponses'],
                ],
            ],
        ],
    ],

    [
        'label' => 'Boat Shows',
        'children' => [
            ['label' => 'All Shows', 'route' => 'boat-shows.index'],
            ['label' => 'Events', 'route' => 'boat-show-events.index'],
            ['label' => 'Follow-up emails', 'route' => 'boat-show-email-templates.index'],
        ],
    ],

    [
        'label' => 'Productivity',
        'children' => [
            ['label' => 'Tasks', 'route' => 'tasks.index'],
            ['label' => 'Documents', 'route' => 'documents.index'],
        ],
    ],

    [
        'label' => 'Company',
        'route' => 'account.index',
        'children' => [
            ['label' => 'Overview', 'route' => 'account.index'],
            ['label' => 'Integrations', 'route' => 'integrations'],
            ['label' => 'Locations', 'route' => 'locations.index'],
            ['label' => 'Users', 'route' => 'users.index'],
            ['label' => 'Roles', 'route' => 'roles.index'],
            ['label' => 'Subsidiaries', 'route' => 'subsidiaries.index'],
        ],
    ],
];
