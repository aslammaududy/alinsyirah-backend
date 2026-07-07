<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    'core_api_base_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com',
];
