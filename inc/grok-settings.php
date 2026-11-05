<?php
defined('FL') or exit;
function grok_settings_file(): string {return __DIR__.'/../config.grok.php';}
function grok_saved_settings(): array {
    $file=grok_settings_file();if(!is_file($file))return [];
    $settings=require $file;return is_array($settings)?$settings:[];
}
function grok_write_settings(array $settings): void {
    // A PHP return file cannot print a key when requested, and is excluded from Git.
    $path=grok_settings_file();$temporary=tempnam(dirname($path),'.grok-');
    if($temporary===false)throw new RuntimeException('Cannot save the key. Allow PHP to write the project configuration directory.');
    try {
        if(!chmod($temporary,0600) || file_put_contents($temporary,"<?php\n// Private server settings. Never commit this file.\nreturn ".var_export($settings,true).";\n",LOCK_EX)===false || !rename($temporary,$path))throw new RuntimeException('Could not save Fluence AI settings. Check configuration directory permissions.');
        if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
    }finally{if(is_file($temporary))unlink($temporary);}
}
function grok_effective_key(): string {
    global $CONFIG;return trim((string)(($CONFIG['grok']['api_key']??'')?:(getenv('XAI_API_KEY')?:'')));
}
function grok_account_settings(array $user,array $input): void {
    global $CONFIG;
    if(($user['role']??'')!=='superadmin')throw new InvalidArgumentException('Only Super Admins can manage Fluence AI settings.');
    $key=trim((string)($input['grok_key']??''));
    if($key!=='' && (!str_starts_with($key,'xai-') || preg_match('/\s/',$key) || strlen($key)>512 || strlen($key)<20))throw new InvalidArgumentException('Enter a valid xAI API key starting with xai-.');
    $model=trim((string)($input['grok_model']??'grok-4.7'));
    if(!preg_match('/\A[a-zA-Z0-9._-]{1,100}\z/',$model))throw new InvalidArgumentException('Enter a valid Fluence AI model name.');
    $settings=['api_key'=>$key!==''?$key:grok_effective_key(),'model'=>$model,'enabled'=>isset($input['grok_enabled']),'allow_remote'=>true];
    if($settings['enabled'] && $settings['api_key']==='')throw new InvalidArgumentException('Add an API key before enabling the assistant.');
    grok_write_settings($settings);
}
