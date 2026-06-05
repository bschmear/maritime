/**
 * Sales process step definitions (shared by HTML flow and archived Vue Flow diagram).
 */

export const salesFlowSteps = {
    'boat-show': {
        id: 'boat-show',
        title: 'Boat show / Survey',
        subtitle: 'Optional intake',
        accent: 'amber',
        routeName: 'boat-show-events.index',
        optional: true,
        notes: [
            'Public boat-show pages and surveys capture interest on the water or at events.',
            'Submissions can create or update a Lead tied to the contact.',
            'Sales follows up from the lead record like any other source.',
        ],
    },
    contact: {
        id: 'contact',
        title: 'Contact',
        subtitle: 'Single record for every person or company',
        accent: 'slate',
        routeName: 'contacts.index',
        notes: [
            'Every party starts as a Contact — the master record for name, phone, email, and addresses.',
            'A contact can gain a Lead profile, a Customer profile, or both over time.',
            'Opportunities and estimates link to the contact (and resolve the customer when needed).',
        ],
    },
    lead: {
        id: 'lead',
        title: 'Lead',
        subtitle: 'Prospect before a sale',
        accent: 'yellow',
        routeName: 'leads.index',
        notes: [
            'Sources: walk-in, website, phone, referral, boat show, broker, and more.',
            'Track source, product interest, budget, timeline, and delivery location.',
            'Use Qualification to score fit, or convert the lead when ready to sell.',
        ],
    },
    qualification: {
        id: 'qualification',
        title: 'Qualification',
        subtitle: 'Score and qualify fit',
        accent: 'orange',
        routeName: 'qualifications.index',
        optional: true,
        notes: [
            'Document budget, timeline, trade-in, and product fit before heavy selling.',
            'Helps prioritize which leads become opportunities.',
            'Can prefill opportunity fields when you create the deal from a qualified lead.',
        ],
    },
    customer: {
        id: 'customer',
        title: 'Customer profile',
        subtitle: 'Buyer on the contact',
        accent: 'emerald',
        routeName: 'customers.index',
        notes: [
            'A Customer is a profile on the same Contact — not a separate person record.',
            'Required for estimates, contracts, invoices, and transactions.',
            'Created when converting a lead or automatically when you start a formal sale.',
        ],
    },
    opportunity: {
        id: 'opportunity',
        title: 'Opportunity',
        subtitle: 'Qualified deal in progress',
        accent: 'blue',
        routeName: 'opportunities.index',
        notes: [
            'Ties contact, product interest, salesperson, subsidiary, and location.',
            'Track stage, probability, and expected close date.',
            'Feature-request invites let the customer configure options before the estimate.',
        ],
    },
    estimate: {
        id: 'estimate',
        title: 'Estimate',
        subtitle: 'Pricing proposal',
        accent: 'indigo',
        routeName: 'estimates.index',
        notes: [
            'Line items: boat, engine, options, trailer, delivery, tax, and discounts.',
            'Statuses: draft → sent → viewed → negotiation → approved or rejected.',
            'Send for customer approval; revisions keep history on the same opportunity.',
        ],
    },
    contract: {
        id: 'contract',
        title: 'Contract',
        subtitle: 'Signed purchase agreement',
        accent: 'violet',
        routeName: 'contracts.index',
        notes: [
            'Captures product, options, payment terms, delivery timeline, and location.',
            'Send to customer for e-signature; PDF stored on the record.',
            'Often follows an approved estimate before operational work begins.',
        ],
    },
    transaction: {
        id: 'transaction',
        title: 'Transaction (Deal)',
        subtitle: 'Closed-won operational record',
        accent: 'purple',
        routeName: 'transactions.index',
        notes: [
            'Created from an approved, signed estimate — becomes the hub for fulfillment.',
            'Carries customer, line items, tax, and links back to opportunity and estimate.',
            'Deposit and final invoices, service, and delivery all hang off the transaction.',
        ],
    },
    'deposit-invoice': {
        id: 'deposit-invoice',
        title: 'Deposit invoice',
        subtitle: 'Secure the sale',
        accent: 'fuchsia',
        routeName: 'invoices.index',
        notes: [
            'Typical structure: deposit now, balance at delivery (e.g. 50/50).',
            'Collect via wire, ACH, check, cash, or card when Stripe is connected.',
            'Once deposit is received, ops can allocate inventory and open service.',
        ],
    },
    inventory: {
        id: 'inventory',
        title: 'Inventory',
        subtitle: 'Stock or factory order',
        accent: 'teal',
        routeName: 'inventoryitems.index',
        notes: [
            'Allocate an in-stock unit, or place a manufacturer order with confirmed specs.',
            'Confirm model year, color, options, and lead time with the customer.',
            'Serialized units track the specific hull or VIN through delivery.',
        ],
    },
    'service-ticket': {
        id: 'service-ticket',
        title: 'Service ticket',
        subtitle: 'Prep & rigging coordination',
        accent: 'cyan',
        routeName: 'servicetickets.index',
        notes: [
            'Internal hub for PDI, rigging, accessories, and quality checks before delivery.',
            'Customer may approve scope via the public review link.',
            'Spawns work orders for technicians in the service yard.',
        ],
    },
    'work-orders': {
        id: 'work-orders',
        title: 'Work orders',
        subtitle: 'Technician tasks',
        accent: 'blue',
        routeName: 'workorders.index',
        notes: [
            'Break the service ticket into schedulable labor and parts lines.',
            'Track status on the kanban from open through closed.',
            'Log actual time and complete checklist items before delivery.',
        ],
    },
    'final-invoice': {
        id: 'final-invoice',
        title: 'Final invoice',
        subtitle: 'Balance due',
        accent: 'rose',
        routeName: 'invoices.index',
        notes: [
            'Collect remaining balance after prep work and before or at delivery.',
            'May include last-minute additions approved on the service ticket.',
            'Payment recorded against the same customer and transaction.',
        ],
    },
    delivery: {
        id: 'delivery',
        title: 'Delivery',
        subtitle: 'Handoff to customer',
        accent: 'emerald',
        routeName: 'deliveries.index',
        notes: [
            'Schedule delivery location and walkthrough with the buyer.',
            'Checklist templates ensure registration, title, and orientation steps.',
            'Customer signs delivery acceptance; documents attach to the deal.',
        ],
    },
    closed: {
        id: 'closed',
        title: 'Deal closed',
        subtitle: 'Transaction complete',
        accent: 'green',
        routeName: 'transactions.index',
        milestone: true,
        notes: [
            'Transaction marked complete — sale and fulfillment finished.',
            'Registration and title paperwork stored under documents on the contact or deal.',
            'Customer remains in the system for service, warranty, and future sales.',
        ],
    },
};

/** Primary pipeline after intake merges at Opportunity */
export const salesFlowMainSpine = [
    'opportunity',
    'estimate',
    'contract',
    'transaction',
    'deposit-invoice',
    'inventory',
    'service-ticket',
    'work-orders',
    'final-invoice',
    'delivery',
    'closed',
];

/** Intake path: boat show → lead → qualification (merges into Opportunity) */
export const salesFlowLeadPath = ['boat-show', 'lead', 'qualification'];

/** Intake path: contact → customer profile (merges into Opportunity) */
export const salesFlowCustomerPath = ['contact', 'customer'];

export function getSalesFlowStep(id) {
    return salesFlowSteps[id] ?? null;
}
