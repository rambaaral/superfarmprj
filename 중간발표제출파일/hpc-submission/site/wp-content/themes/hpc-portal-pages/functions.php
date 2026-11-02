<?php
if(!defined('ABSPATH')) exit;
require_once __DIR__.'/auth-design.php';
add_action('after_setup_theme',function(){require_once __DIR__.'/center-content.php';},20);
function hp_pages(){return ['home'=>hpct_text('ta8ce0724326b833ac770'),'center'=>hpct_text('tb5bf553de4e00f74778f'),'center-research-guide'=>hpct_text('t6f0298e37c212ddd8085'),'center-use-guide'=>hpct_text('t50e0fda01868557938ac'),'center-equipment'=>hpct_text('tbcfdd852c3eb7c7ebbf2'),'center-research'=>hpct_text('t410c46d881599a715d57'),'notices'=>hpct_text('ta8f562bb9bb06d2a6f40'),'application-training'=>hpct_text('t3687918ac7c03a35a51e'),'usage-training'=>hpct_text('t98981b15a48789b71601'),'usage-simulation'=>hpct_text('tdeb8ccad6618a8b38a14'),'application'=>hpct_text('t560a559b8d4769ceb7f4'),'application-status'=>hpct_text('t5f7ddc7e1593b1433a45'),'my-page'=>hpct_text('t92926204706ba7315a5c'),'faq'=>hpct_text('tcb5aac813b2343c39d57'),'usage-rules'=>hpct_text('t27e597f8d61a70af3a1a'),'ai-chatbot'=>hpct_text('t570c301fc376da45a191'),'qna'=>hpct_text('t5b0a6f0bb50e48bbc3db'),'forum'=>hpct_text('t4b6108492bc78eb3b3a1')];}
function hp_url($key){if($key==='center-research-guide')return get_permalink(167);if($key==='center-use-guide')return get_permalink(170);$ids=get_option('hpc_portal_pages_v1',[]);return !empty($ids[$key])?get_permalink($ids[$key]):home_url('/');}
function hp_current(){if(is_page(167))return 'center-research-guide';if(is_page(170))return 'center-use-guide';if(is_page(143))return 'center-equipment';if(is_page(147))return 'center-research';return function_exists('hpc_portal_page_key')?hpc_portal_page_key():'';}
require_once __DIR__.'/navigation.php';
function hp_icon($name){$paths=['arrow'=>'M5 12h14m-6-6 6 6-6 6','book'=>'M12 5v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2Z','edit'=>'m15 4 5 5M4 20l5-1L21 7l-5-5L4 14Zm0 0 5-1','monitor'=>'M3 4h18v13H3zM8 21h8m-4-4v4M7 8l3 3-3 3m6 0h4','file'=>'M14 2H5v20h14V7Zm0 0v5h5M8 12h8m-8 4h6','list'=>'M8 5h13M8 12h13M8 19h13M3 5h.01M3 12h.01M3 19h.01','chat'=>'M3 3h18v14H9l-6 4ZM7 8h10M7 12h7','calendar'=>'M3 5h18v16H3zM7 2v6m10-6v6M3 11h18','search'=>'m16 16 5 5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0','home'=>'m2 11 10-9 10 9M5 9v13h14V9m-10 13v-8h6v8'];return '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="'.($paths[$name]??$paths['arrow']).'"/></svg>';}
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('responsive-embeds');add_theme_support('wp-block-styles');add_theme_support('editor-styles');add_editor_style('portal.css');register_nav_menus(['primary'=>'상단 메뉴','footer_quick'=>'하단 펼침 메뉴','home_quick'=>'홈 바로가기','footer_links'=>'하단 안내 링크','footer_bottom'=>'하단 보조 링크']);});
add_action('wp_enqueue_scripts',function(){
    $uri=get_template_directory_uri();
    wp_enqueue_style('hpc-portal',$uri.'/portal.css',[],filemtime(__DIR__.'/portal.css'));
    wp_enqueue_style('naas-original',$uri.'/naas/usr/_common/_css/common_2024.css',['hpc-portal'],'0.6.0');
    wp_enqueue_style('naas-responsive',$uri.'/naas/usr/_common/_css/responsive_2024.css',['naas-original'],'0.6.0');
    wp_enqueue_style('hpc-naas-adapter',$uri.'/naas-adapter.css',['naas-responsive'],filemtime(__DIR__.'/naas-adapter.css'));
    wp_enqueue_script('hpc-portal',$uri.'/portal.js',[],filemtime(__DIR__.'/portal.js'),true);
});
add_filter('body_class',function($classes){$classes[]='hpc-portal';if(hp_current()==='application')$classes[]='portal-application';return $classes;});
add_filter('show_admin_bar',function($show){return is_admin()?$show:false;});
add_shortcode('hpc_quick_links',function(){
    $items=hp_menu_flat(hp_menu_tree('home_quick'));
    $s=(''.'<section class="section sec1">'.''.'<div class="wrap">'.''.'<h2 class="tit">'.esc_html(hpct_text('te8bb57341812675eec0e')).'</h2>'.''.'<div class="menu_list">'.'');
    foreach($items as $item){$icon=1;foreach($item->classes as $class)if(preg_match('/^hpc-icon-([1-8])$/',$class,$m))$icon=(int)$m[1];$s.='<div class="slick-slide">'.hp_menu_link($item,'<i class="ico'.$icon.'" aria-hidden="true"></i>').'</div>';}
    return $s.'</div></div></section>';
});

// Register the additional center information pages in the portal navigation.
add_filter('option_hpc_portal_pages_v1',function($pages){return array_merge((array)$pages,['center-equipment'=>143,'center-research'=>147]);});
