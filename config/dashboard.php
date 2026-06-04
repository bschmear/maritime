<?php

return [
    'stalled_opportunity_days' => (int) env('DASHBOARD_STALLED_OPPORTUNITY_DAYS', 14),
    'service_ticket_stale_days' => (int) env('DASHBOARD_SERVICE_TICKET_STALE_DAYS', 7),
    'expiring_estimate_days' => (int) env('DASHBOARD_EXPIRING_ESTIMATE_DAYS', 14),
    'list_cap' => (int) env('DASHBOARD_LIST_CAP', 10),

    /** Max merged notification + payment rows loaded for the dashboard activity feed. */
    'activity_fetch_cap' => (int) env('DASHBOARD_ACTIVITY_FETCH_CAP', 45),

    /** Rows shown per page in the activity feed (client-side pagination). */
    'activity_per_page' => (int) env('DASHBOARD_ACTIVITY_PER_PAGE', 10),
];
