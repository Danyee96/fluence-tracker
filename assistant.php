<?php
// Full chat workspace; the floating assistant shares the same conversation history.
require __DIR__.'/inc/bootstrap.php';
if (!assistant_local()) {http_response_code(404);exit('Assistant is disabled.');}
$u=require_login();
page_header('Chats','assistant');
?><div class="section-h chats-page-heading"><div><p class="eyebrow">Fluence assistant</p><h1>Your workspace</h1></div><a class="btn sm" href="<?= e(home_for($u)) ?>">Back to dashboard</a></div><?php
page_footer();
