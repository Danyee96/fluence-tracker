<?php
require __DIR__.'/custom-plans.php';
require __DIR__.'/../inc/assistant.php';
$creator=['id'=>1,'role'=>'creator'];
$context=assistant_context($creator,1,'2026-10');
check($context['creator']['id']===1 && isset($context['plan'],$context['meetings'],$context['socials']), 'Context incomplete');
check(!str_contains(json_encode($context),'password_hash'), 'Password exposed');
try {assistant_context($creator,2,'2026-10');throw new RuntimeException('Creator isolation failed');}catch(InvalidArgumentException $expected){}
check(assistant_context(['id'=>9,'role'=>'admin'],2,'2026-10')['creator']['id']===2,'Admin access failed');
$CONFIG['grok']=['enabled'=>true];$_SERVER['REMOTE_ADDR']='127.0.0.1';check(assistant_local(),'Loopback unavailable');
$_SERVER['REMOTE_ADDR']='203.0.113.1';$_SERVER['HTTP_HOST']='localhost';check(!assistant_local(),'Remote access allowed via spoofed host');
try{assistant_reply($context,'What next?');throw new RuntimeException('Missing key accepted');}catch(RuntimeException $expected){check(str_contains($expected->getMessage(),'API key'),'Unexpected missing-key error');}
echo "Assistant: scoped context, admin access, secret exclusion, loopback restriction and missing key passed.\n";
$own=assistant_global_context($creator,'2026-10');check($own['creator_count']===1 && $own['creators'][0]['creator']['id']===1,'Global scope leaked another creator');
$all=assistant_global_context(['id'=>9,'role'=>'admin'],'2026-10');check($all['creator_count']===2 && count($all['creators'])===2,'Global admin context incomplete');
