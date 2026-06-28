<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI tax rate lookup
    |--------------------------------------------------------------------------
    |
    | Rates are fetched via OpenAI on first request (or when stale) and stored
    | in tax_jurisdiction_rates. A record is considered stale when fetched_at
    | is before the start of the current calendar month.
    |
    */

    /*
    | Deprecated: tax lookup uses OPENAI_MODEL_DOCUMENT_EXTRACT (openai_models.php).
    */
    'ai_model' => env('OPENAI_TAX_RATE_MODEL'),

];
