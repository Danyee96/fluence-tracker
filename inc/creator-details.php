<?php
defined('FL') or exit;

function onboarding_goals(): array
{
    return [
        ['key'=>'target_revenue','title'=>'Monthly revenue','type'=>'money','target'=>'10000','source'=>'revenue','description'=>''],
        ['key'=>'target_subs','title'=>'Subscribers','type'=>'number','target'=>'100000','source'=>'subs','description'=>''],
        ['key'=>'target_streams','title'=>'Income streams','type'=>'number','target'=>'3','source'=>'streams','description'=>''],
    ];
}

function social_edit_fingerprint(array $accounts): string
{
    return hash('sha256', json_encode(array_map(fn($a)=>[(int)$a['id'],$a['platform'],$a['handle'],(int)$a['is_active']],$accounts)));
}

/** Validate every row before touching either the user or their tracker. */
function creator_details_input(array $post, ?array $plan, array $accounts): array
{
    if (($post['creator_details_complete'] ?? '') !== '1') throw new InvalidArgumentException('The form was incomplete. Reload and try again (too many rows may exceed the server input limit).');
    if ($plan && (int)($post['plan_revision'] ?? -1) !== $plan['revision']) throw new InvalidArgumentException('The creator plan changed in another tab. Reload before saving.');
    if ($plan && !hash_equals(social_edit_fingerprint($accounts),(string)($post['social_revision'] ?? ''))) throw new InvalidArgumentException('Social profiles changed in another tab. Reload before saving.');
    $rawGoals=($post['goals_edit_mode']??'')==='external' && $plan ? $plan['goals'] : ($post['creator_goals']??[]);
    $rawSocial=$post['creator_socials'] ?? [];
    if (!is_array($rawGoals) || !is_array($rawSocial)) throw new InvalidArgumentException('Invalid creator details.');
    $known=array_column($plan['goals'] ?? onboarding_goals(),null,'key');$seen=[];$goals=[];
    foreach ($rawGoals as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid goal row.');
        foreach (['key','title','type','target','description'] as $field) if (!is_scalar($row[$field]??'')) throw new InvalidArgumentException('Enter valid goal details.');
        $key=(string)($row['key'] ?? '');$title=trim((string)($row['title'] ?? ''));$type=(string)($row['type'] ?? 'number');
        if ($title==='' && $key==='') continue;
        if ($title==='' || mb_strlen($title)>120) throw new InvalidArgumentException('Each goal needs a title of 1–120 characters.');
        if (!isset(PLAN_TYPES[$type])) throw new InvalidArgumentException('Choose a valid goal type.');
        if ($key!=='' && (!isset($known[$key]) || isset($seen[$key]))) throw new InvalidArgumentException('A goal row is invalid or duplicated. Reload the form.');
        $source=$key!=='' ? ($known[$key]['source'] ?? 'manual') : 'manual';
        if ($source!=='manual' && $type!==$known[$key]['type']) throw new InvalidArgumentException('Goals linked to monthly numbers must keep their original value type.');
        $key=$key!=='' ? $key : 'custom_'.bin2hex(random_bytes(10));$seen[$key]=true;
        $goals[]=['key'=>$key,'title'=>$title,'type'=>$type,'target'=>typed_value($type,$row['target']??''),'source'=>$source,'description'=>mb_substr(trim((string)($row['description']??'')),0,3000)];
    }
    $existing=array_column($accounts,null,'id');$seenIds=[];$seenIdentity=[];$socials=[];
    foreach ($rawSocial as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid social profile row.');
        foreach (['id','platform','handle'] as $field) if (!is_scalar($row[$field]??'')) throw new InvalidArgumentException('Enter valid social profile details.');
        $id=(int)($row['id']??0);$raw=trim((string)($row['handle']??''));
        if ($id && (!isset($existing[$id]) || isset($seenIds[$id]))) throw new InvalidArgumentException('A social profile row is invalid or duplicated. Reload the form.');
        if ($id) $seenIds[$id]=true;
        if ($raw==='' && !$id) continue;
        $platform=(string)($row['platform']??'');$identity=social_identity($platform,$raw);
        $unique=$platform.':'.$identity['handle'];
        if (isset($seenIdentity[$unique])) throw new InvalidArgumentException('The same social profile is listed more than once.');
        $seenIdentity[$unique]=true;
        $socials[]=['id'=>$id,'platform'=>$platform]+$identity;
    }
    return ['goals'=>$goals,'socials'=>$socials];
}

/** Must run inside the user's create/update transaction; provider calls happen after commit. */
function save_creator_details(int $cid, array $details, ?array $previousPlan = null): array
{
    $plan=$previousPlan ?? creator_plan($cid);$revision=$plan['revision'];unset($plan['revision']);$plan['goals']=$details['goals'];
    $json=json_encode($plan,JSON_THROW_ON_ERROR);
    if ($revision===0) q('INSERT INTO creator_plans (creator_id,definition,revision) VALUES (?,?,1)',[$cid,$json]);
    else {
        $changed=q('UPDATE creator_plans SET definition=?,revision=revision+1 WHERE creator_id=? AND revision=?',[$json,$cid,$revision]);
        if (!$changed->rowCount()) throw new InvalidArgumentException('Another admin changed this plan. Reload before saving.');
    }
    $keep=[];$queue=[];
    foreach ($details['socials'] as $row) {
        // Reuse the same identity (including archived profiles) to preserve its own history.
        $existing=q('SELECT * FROM social_accounts WHERE creator_id=? AND platform=? AND handle=?',[$cid,$row['platform'],$row['handle']])->fetch();
        if ($existing) {
            if ($existing['handle'] !== $row['handle']) throw new InvalidArgumentException('Check the exact capitalization of this channel ID.');
            $id=(int)$existing['id'];
            if ($existing['sync_status']==='archived') {
                q("UPDATE social_accounts SET is_active=1,sync_status='pending',next_sync=?,run_id=NULL WHERE id=?",[now(),$id]);
                $queue[]=$id;
            }
        } else {
            q('INSERT INTO social_accounts (creator_id,platform,handle,profile_url,created_at,next_sync) VALUES (?,?,?,?,?,?)',[$cid,$row['platform'],$row['handle'],$row['profile_url'],now(),now()]);
            $id=(int)db()->lastInsertId();$queue[]=$id;
        }
        $keep[]=$id;
    }
    $current=q("SELECT id FROM social_accounts WHERE creator_id=? AND sync_status<>'archived'",[$cid])->fetchAll();
    foreach ($current as $row) if (!in_array((int)$row['id'],$keep,true)) {
        q("UPDATE social_accounts SET is_active=0,sync_status='archived' WHERE id=?",[$row['id']]);
    }
    return $queue;
}

function creator_goal_row(array $row, string $index): void
{
    $row=array_map(fn($v)=>is_scalar($v)?(string)$v:'',$row);
    $prefix='creator_goals['.$index.']'; ?>
<div class="creator-goal-row repeat-row">
 <input type="hidden" name="<?= $prefix ?>[key]" value="<?= e($row['key']??'') ?>">
 <label>Goal name<input name="<?= $prefix ?>[title]" value="<?= e($row['title']??'') ?>" maxlength="120" placeholder="e.g. Monthly revenue" required></label>
 <label>Type<select name="<?= $prefix ?>[type]"><?php foreach (PLAN_TYPES as $key=>$label): ?><option value="<?= $key ?>" <?= ($row['type']??'number')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
 <label>Target<input name="<?= $prefix ?>[target]" value="<?= e($row['target']??'') ?>" maxlength="3000" placeholder="e.g. 10000"></label>
 <button class="iconbtn remove-repeat" type="button" aria-label="Remove goal"><?= icon('trash') ?></button>
 <label class="goal-row-notes">Instructions <small>(optional<?= ($row['source']??'manual')!=='manual'?'; result comes from monthly numbers':'' ?>)</small><input name="<?= $prefix ?>[description]" value="<?= e($row['description']??'') ?>" maxlength="3000" placeholder="What should the creator achieve?"></label>
</div><?php
}

function creator_social_row(array $row, string $index): void
{
    $row=array_map(fn($v)=>is_scalar($v)?(string)$v:'',$row);
    $prefix='creator_socials['.$index.']'; ?>
<div class="creator-social-row repeat-row">
 <input type="hidden" name="<?= $prefix ?>[id]" value="<?= (int)($row['id']??0) ?>">
 <label>Platform<select name="<?= $prefix ?>[platform]"><?php foreach (SOCIAL_PLATFORMS as $key=>$platform): ?><option value="<?= $key ?>" <?= ($row['platform']??'instagram')===$key?'selected':'' ?>><?= e($platform['label']) ?></option><?php endforeach; ?></select></label>
 <label>Profile link or handle<input name="<?= $prefix ?>[handle]" value="<?= e($row['profile_url']??$row['handle']??'') ?>" maxlength="255" placeholder="https://instagram.com/yourhandle" required></label>
 <button class="iconbtn remove-repeat" type="button" aria-label="Remove social profile"><?= icon('trash') ?></button>
</div><?php
}

function creator_details_editor(?array $plan = null, array $accounts = [], ?array $draft = null, ?int $creatorId = null): void
{
    $goals=$draft['creator_goals']??$plan['goals']??onboarding_goals();$socials=$draft['creator_socials']??$accounts;
    if (!is_array($goals)) $goals=$plan['goals']??onboarding_goals();
    if (!is_array($socials)) $socials=$accounts; ?>
<div class="creator-details fullrow" data-details-editor>
 <input type="hidden" name="creator_details_present" value="1">
 <input type="hidden" name="plan_revision" value="<?= (int)($draft['plan_revision']??$plan['revision']??0) ?>">
 <input type="hidden" name="social_revision" value="<?= e($draft['social_revision']??social_edit_fingerprint($accounts)) ?>">
 <div class="creator-details-heading"><h4>Social profiles</h4><button class="btn sm" type="button" data-add-repeat="socials"><?= icon('plus') ?>Add social profile</button></div>
 <div class="repeat-socials" data-next-index="<?= count($socials) ?>"><?php foreach (array_values($socials) as $i=>$social) creator_social_row(is_array($social)?$social:[],(string)$i); ?></div>
 <p class="hint">Select a platform and paste its public profile link or handle. Add as many profiles as needed. Changing or removing a profile archives its previous history; a different profile starts fresh.</p>

 <?php if ($creatorId !== null): ?>
 <input type="hidden" name="goals_edit_mode" value="external">
 <div class="creator-details-heading"><h4>Destination goals</h4><a class="btn sm" href="planner.php?id=<?= $creatorId ?>#destinations"><?= icon('edit') ?>Edit goals &amp; path</a></div>
 <?php else: ?>
 <div class="creator-details-heading"><h4>Destination goals</h4><button class="btn sm" type="button" data-add-repeat="goals"><?= icon('plus') ?>Add goal</button></div>
 <div class="repeat-goals" data-next-index="<?= count($goals) ?>"><?php foreach (array_values($goals) as $i=>$goal) creator_goal_row(is_array($goal)?$goal:[],(string)$i); ?></div>
 <p class="hint">These targets appear on the tracker and dashboard. Existing linked goals use monthly numbers; new goals accept results from the creator.</p>
 <?php endif; ?>

 <input type="hidden" name="creator_details_complete" value="1">
</div><?php
}
