<?php
/* Plugin Name: HPC Board Administration
Description: Unified administration for existing center boards.
Version: 1.0.0
*/
defined('ABSPATH') || exit;
add_action('admin_menu',function(){
 add_menu_page('게시판 관리','게시판 관리','manage_options','hpb-boards','hpb_home','dashicons-feedback',25);
 add_submenu_page('hpb-boards','게시판 현황','게시판 현황','manage_options','hpb-boards','hpb_home');
 add_submenu_page('hpb-boards','FAQ','FAQ 전체','manage_options','edit.php?post_type=hkc_faq');
 add_submenu_page('hpb-boards','공지사항','공지사항','manage_options','edit.php?post_type=hpc_notice');
 if(current_user_can('manage_options'))foreach(['hkc_faq','hpc_notice','hkc_public_qna'] as $type)remove_menu_page('edit.php?post_type='.$type);
},100);
function hpb_home(){
 if(!current_user_can('manage_options'))wp_die(esc_html(hpct_text('ta20ccb3e6b01e3245b66')));
 if(function_exists('hkb_home')&&get_option('hkb_enabled')){hkb_home();return;}
 echo '<div class="wrap"><h1>'.esc_html(hpct_text('t278b3db56d1f46e7033b')).'</h1><p><a href="'.esc_url(admin_url('edit.php?post_type=hkc_faq')).'">FAQ</a></p></div>';
}
