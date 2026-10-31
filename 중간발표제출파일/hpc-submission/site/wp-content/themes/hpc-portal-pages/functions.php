<?php
if(!defined('ABSPATH')) exit;
require_once __DIR__.'/auth-design.php';
add_action('after_setup_theme',function(){require_once __DIR__.'/center-content.php';},20);
function hp_pages(){return ['home'=>'메인페이지','center'=>'센터소개','notices'=>'공지사항','application-training'=>'신청 안내','usage-training'=>'사용 교육','usage-simulation'=>'사용 모의','application'=>'이용신청','application-status'=>'신청현황','my-page'=>'마이페이지','faq'=>'FAQ 자주 묻는 질문','usage-rules'=>'이용 수칙','ai-chatbot'=>'AI 챗봇','qna'=>'Q&A 게시판','forum'=>'이용자 포럼'];}
function hp_url($key){$ids=get_option('hpc_portal_pages_v1',[]);return !empty($ids[$key])?get_permalink($ids[$key]):home_url('/');}
function hp_current(){return function_exists('hpc_portal_page_key')?hpc_portal_page_key():'';}
function hp_group($key){foreach(hp_groups() as $name=>$keys)if(in_array($key,$keys,true))return $name;return '슈퍼컴퓨팅센터';}
function hp_groups(){return ['센터소개'=>['center','notices'],'사용 안내·실습'=>['application-training','usage-training','usage-simulation'],'이용신청'=>['application','application-status'],'이용자 지원'=>['my-page','faq','qna','forum','usage-rules'],'AI 챗봇'=>['ai-chatbot']];}
// Keep WordPress menu labels and ordering inside the archive's grouped navigation.
function hp_navigation_items($keys){
    $ids=get_option('hpc_portal_pages_v1',[]);$locations=get_nav_menu_locations();
    $menu=wp_get_nav_menu_items($locations['primary']??get_option('hpc_portal_menu_v1'))?:[];$result=[];
    foreach($menu as $item){if($item->object!=='page')continue;$key=array_search((int)$item->object_id,array_map('intval',$ids),true);if($key!==false&&in_array($key,$keys,true))$result[$key]=['title'=>$item->title,'url'=>$item->url];}
    foreach($keys as $key)if(!isset($result[$key]))$result[$key]=['title'=>hp_pages()[$key],'url'=>hp_url($key)];
    return array_replace(array_fill_keys($keys,null),$result);
}
function hp_icon($name){$paths=['arrow'=>'M5 12h14m-6-6 6 6-6 6','book'=>'M12 5v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2Z','edit'=>'m15 4 5 5M4 20l5-1L21 7l-5-5L4 14Zm0 0 5-1','monitor'=>'M3 4h18v13H3zM8 21h8m-4-4v4M7 8l3 3-3 3m6 0h4','file'=>'M14 2H5v20h14V7Zm0 0v5h5M8 12h8m-8 4h6','list'=>'M8 5h13M8 12h13M8 19h13M3 5h.01M3 12h.01M3 19h.01','chat'=>'M3 3h18v14H9l-6 4ZM7 8h10M7 12h7','calendar'=>'M3 5h18v16H3zM7 2v6m10-6v6M3 11h18','search'=>'m16 16 5 5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0','home'=>'m2 11 10-9 10 9M5 9v13h14V9m-10 13v-8h6v8'];return '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="'.($paths[$name]??$paths['arrow']).'"/></svg>';}
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('responsive-embeds');add_theme_support('wp-block-styles');add_theme_support('editor-styles');add_editor_style('portal.css');register_nav_menus(['primary'=>'상단 메뉴']);});
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
add_shortcode('hpc_calendar',function(){return '<section class="calendar-panel" aria-labelledby="calendar-heading"><div class="section-heading"><div><span class="eyebrow">SCHEDULE</span><h2 id="calendar-heading">센터 일정</h2></div><span class="muted">교육 · 모집 · 주요 일정</span></div><div class="calendar-layout"><div data-calendar><div class="calendar-toolbar"><h3 data-calendar-title aria-live="polite"></h3><div><button type="button" data-month="-1" aria-label="이전 달">‹</button><button type="button" data-month="today">오늘</button><button type="button" data-month="1" aria-label="다음 달">›</button></div></div><table class="calendar-table"><caption class="screen-reader-text">날짜를 선택해 일정을 확인하세요</caption><thead><tr><th scope="col">일</th><th scope="col">월</th><th scope="col">화</th><th scope="col">수</th><th scope="col">목</th><th scope="col">금</th><th scope="col">토</th></tr></thead><tbody></tbody></table><noscript>캘린더를 이용하려면 JavaScript를 켜 주세요.</noscript></div><aside class="schedule-detail"><span class="eyebrow">선택한 날짜</span><h3 data-selected-date></h3><span class="calendar-empty-icon" aria-hidden="true">'.hp_icon('calendar').'</span><p><strong>등록된 일정이 없습니다.</strong></p><p class="muted">교육·모집 일정은 준비 중입니다.<br>확정된 일정은 추후 안내합니다.</p></aside></div></section>';});
add_shortcode('hpc_quick_links',function(){
    $items=['application'=>[2,'이용신청'],'application-status'=>[3,'신청현황'],'application-training'=>[4,'신청 안내'],'forum'=>[6,'이용자 포럼'],'usage-training'=>[7,'사용 교육'],'usage-simulation'=>[1,'사용 모의'],'my-page'=>[5,'마이페이지'],'qna'=>[8,'Q&A 게시판']];
    $s='<section class="section sec1"><div class="wrap"><h2 class="tit">자주찾는 메뉴</h2><div class="menu_list">';
    foreach($items as $key=>[$icon,$label])$s.='<div class="slick-slide"><a href="'.esc_url(hp_url($key)).'"><i class="ico'.$icon.'" aria-hidden="true"></i>'.$label.'</a></div>';
    return $s.'</div></div></section>';
});
