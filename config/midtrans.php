<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    'payment_link_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://api.midtrans.com/v1/payment-links'
        : 'https://api.sandbox.midtrans.com/v1/payment-links',
];
