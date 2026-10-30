<?php
require __DIR__.'/custom-plans.php';
foreach (['@Apify','https://x.com/Apify','https://twitter.com/Apify/','twitter.com/Apify','https://mobile.twitter.com/Apify'] as $raw) {
 $account=social_identity('x',$raw);check($account['handle']==='apify' && $account['profile_url']==='https://x.com/apify','X URL normalization failed');
}
foreach (['https://x.com/apify/status/123','https://x.com.evil.test/apify','https://evil.test/apify','https://x.com/home','@bad.handle','@name-with-dash','@this_handle_is_far_too_long'] as $raw) {
 try {social_identity('x',$raw);throw new RuntimeException('Invalid X handle accepted');}catch(InvalidArgumentException $expected){}
}
$account=['platform'=>'x']+social_identity('x','apify');$input=social_actor_input($account);
check($input['twitterHandles']===['apify'] && $input['getFollowers']===false && $input['getFollowing']===false && $input['getRetweeters']===false && $input['maxItems']===1,'X actor fetched unwanted followers/tweets');
$stats=social_parse($account,[['userName'=>'APIFY','name'=>'Apify','followers'=>25000,'statusesCount'=>1200,'favouritesCount'=>900]]);
check($stats['followers']===25000 && $stats['posts']===1200 && $stats['likes']===null,'X field mapping or likes semantics incorrect');
try{social_parse($account,[['userName'=>'other','followers'=>100]]);throw new RuntimeException('Wrong X identity accepted');}catch(RuntimeException $e){check(!str_contains($e->getMessage(),'Wrong X'),'Mismatched profile accepted');}
try{social_parse($account,[['userName'=>'apify','followers'=>null]]);throw new RuntimeException('Unknown X count became zero');}catch(RuntimeException $e){check(!str_contains($e->getMessage(),'Unknown X'),'Unknown count was fabricated');}
echo "X integration: platform, both URL formats, validation, bounded actor input, identity match and metric mapping passed.\n";
