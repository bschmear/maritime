<?php

return [

    'domain' => env('INBOUND_EMAIL_DOMAIN', 'inbound.helmful.com'),

    'verify_signature' => env('SENDGRID_INBOUND_VERIFY', env('APP_ENV') === 'production'),

    'webhook_secret' => env('INBOUND_EMAIL_WEBHOOK_SECRET'),

    'rate_limit' => [
        'max_attempts' => (int) env('INBOUND_EMAIL_RATE_LIMIT', 120),
        'decay_minutes' => 1,
    ],

    /*
    | Deprecated: lead extraction uses OPENAI_MODEL_DOCUMENT_EXTRACT (openai_models.php).
    */
    'ai_model' => env('OPENAI_LEAD_EXTRACTION_MODEL'),

];
