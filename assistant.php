<?php
// Keep old bookmarks working; the assistant now lives on every authenticated page.
require __DIR__.'/inc/bootstrap.php';
if (!assistant_local()) {http_response_code(404);exit('Local assistant is disabled.');}
$u=require_login();redirect(home_for($u).'#assistant');
