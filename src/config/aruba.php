<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aruba config
    |--------------------------------------------------------------------------
    |
    | config aruba ac7210 || iap.
    |
    */

    'ac_7210' => [
        'addr' => env('AC7210_ADDR', ''),
        'username' => env('AC7210_USERNAME', 'admin'),
        'password' => env('AC7210_PASSWORD', ''),
    ],

    'iap' => [
        'addr' => env('IAP_ADDR', ''),
        'username' => env('IAP_USERNAME', 'admin'),
        'password' => env('IAP_PASSWORD', ''),
    ],
];
