<?php
if(!defined('ABSPATH'))exit;

// Retained solely for erasure of pre-KBoard records; no legacy writes or UI.
function hpcf_table(){return hpc_table('forum');}

function hpcf_allowed($uid=0) {
    global $wpdb;
    $uid=$uid?:get_current_user_id();
    if (!$uid || !get_userdata($uid) || hpcw_pending($uid) || !hpcm_verified($uid)) return false;
    if (user_can($uid,'manage_options')) return true;
    $id=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.hpc_table()." WHERE owner=%d AND state='selected' LIMIT 1",$uid));
    return !$wpdb->last_error && (bool)$id;
}
function hpcf_url($args=[]) {
    $ids=get_option('hpc_portal_pages_v1',[]);
    return add_query_arg($args,!empty($ids['forum'])?get_permalink($ids['forum']):home_url('/forum/'));
}

// The current KBoard forum uses the original approved-member policy.
add_action('template_redirect',function(){
    if(hpc_portal_page_key()!=='forum')return;
    if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
    do_action('litespeed_control_set_nocache','Approved member forum');
    nocache_headers();header('X-Robots-Tag: noindex, nofollow, noarchive');
    if(!is_user_logged_in()){auth_redirect();exit;}
    if(!hpcf_allowed())status_header(403);
},6);
