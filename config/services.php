<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'xposedornot' => [
        'key'      => env('XPOSEDORNOT_API_KEY'),
        'base_url' => env('XPOSEDORNOT_BASE_URL', 'https://plus-api.xposedornot.com'),
    ],

];
