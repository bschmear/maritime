<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'radar' => [
        'secret' => env('RADAR_SECRET'),
        'publishable' => env('RADAR_PUBLISHABLE'),
    ],

    /*
    | Stripe Connect (optional reference)
    |----------------------------------------------------------------------
    | Register this URL once in the Stripe Dashboard (Connect webhooks).
    | Signing secret for that endpoint → STRIPE_WEBHOOK_SECRET (see config/cashier.php).
    */
    'stripe' => [
        'connect_webhook_url' => env('STRIPE_WEBHOOK'),
    ],

    /*
     * QuickBooks Online (Intuit) OAuth 2.0
     *----------------------------------------------------------------------
     * Register a single redirect URL once in the Intuit developer dashboard
     * (Production keys → Redirect URIs). It must match `services.quickbooks.redirect_uri`
     * byte-for-byte (scheme, host, path, no trailing slash). Each tenant uses a central
     * handoff record to map the OAuth `state` back to the originating workspace.
     *
     * `environment` controls whether to hit the sandbox or production OAuth + Accounting APIs.
     */
    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        'environment' => env('QUICKBOOKS_ENVIRONMENT', 'sandbox'),
        'scopes' => env('QUICKBOOKS_SCOPES', 'com.intuit.quickbooks.accounting'),
    ],

    'mailchimp' => [
        'client_id' => env('MAILCHIMP_CLIENT_ID'),
        'client_secret' => env('MAILCHIMP_CLIENT_SECRET'),
        /*
         * Single redirect URL registered in Mailchimp (must match this value exactly).
         * Prefer MAILCHIMP_REDIRECT_URI=https://your-central-host/integrations/mailchimp/oauth/callback
         * so it matches Mailchimp even when APP_URL uses http by mistake.
         */
        'redirect_uri' => \App\Support\MailchimpOAuthRedirect::canonical(),
        'api_key' => env('MAILCHIMP_API_KEY'),
        'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
    ],
];
