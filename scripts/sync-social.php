<?php
/** Hostinger cron: php /absolute/path/to/public_html/scripts/sync-social.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../inc/bootstrap.php';
try {
    $count = social_tick(20);
    echo now() . ': processed ' . $count . " social accounts.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Social sync could not run. Check Apify configuration and database access.\n");
    exit(1);
}
