<?php

declare(strict_types=1);

return [
    /*
    | Deprecated: use OPENAI_MODEL_DOCUMENT_EXTRACT / openai_models.invoice_min_text_length.
    */
    'ai_model' => env('OPENAI_INVOICE_IMPORT_MODEL'),
    'min_text_length' => (int) env('OPENAI_INVOICE_MIN_TEXT_LENGTH', 80),

    /*
    | Invoice AI parse/extract can run OpenAI calls for 60–120+ seconds. Web SAPI
    | defaults (and some reverse proxies) are lower unless raised here.
    */
    'max_execution_seconds' => (int) env('INVOICE_IMPORT_MAX_EXECUTION_SECONDS', 300),
];
