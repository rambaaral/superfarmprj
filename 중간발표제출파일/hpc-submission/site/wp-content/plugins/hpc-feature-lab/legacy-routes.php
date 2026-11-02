<?php
if(!defined('ABSPATH'))exit;

// Bookmarks remain useful; retired form submissions never write or redirect as success.
add_action('template_redirect',function(){
    $legacy_view=isset($_GET['hpc_lab'])&&isset($_GET['view'])&&is_scalar($_GET['view'])?(string)$_GET['view']:'';
    $legacy_board=in_array($legacy_view,['board','qna'],true);
    $legacy_post=($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&
        (isset($_POST['forum_op'])||in_array(hpc_input('op'),['message','answer'],true))&&
        (isset($_GET['hpc_lab'])||in_array(hpc_portal_page_key(),['qna','forum'],true));
    if($legacy_post||($legacy_board&&($_SERVER['REQUEST_METHOD']??'GET')==='POST')){
        nocache_headers();wp_die(esc_html(hpct_text('tdef94cb9e22dfa7aef36')),'',['response'=>410]);
    }
    if($legacy_board){
        nocache_headers();wp_safe_redirect(hpc_support_url($legacy_view==='board'?'notices':'qna'),301);exit;
    }
},-30);

// The old practice page is retired reversibly; bookmarks lead to the application guide.
add_action('template_redirect',function() {
    $ids=get_option('hpc_portal_pages_v1',[]);
    $path=rtrim((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
    $old=isset($_GET['page_id'])&&is_scalar($_GET['page_id'])?(int)$_GET['page_id']:0;
    if (hpc_portal_page_key()==='application-simulation' || $path===rtrim((string)wp_parse_url(home_url('/application-simulation'),PHP_URL_PATH),'/') || ($old && $old===(int)($ids['application-simulation']??0))) {
        wp_safe_redirect(!empty($ids['application-training'])?get_permalink($ids['application-training']):home_url('/'),301);exit;
    }
},1);
