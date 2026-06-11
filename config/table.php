<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default table page size
    |--------------------------------------------------------------------------
    |
    | Number of records shown per page on tenant index/table views when the
    | request does not specify ?per_page=.
    |
    */

    'per_page' => (int) env('TABLE_PER_PAGE', 30),

];
