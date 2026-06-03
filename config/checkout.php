<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout process max execution time (seconds)
    |--------------------------------------------------------------------------
    |
    | POST /checkout creates a Stripe subscription and, for new workspaces,
    | provisions a tenant database and runs migrations synchronously. On slow
    | hosts this can exceed PHP's default 30s limit. Also raise your web
    | server timeout (e.g. nginx fastcgi_read_timeout) if you still see 504s.
    |
    */

    'process_max_execution_seconds' => (int) env('CHECKOUT_PROCESS_MAX_EXECUTION_SECONDS', 300),

];
