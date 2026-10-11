<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expiry alert threshold
    |--------------------------------------------------------------------------
    |
    | A batch is flagged "Expiring Soon" when its expiration date falls within
    | this many days from today. Expired batches are excluded from usable stock.
    |
    */

    'expiry_alert_days' => (int) env('INVENTORY_EXPIRY_ALERT_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Branch
    |--------------------------------------------------------------------------
    */

    'branch_name' => env('INVENTORY_BRANCH_NAME', 'ZUS Coffee — Demo Branch'),

    'currency' => 'RM',

];
