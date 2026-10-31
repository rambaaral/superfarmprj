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
    if($k==='theme_mods_hpc-portal-pages'&&isset($v['nav_menu_locations']))foreach($v['nav_menu_locations'] as &$term)$term=$termMap[$term]??0;
    update_option($k,$v);
}
update_option('home',getenv('SITE_URL'));update_option('siteurl',getenv('SITE_URL'));
update_option('admin_email',getenv('ADMIN_EMAIL'));update_option('users_can_register',0);
$plugins=$seed['options']['active_plugins'];
// WordPress stores this list alphabetically, which can put Pro before its base plugin.
usort($plugins,fn($a,$b)=>(str_contains($a,'-pro/')?1:0)<=>(str_contains($b,'-pro/')?1:0));
foreach($plugins as $plugin){
    $r=activate_plugin($plugin,'',false,false);
    if(is_wp_error($r))throw new RuntimeException('Plugin activation failed: '.$plugin.' '.$r->get_error_message());
}
// Init hooks ran before activation; install the custom schemas in this same process.
foreach(['hpc_install','hpco_install','hpcm_install','hpcw_install','hpcf_install','hpc_templates_install'] as $fn)if(function_exists($fn))$fn();
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
