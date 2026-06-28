<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI models by request type
    |--------------------------------------------------------------------------
    |
    | boat_specs       → structured spec / catalog metadata (cheap, schema-bound)
    | document_extract → invoice PDF text, leads, tax lookup (cheap, schema-bound)
    | messy_ocr        → poor PDF text extraction; upgrade to full model
    |
    */

    'boat_specs' => env('OPENAI_MODEL_BOAT_SPECS', env('OPENAI_BOAT_GENERATE_MODEL', 'gpt-5-mini')),

    'document_extract' => env('OPENAI_MODEL_DOCUMENT_EXTRACT', env('OPENAI_INVOICE_IMPORT_MODEL', 'gpt-5-mini')),

    'messy_ocr' => env('OPENAI_MODEL_MESSY_OCR', 'gpt-5'),

    /*
    |--------------------------------------------------------------------------
    | Invoice import: minimum extracted text length before messy_ocr upgrade
    |--------------------------------------------------------------------------
    */

    'invoice_min_text_length' => (int) env('OPENAI_INVOICE_MIN_TEXT_LENGTH', 80),

];
