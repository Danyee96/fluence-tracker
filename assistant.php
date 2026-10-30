<?php
require __DIR__.'/inc/bootstrap.php';
if (!assistant_local()) {http_response_code(404);exit('Local assistant is disabled.');}
$u=require_login();
$creators=is_staff($u)?q("SELECT id,name FROM users WHERE role='creator' ORDER BY name")->fetchAll():[['id'=>$u['id'],'name'=>$u['name']]];
page_header('Assistant','assistant');
?>
<div class="dash-head"><div><p class="eyebrow">Fluence · Local preview</p><h1>Ask your tracker</h1><p class="sub">Read-only answers about a creator’s goals, tasks, results, notes, meetings and saved social stats.</p></div></div>
<section class="panel assistant-panel">
<?php if (empty($CONFIG['grok']['api_key']) && !getenv('XAI_API_KEY')): ?><div class="flash">Grok needs an API key in your local configuration. The interface is ready; live answers will be available after adding the key.</div><?php endif; ?>
<p class="hint">Relevant tracker records are sent to xAI when you ask a question. This preview runs only on this computer. It cannot modify records or inspect project source code.</p>
<form id="assistant-form">
<div class="meeting-fields"><label>Creator<select id="assistant-creator" required><?php foreach($creators as $creator):?><option value="<?= (int)$creator['id'] ?>"><?= e($creator['name']) ?></option><?php endforeach;?></select></label><label>Month<input id="assistant-month" type="month" value="<?= date('Y-m') ?>" required></label></div>
<div id="assistant-messages" class="assistant-messages" role="log" aria-label="Conversation" aria-live="polite"></div>
<label>Your question<textarea id="assistant-question" rows="2" maxlength="2000" placeholder="What should this creator focus on next?" required></textarea></label>
<div class="plan-actions"><button type="submit" class="btn primary" id="assistant-send">Ask Grok</button><button type="button" class="btn" id="assistant-clear">Clear conversation</button></div>
<p class="hint" id="assistant-status" role="status"></p>
</form></section>
<?php page_footer(['assistant.js']); ?>
