<?php

return [
    'api' => [
        'id' => env('MANGOPAY_ID'),
        'secret' => env('MANGOPAY_KEY'),
        'url' => env('MANGOPAY_BASE', "https://api.mangopay.com")
    ],
    'folder' => '',
    'defaultCurrency' => 'EUR',
];
