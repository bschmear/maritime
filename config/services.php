<?php

use App\Support\MailchimpOAuthRedirect;

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

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
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
    | Stripe Connect (platform webhook)
    |----------------------------------------------------------------------
    | Dashboard → Developers → Webhooks → add endpoint (Connect enabled):
    |   POST https://your-app-domain/stripe/connect-webhook
    |
    | STRIPE_WEBHOOK is the public URL (documentation / ops reference only).
    | STRIPE_CONNECT_WEBHOOK_SECRET (or STRIPE_WEBHOOK_SECRET) must be the
    | signing secret (whsec_…) from that exact endpoint — not the URL.
    */
    'stripe' => [
        'connect_webhook_url' => env('STRIPE_WEBHOOK'),
        'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),
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

    /*
     * Cloudflare Turnstile (contact form and other public forms).
     * When secret_key is empty, captcha is skipped (local dev).
     */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        // When true, sends request()->ip() to siteverify. Leave false behind load balancers / Cloudflare unless TrustProxies is correct.
        'send_remote_ip' => env('TURNSTILE_SEND_REMOTE_IP', false),
    ],

    'mailchimp' => [
        'client_id' => env('MAILCHIMP_CLIENT_ID'),
        'client_secret' => env('MAILCHIMP_CLIENT_SECRET'),
        /*
         * Single redirect URL registered in Mailchimp (must match this value exactly).
         * Prefer MAILCHIMP_REDIRECT_URI=https://your-central-host/integrations/mailchimp/oauth/callback
         * so it matches Mailchimp even when APP_URL uses http by mistake.
         */
        'redirect_uri' => MailchimpOAuthRedirect::canonical(),
        'api_key' => env('MAILCHIMP_API_KEY'),
        'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'scopes' => env('GOOGLE_OAUTH_SCOPES', 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/userinfo.email'),
        'drive_app_folder_name' => env('GOOGLE_DRIVE_APP_FOLDER_NAME', 'Helmful'),
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'analytics_id' => env('GOOGLE_ANALYTICS_ID', 'G-8RV63EJXH6'),
    ],
];
