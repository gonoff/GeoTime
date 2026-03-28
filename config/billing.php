<?php

return [
    'prices' => [
        'starter' => env('STRIPE_PRICE_STARTER', ''),
        'business' => env('STRIPE_PRICE_BUSINESS', ''),
    ],
];
