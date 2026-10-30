<?php
/** Shared override loader. Put server-specific values in Git-ignored config.private.php. */
$overrides = [];
$apifyToken = getenv('APIFY_API_TOKEN');
if ($apifyToken !== false && trim($apifyToken) !== '') {
    $overrides['apify']['token'] = trim($apifyToken);
}
$privateFile = __DIR__ . '/config.private.php';
if (is_file($privateFile)) {
    $private = require $privateFile;
    if (!is_array($private)) throw new RuntimeException('config.private.php must return an array.');
    $overrides = array_replace_recursive($overrides, $private);
}
return $overrides;
