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

    'ai_model' => env('OPENAI_TAX_RATE_MODEL', 'gpt-4o-mini'),

];
