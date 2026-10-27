<?php
/**
 * Fluence — configuration
 * Copy this file to config.php and fill in your Hostinger MySQL details
 * (hPanel → Databases → MySQL Databases).
 */
return [
    'db' => [
        // 'mysql' on Hostinger. Use 'sqlite' for quick local testing (php -S localhost:8000).
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'port'        => 3306,
        'name'        => 'u308679597_tracker',
        'user'        => 'u308679597_tracker',
        'pass'        => 'ENTER_HOSTINGER_DATABASE_PASSWORD',
        'sqlite_path' => __DIR__ . '/data/fluence.sqlite',
    ],

    'app_name' => 'Fluence',
    'timezone' => 'Asia/Karachi',

    // Admins are view-only by default. Set true to let admins edit creator data too.
    'admin_can_edit' => false,
];
