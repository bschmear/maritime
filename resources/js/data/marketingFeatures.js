/**
 * Marketing feature catalog (features index + nav mega menu).
 * `routeName` must match a named route in routes/web.php when set.
 */
export const marketingFeatures = [
    {
        icon: 'person',
        title: 'Leads & Contacts',
        description: 'Capture, assign, and nurture leads with full contact history in one place.',
        routeName: null,
        category: 'Sales',
    },
    {
        icon: 'handshake',
        title: 'Deals & Estimates',
        description: 'Build estimates, move deals through your pipeline, and close with confidence.',
        routeName: null,
        category: 'Sales',
    },
    {
        icon: 'inventory_2',
        title: 'Inventory Control',
        description: 'Track boats, engines, and trailers across locations with rich specifications.',
        routeName: null,
        category: 'Operations',
    },
    {
        icon: 'build',
        title: 'Service Department',
        description: 'Run service tickets and work orders tied to customers and inventory.',
        routeName: 'features.service-department',
        category: 'Operations',
        featured: true,
    },
    {
        icon: 'event',
        title: 'Boat Shows & Events',
        description: 'Plan show layouts, showcase inventory publicly, and capture leads on the floor.',
        routeName: 'features.boat-shows',
        category: 'Marketing',
    },
    {
        icon: 'bar_chart',
        title: 'Performance Tracking',
        description: 'See how your team and locations are performing across sales and operations.',
        routeName: 'features.performance-tracking',
        category: 'Analytics',
    },
    {
        icon: 'local_shipping',
        title: 'Delivery System',
        description: 'Plan routes, schedule deliveries, alert customers, and capture signatures on delivery day.',
        routeName: 'features.delivery-system',
        category: 'Operations',
    },
    {
        icon: 'poll',
        title: 'Smart Surveys',
        description: 'Build feedback and lead surveys with templates, conditional logic, and responses tied to leads and contacts.',
        routeName: 'features.smart-surveys',
        category: 'Marketing',
    },
    {
        icon: 'payments',
        title: 'Stripe Payments',
        description: 'Connect Stripe and let customers pay open invoices online by card or bank debit.',
        routeName: 'features.stripe-payments',
        category: 'Integrations',
    },
    {
        icon: 'campaign',
        title: 'Mailchimp',
        description: 'Sync contacts and leads with Mailchimp audiences — push, pull, lists, and segments.',
        routeName: 'features.mailchimp',
        category: 'Integrations',
    },
    {
        icon: 'account_balance',
        title: 'QuickBooks Online',
        description: 'Connect QuickBooks to sync customers, push invoices, and pull payments into Helmful.',
        routeName: 'features.quickbooks',
        category: 'Integrations',
    },
];

/** Mega menu columns (subset with detail pages + platform highlights). */
export const featuresMegaMenuGroups = [
    {
        title: 'Platform',
        items: [
            marketingFeatures[0],
            marketingFeatures[1],
            marketingFeatures[2],
        ],
    },
    {
        title: 'Operations',
        items: [
            marketingFeatures[3],
            marketingFeatures[6],
            marketingFeatures[5],
        ],
    },
    {
        title: 'Marketing',
        items: [
            marketingFeatures[4],
            marketingFeatures[7],
        ],
    },
    {
        title: 'Integrations',
        items: [
            marketingFeatures[8],
            marketingFeatures[9],
            marketingFeatures[10],
        ],
    },
];

export const featuresNavRouteNames = [
    'features',
    ...marketingFeatures.map((f) => f.routeName).filter(Boolean),
];
