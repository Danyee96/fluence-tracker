<?php
require __DIR__ . '/inc/bootstrap.php';
$u = current_user();
redirect($u ? home_for($u) : 'login.php');
