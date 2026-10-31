<?php
if(!defined('ABSPATH'))exit;
function hpc_install_portal_pages(){
if(!function_exists('hpc_portal_page_key'))throw new RuntimeException('HPC plugin must be active.');
$theme=wp_get_theme('hpc-portal-pages');
if(!$theme->exists())throw new RuntimeException('Portal theme missing.');
$definitions=json_decode(file_get_contents($theme->get_stylesheet_directory().'/pages.json'),true,512,JSON_THROW_ON_ERROR);
if(count($definitions)!==10)throw new RuntimeException('Expected ten page definitions.');
$ids=get_option('hpc_portal_pages_v1',[]);$result=[];
if(!get_option('hpc_portal_original_v1'))add_option('hpc_portal_original_v1',['stylesheet'=>get_stylesheet(),'template'=>get_template(),'show_on_front'=>get_option('show_on_front'),'page_on_front'=>get_option('page_on_front'),'page_for_posts'=>get_option('page_for_posts'),'nav_menu_locations'=>get_theme_mod('nav_menu_locations',[])], '', false);
foreach($definitions as $d){
    $page=!empty($ids[$d['slug']])?get_post($ids[$d['slug']]):null;
    if(!$page||$page->post_type!=='page')$page=get_page_by_path($d['slug'],OBJECT,'page');
    if($page && $page->post_status==='trash')throw new RuntimeException('Existing trashed page requires review: '.$d['slug']);
    if(!$page){$id=wp_insert_post(['post_type'=>'page','post_title'=>$d['title'],'post_name'=>$d['slug'],'post_status'=>'publish','post_content'=>'','comment_status'=>'closed','ping_status'=>'closed'],true);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());update_post_meta($id,'_hpc_portal_generated_hash',hash('sha256',''));$result[$d['slug']]='created';}
    else{$id=$page->ID;$result[$d['slug']]='reused';}
    $ids[$d['slug']]=(int)$id;
}
update_option('hpc_portal_pages_v1',$ids,false);
// These are trusted, locally maintained seeds, not uploads or user input.
// The CLI has no logged-in user; default KSES would remove the simulation form and SVG.
remove_filter('content_save_pre','wp_filter_post_kses');
foreach($definitions as $d){
    $id=$ids[$d['slug']];$current=get_post($id)->post_content;$old=get_post_meta($id,'_hpc_portal_generated_hash',true);
    $content=preg_replace_callback('/\{\{page:([a-z-]+)\}\}/',fn($m)=>esc_url(get_permalink($ids[$m[1]])),$d['content']);
    if($old!=='' && hash_equals($old,hash('sha256',$current))){
        if($current!==$content){$r=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$content]),true);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());}
        update_post_meta($id,'_hpc_portal_generated_hash',hash('sha256',get_post($id)->post_content));
    }else{$result[$d['slug']].='; content preserved (manual edit or existing page)';}
}
$first=!get_option('hpc_portal_initialized_v1');
if($first){
    switch_theme('hpc-portal-pages');update_option('show_on_front','page');update_option('page_on_front',$ids['home']);update_option('page_for_posts',0);
    $menu=wp_create_nav_menu('슈퍼컴퓨팅센터 상단 메뉴');if(is_wp_error($menu))throw new RuntimeException($menu->get_error_message());
    foreach($definitions as $i=>$d){$r=wp_update_nav_menu_item($menu,0,['menu-item-title'=>$d['slug']==='home'?'메인':$d['title'],'menu-item-object-id'=>$ids[$d['slug']],'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish','menu-item-position'=>$i+1]);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());}
    set_theme_mod('nav_menu_locations',['primary'=>$menu]);update_option('hpc_portal_menu_v1',(int)$menu,false);update_option('hpc_portal_initialized_v1',true,false);
}
// Retire practice without deleting its content or revisions. Old URLs redirect in forum.php.
if (isset($ids['forum']) && function_exists('hpcf_install')) {
    hpcf_install();
    $retired=!empty($ids['application-simulation'])?get_post($ids['application-simulation']):get_page_by_path('application-simulation',OBJECT,'page');
    if ($retired && $retired->post_type==='page') {
        $ids['application-simulation']=(int)$retired->ID;
        if ($retired->post_status==='publish') {
            update_post_meta($retired->ID,'_hpc_retired_previous_status','publish');
            $r=wp_update_post(['ID'=>$retired->ID,'post_status'=>'draft'],true);
            if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
        }
        $result['application-simulation']='retired; original content retained';
    }
    update_option('hpc_portal_pages_v1',$ids,false);
    $menu=get_option('hpc_portal_menu_v1');
    if($menu) {
        $has_forum=false;
        foreach(wp_get_nav_menu_items($menu,['post_status'=>'any'])?:[] as $item) {
            if((int)$item->object_id===(int)$ids['forum'])$has_forum=true;
            if($retired && (int)$item->object_id===(int)$retired->ID)wp_update_post(['ID'=>$item->ID,'post_status'=>'draft']);
        }
        if(!$has_forum) {
            $r=wp_update_nav_menu_item($menu,0,['menu-item-title'=>'이용자 포럼','menu-item-object-id'=>$ids['forum'],'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish']);
            if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
        }
    }
    do_action('litespeed_purge_all');
}
return ['pages'=>$ids,'results'=>$result,'theme'=>get_stylesheet(),'home'=>get_option('page_on_front')];

}

