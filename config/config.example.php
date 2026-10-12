<?php

// Copy this file to vaporised-config.php one level above the document root,
// then set the values. Do not put the real file in Git. The VAPORISED_CONFIG
// environment variable can point to another location.
//
// Generate each key with:
//   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'

return [
    'base_url' => 'https://www.vaporised.co.uk',

    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=DATABASE_NAME;charset=utf8mb4',
        'user' => 'DATABASE_USER',
        'password' => 'DATABASE_PASSWORD',
    ],

    // Two different 32-byte keys in base64. Neither one encrypts secrets:
    // the browser encrypts each secret, and the server never has that key.
    'rate_limit_key' => '',
    'pow_key' => '',

    'max_secret_chars' => 10000,
    'max_views' => 10,

    // A secret with a passphrase is deleted after this many incorrect attempts.
    'max_failed_attempts' => 10,

    // Time limit choices in seconds. The app ignores values above 7 days (604800).
    'ttl_choices' => [
        300 => '5 minutes',
        3600 => '1 hour',
        86400 => '1 day',
        259200 => '3 days',
        604800 => '7 days',
    ],

    // Maximum requests for each hashed IP address in one window.
    'rate_limit' => [
        'window_seconds' => 3600,
        'create' => 20,
        'reveal' => 60,
    ],

    // Proof-of-work for secret creation. Each added bit doubles the browser work.
    'pow' => [
        'difficulty_bits' => 16,
        'ttl_seconds' => 600,
    ],
];
