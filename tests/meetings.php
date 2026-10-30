<?php
require __DIR__ . '/custom-plans.php';
$creator=['id'=>1,'role'=>'creator']; $other=['id'=>2,'role'=>'creator']; $admin=['id'=>99,'role'=>'admin'];
$input=['meeting_date'=>'2026-10-29','attendees'=>'Alex, Daniyal','discussion'=>"Discussed launch\nBudget reviewed",'actionables'=>'Alex: send proposal by Friday'];
$id=save_meeting($creator,1,$input);
$row=q('SELECT * FROM creator_meetings WHERE id=?',[$id])->fetch();
check($row['attendees']===$input['attendees'] && $row['author_id']==1,'Meeting details not saved');
check(can_edit_meeting($admin,$row) && !can_edit_meeting($other,$row),'Meeting permissions failed');
foreach (['2026-02-30','2026-13-01','2026-2-01','0000-01-01'] as $date) {
 try { meeting_fields(array_replace($input,['meeting_date'=>$date])); throw new RuntimeException('Invalid date accepted'); } catch(InvalidArgumentException $expected){}
}
try {meeting_fields(array_replace($input,['attendees'=>'']));throw new RuntimeException('Empty attendees accepted');}catch(InvalidArgumentException $expected){}
try {save_meeting($other,1,$input);throw new RuntimeException('Cross-creator creation allowed');}catch(InvalidArgumentException $expected){}
$edit=$input+['meeting_id'=>$id,'meeting_revision'=>1];$edit['discussion']='Updated launch plan';save_meeting($admin,1,$edit);
check(q('SELECT revision FROM creator_meetings WHERE id=?',[$id])->fetchColumn()==2,'Revision not advanced');
try{save_meeting($creator,1,$edit);throw new RuntimeException('Stale overwrite allowed');}catch(InvalidArgumentException $expected){}
try{remove_meeting($other,1,$id,2);throw new RuntimeException('Unauthorized deletion allowed');}catch(InvalidArgumentException $expected){}
$staffId=save_meeting($admin,1,array_replace($input,['meeting_date'=>'2026-10-30']));
try{save_meeting($creator,1,$input+['meeting_id'=>$staffId,'meeting_revision'=>1]);throw new RuntimeException('Creator changed staff log');}catch(InvalidArgumentException $expected){}
$data=dashboard_data('2026-10');$card=array_values(array_filter($data['cards'],fn($c)=>$c['id']===1))[0];check($card['lastMeeting']==='Oct 30','Dashboard last meeting not updated');
$data=dashboard_data('2026-09');$card=array_values(array_filter($data['cards'],fn($c)=>$c['id']===1))[0];check($card['lastMeeting']===null,'Future meeting leaked into historical dashboard');
remove_meeting($creator,1,$id,2);check(!q('SELECT 1 FROM creator_meetings WHERE id=?',[$id])->fetchColumn(),'Meeting deletion failed');
check(q('SELECT COUNT(*) FROM creator_meetings WHERE creator_id=2')->fetchColumn()==0,'Meeting data leaked across creators');
echo "Meetings: validation, creation, editing, concurrency, ownership, deletion and dashboard dates passed.\n";
