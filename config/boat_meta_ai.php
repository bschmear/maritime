<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI model (full boat line metadata for one model)
    |--------------------------------------------------------------------------
    |
    | Deprecated: use OPENAI_MODEL_BOAT_SPECS in config/openai_models.php.
    |
    */

    'generate_model' => env('OPENAI_BOAT_GENERATE_MODEL'),

];
