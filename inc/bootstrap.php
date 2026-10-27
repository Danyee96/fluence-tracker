<?php
declare(strict_types=1);
define('FL', true);
define('FL_VERSION', '2.2');

$cfgFile = __DIR__ . '/../config.php';
if (!file_exists($cfgFile)) {
    http_response_code(500);
    exit('Missing config.php — copy config.sample.php to config.php and add your database details.');
}
$CONFIG = require $cfgFile;
date_default_timezone_set($CONFIG['timezone'] ?? 'UTC');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('fluence_sess');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/schema.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/progress.php';
require __DIR__ . '/layout.php';

ensure_schema();
