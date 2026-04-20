<?php
return [
    // JWT Configuration
    'jwt_secret' => 'change_this_to_a_long_random_secret_please',
    'jwt_algo'   => 'HS256',
    'jwt_ttl'    => 3600,

    // CORS Configuration - specify allowed origins explicitly
    'cors' => [
        'allow_origin' => ['http://localhost:3000', 'https://yourdomain.com'],
        'allow_methods' => 'POST, GET, OPTIONS, PUT, DELETE',
        'allow_headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
        'allow_credentials' => true,
    ],

    // Password Reset - base URL for password reset links
    'reset_url_base' => 'https://yourdomain.com/reset-password',

    // Development mode - exposes debug OTP in responses (NEVER enable in production)
    'dev_mode' => false,
];