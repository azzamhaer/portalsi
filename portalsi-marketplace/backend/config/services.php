<?php

return [
    'tripay' => [
        'mode'          => env('TRIPAY_MODE', 'sandbox'),
        'api_key'       => env('TRIPAY_API_KEY'),
        'private_key'   => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE', 'T0001'),
    ],

    'brevo' => [
        'api_key'       => env('BREVO_API_KEY'),
        'sender_email'  => env('BREVO_SENDER_EMAIL', 'noreply@mpsi.id'),
        'sender_name'   => env('BREVO_SENDER_NAME', 'MPSI'),
    ],

    'rajaongkir' => [
        'mode'            => env('RAJAONGKIR_MODE', 'sandbox'),
        'api_key'         => env('RAJAONGKIR_API_KEY'),
        'tariff_base_url' => env('RAJAONGKIR_TARIFF_BASE_URL'),
        'order_base_url'  => env('RAJAONGKIR_ORDER_BASE_URL'),
    ],

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
