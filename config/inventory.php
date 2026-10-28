<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Products with stock on hand less than or equal to this threshold will be
    | flagged as low stock and listed on the low-stock alert endpoint/UI.
    |
    */
    'low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 10),
];

