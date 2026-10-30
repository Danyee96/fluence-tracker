<?php
require __DIR__.'/inc/bootstrap.php';
if (!assistant_local()) json_out(['ok'=>false,'error'=>'Local assistant is disabled.'],404);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['ok'=>false,'error'=>'POST required.'],405);
$u=current_user();if (!$u) json_out(['ok'=>false,'error'=>'Please log in.'],401);
if (!csrf_ok($_SERVER['HTTP_X_CSRF']??null)) json_out(['ok'=>false,'error'=>'Refresh the page and retry.'],419);
$in=json_decode(file_get_contents('php://input'),true);
if (!is_array($in)) json_out(['ok'=>false,'error'=>'Invalid request.'],422);
$month=valid_month($in['month']??'');$question=trim((string)($in['question']??''));
if (!$month || $question==='' || mb_strlen($question)>2000) json_out(['ok'=>false,'error'=>'Choose a month and enter a question up to 2,000 characters.'],422);
if (time()-(int)($_SESSION['assistant_last']??0)<5) json_out(['ok'=>false,'error'=>'Please wait a few seconds before asking again.'],429);
try {
    $cid=$u['role']==='creator'?(int)$u['id']:(int)($in['cid']??0);
    $context=assistant_context($u,$cid,$month);
    $_SESSION['assistant_last']=time();
    // Release the session lock while waiting for the provider.
    session_write_close();
    $answer=assistant_reply($context,$question,is_array($in['history']??null)?$in['history']:[]);
    json_out(['ok'=>true,'answer'=>$answer,'source'=>$context['tracker_url'],'captured_at'=>$context['captured_at']]);
} catch (InvalidArgumentException $e) {json_out(['ok'=>false,'error'=>$e->getMessage()],422);}
catch (RuntimeException $e) {json_out(['ok'=>false,'error'=>$e->getMessage()],503);}
catch (Throwable $e) {json_out(['ok'=>false,'error'=>'Unable to prepare an answer. Try again.'],500);}
