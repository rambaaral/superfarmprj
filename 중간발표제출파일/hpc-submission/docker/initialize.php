<?php
define('WP_INSTALLING',true);
$_SERVER['HTTP_HOST']=parse_url(getenv('SITE_URL'),PHP_URL_HOST) ?: 'localhost';
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
if(get_option('hpc_submission_initialized')){echo "Existing local site retained.\n";exit;}
if(!is_blog_installed()){
    $password=getenv('ADMIN_PASSWORD');
    if(strlen($password)<15)throw new RuntimeException('Use an administrator password of at least 15 characters.');
    wp_install('슈퍼컴퓨팅센터 제출용',getenv('ADMIN_USER'),getenv('ADMIN_EMAIL'),false,'',$password);
}
$admin=get_user_by('login',getenv('ADMIN_USER'));
if(!$admin||!in_array('administrator',$admin->roles,true))throw new RuntimeException('Administrator initialization failed.');
wp_set_current_user($admin->ID);
$seed=json_decode(file_get_contents('/opt/hpc/seed/site.json'),true,512,JSON_THROW_ON_ERROR);
$replace=function($v)use(&$replace){
    if(is_array($v))return array_map($replace,$v);
    return is_string($v)?str_replace(['https://su-pa-fa-mu.com','http://su-pa-fa-mu.com'],home_url(),$v):$v;
};
$seed=$replace($seed);
// Empty installation only: no production database or users are imported.
foreach(get_posts(['post_type'=>'any','post_status'=>'any','numberposts'=>-1]) as $p)wp_delete_post($p->ID,true);
foreach($seed['posts'] as $p){
    $id=(int)$p['ID'];unset($p['ID']);$p['import_id']=$id;$p['post_author']=$admin->ID;
    $created=wp_insert_post(wp_slash($p),true);
    if(is_wp_error($created)||(int)$created!==$id)throw new RuntimeException('Content ID import failed: '.$id);
}
foreach($seed['meta'] as $m)add_post_meta((int)$m['post_id'],$m['key'],wp_slash($m['value']));
$termMap=[];
foreach($seed['terms'] as $t){$new=wp_insert_term($t['name'],'nav_menu',['slug'=>$t['slug'],'description'=>$t['description']]);if(is_wp_error($new))throw new RuntimeException($new->get_error_message());$termMap[$t['term_id']]=$new['term_id'];}
foreach($seed['relationships'] as $id=>$terms)wp_set_object_terms((int)$id,array_map(fn($n)=>(int)$termMap[$n],$terms),'nav_menu');
foreach($seed['options'] as $k=>$v){
    if(in_array($k,['active_plugins','stylesheet','template','hpc_lab_active_template']))continue;
    if($k==='theme_mods_hpc-portal-pages'&&isset($v['nav_menu_locations'])){foreach($v['nav_menu_locations'] as &$term)$term=$termMap[$term]??0;unset($term);}
    update_option($k,$v);
}
update_option('home',getenv('SITE_URL'));update_option('siteurl',getenv('SITE_URL'));
update_option('admin_email',getenv('ADMIN_EMAIL'));update_option('users_can_register',0);
$plugins=$seed['options']['active_plugins'];
// This submission uses MariaDB, which has no MySQL ngram_token_size variable.
// Return the same unsupported result without logging an unknown-variable error.
$mariaNgram=function($sql){return trim($sql)==='SELECT @@ngram_token_size'?'SELECT NULL':$sql;};
add_filter('query',$mariaNgram);
// WordPress stores this list alphabetically, which can put Pro before its base plugin.
usort($plugins,fn($a,$b)=>(str_contains($a,'-pro/')?1:0)<=>(str_contains($b,'-pro/')?1:0));
foreach($plugins as $plugin){
    $r=activate_plugin($plugin,'',false,false);
    if(is_wp_error($r))throw new RuntimeException('Plugin activation failed: '.$plugin.' '.$r->get_error_message());
}
remove_filter('query',$mariaNgram);
// The activation hook just created the schema; avoid repeating dbDelta on first request.
if(defined('KBOARD_VERSION'))update_option('kboard_version',KBOARD_VERSION);
// Init hooks ran before activation; install the custom schemas in this same process.
// hpc_install already ran via its activation hook.
foreach(['hpco_install','hpcm_install','hpcw_install','hpc_templates_install'] as $fn)if(function_exists($fn))$fn();
// Empty compatibility tables for retained legacy erasure references only.
// No legacy records or posting interface are restored.
foreach(['messages','forum'] as $kind){
    $table=hpc_table($kind);
    dbDelta("CREATE TABLE $table (id bigint unsigned NOT NULL AUTO_INCREMENT,
owner bigint unsigned NOT NULL DEFAULT 0,
title text NOT NULL,
body longtext NOT NULL,
hidden tinyint NOT NULL DEFAULT 0,
PRIMARY KEY  (id)
) ".$wpdb->get_charset_collate().';');
}
// Fresh empty KBoard boards; production posts, comments and accounts are excluded.
$boardIds=[];
foreach(['qna'=>'Q&A 게시판','forum'=>'이용자 포럼'] as $kind=>$label){
    $row=['board_name'=>$label,'skin'=>'default','use_comment'=>$kind==='forum'?'1':'','use_editor'=>'',
        'permission_read'=>'roles','permission_write'=>'roles','admin_user'=>'','use_category'=>'',
        'category1_list'=>'','category2_list'=>'','category3_list'=>'','category4_list'=>'','category5_list'=>'',
        'page_rpp'=>10,'created'=>date('YmdHis')];
    if(!$wpdb->insert($wpdb->prefix.'kboard_board_setting',$row))throw new RuntimeException('Board creation failed: '.$wpdb->last_error);
    $boardIds[$kind]=(int)$wpdb->insert_id;
    $board=new KBoard($boardIds[$kind]);
    $board->meta->permission_comment_write=$kind==='forum'?'author':'roles';
    $board->meta->comment_skin=$kind==='forum'?'default':'';
    $board->meta->permission_read_roles=serialize(['administrator']);
    $board->meta->permission_write_roles=serialize(['administrator']);
    $board->meta->permission_comment_write_roles=serialize(['administrator']);
    $board->meta->autolink=(int)($seed['options']['hpc_portal_pages_v1'][$kind]??0);
}
update_option('hkb_boards',$boardIds);
update_option('hkb_enabled',1);
if(function_exists('hpc_template_table')){
    global $wpdb;
    $table=hpc_template_table();
    if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table)throw new RuntimeException('Template table is missing.');
    $wpdb->query('DELETE FROM '.$table);
    foreach($seed['templates'] as $t){$t['created_by']=$admin->ID;if(!$wpdb->insert($table,$t))throw new RuntimeException('Template import failed: '.$wpdb->last_error);}
    update_option('hpc_lab_active_template',$seed['options']['hpc_lab_active_template']);
}
switch_theme('hpc-portal-pages');
flush_rewrite_rules(true);
update_option('hpc_submission_initialized',gmdate('c'));
echo "Local portal initialized with a new administrator.\n";
