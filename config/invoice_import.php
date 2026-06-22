<?php

declare(strict_types=1);

return [
    'ai_model' => env('OPENAI_INVOICE_IMPORT_MODEL', 'gpt-4o-mini'),
    'min_text_length' => 80,
];
