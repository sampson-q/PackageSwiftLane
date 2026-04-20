<?php
// API-specific config
// Keep secret safe in production! Change this value.
return [
    'jwt_secret' => 'change_this_to_a_long_random_secret_please', // <-- change in production
    'jwt_algo'   => 'HS256',
    // token lifetime (seconds). e.g., 3600 = 1 hour
    'jwt_ttl'    => 3600,
    // CORS allowed origins - set to exact origins in production
    'cors' => [
        'allow_origin' => '*',
        'allow_methods' => 'POST, GET, OPTIONS, PUT, DELETE',
        'allow_headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
    ],
    // DEVELOPMENT only: if true, login responses may include debug_otp for testing
    'dev_mode' => true,
];