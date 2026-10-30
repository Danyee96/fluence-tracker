<?php
require __DIR__ . '/custom-plans.php';
// Monthly entries have no activity detail column. Surface warnings as test failures.
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
try {
    $entry = entry_js(['month'=>'2026-10','sponsor_rev'=>'500','info_rev'=>'250','subscribers'=>1000,'income_streams'=>2,'last_meeting'=>null]);
    check($entry === ['month'=>'2026-10','spon'=>500.0,'info'=>250.0,'subs'=>1000,'streams'=>2,'meeting'=>null], 'Monthly entry payload changed');
    check(entry_js(null) === null, 'Missing monthly entry changed');
    check(activity_verb(['action'=>'impersonation','month'=>'2026-10','detail'=>'returned to their admin account']) === 'returned to their admin account', 'Impersonation activity label changed');
} finally {
    restore_error_handler();
}
echo "Monthly entry serialization: warning-free payload and activity labels passed.\n";
