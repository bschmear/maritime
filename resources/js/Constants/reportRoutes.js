export const reportRouteGroups = [
    {
        label: 'Financial',
        options: [
            { label: 'Profit & Loss', route: 'reports.pnl' },
            { label: 'Balance Sheet', route: 'reports.balance-sheet' },
            { label: 'Cash Flow', route: 'reports.cash-flow' },
            { label: 'Sales Tax Liability', route: 'reports.sales-tax-liability' },
            { label: 'Sales Tax Payable', route: 'reports.sales-tax-payable' },
        ],
    },
    {
        label: 'Sales',
        options: [
            { label: 'Sales by Customer', route: 'reports.sales-by-customer' },
            { label: 'Sales by Item (Summary)', route: 'reports.sales-by-item-summary' },
            { label: 'Sales by Item (Detail)', route: 'reports.sales-by-item-detail' },
        ],
    },
];
