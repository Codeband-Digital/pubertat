<?php

return [
    'stripe' => [
        'api_key' => env('STRIPE_API_KEY')
    ],
    'robokassa' => [
        'merchant_url' =>  env('ROBOKASSA_MERCHANT_URL'),
        'login' =>  env('ROBOKASSA_LOGIN'),
        'testpass1' =>  env('ROBOKASSA_TEST_PASS_1'),
        'testpass2' =>  env('ROBOKASSA_TEST_PASS_2'),
        'pass1' =>  env('ROBOKASSA_PASS_1'),
        'pass2' =>  env('ROBOKASSA_PASS_2'),
    ]
];


