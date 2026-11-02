<?php
defined('ABSPATH') || exit;
// Menu items, labels, order and hierarchy are stored in WordPress.
function hp_menu_tree($location){
 $locations=get_nav_menu_locations();
 $items=empty($locations[$location])?[]:(wp_get_nav_menu_items($locations[$location])?:[]);
 $by_parent=[];
 foreach($items as $item)$by_parent[(int)$item->menu_item_parent][]=$item;
 $build=function($parent,$seen=[])use(&$build,$by_parent){
  $nodes=[];foreach($by_parent[$parent]??[] as $item){
   if(isset($seen[$item->ID]))continue;
   $next=$seen;$next[$item->ID]=true;
   $nodes[]=['item'=>$item,'children'=>$build($item->ID,$next)];
  }return $nodes;
 };return $build(0);
}
function hp_menu_flat($nodes){
 $items=[];foreach($nodes as $node){$items[]=$node['item'];$items=array_merge($items,hp_menu_flat($node['children']));}return $items;
}
function hp_menu_current($item){
 return $item->object==='page'&&(int)$item->object_id===(int)get_queried_object_id()&&is_page();
}
function hp_menu_link($item,$before='',$after=''){
 $target=$item->target==='_' . 'blank'?' target="_blank" rel="noopener noreferrer"':'';
 return '<a href="'.esc_url($item->url).'"'.$target.(hp_menu_current($item)?' aria-current="page"':'').'>'.$before.esc_html(wp_specialchars_decode($item->title,ENT_QUOTES)).$after.'</a>';
}
function hp_menu_list($nodes){
 $html='';foreach($nodes as $node){
  $html.='<li'.(hp_menu_current($node['item'])?' class="on"':'').'>'.hp_menu_link($node['item'],'','<span aria-hidden="true">›</span>');
  if($node['children'])$html.='<ul>'.hp_menu_list($node['children']).'</ul>';
  $html.='</li>';
 }return $html;
}
function hp_menu_context(){
 foreach(hp_menu_tree('primary') as $node){
  foreach(hp_menu_flat([$node]) as $item)if(hp_menu_current($item))return ['title'=>wp_specialchars_decode($node['item']->title,ENT_QUOTES),'nodes'=>$node['children']?:[$node]];
 }
 return ['title'=>hpct_text('t26bd551efdba9b898efe'),'nodes'=>hp_menu_tree('primary')];
}
add_action('admin_notices',function(){
 $screen=get_current_screen();if(!$screen||$screen->id!=='nav-menus')return;
 echo '<div class="notice notice-info"><p>상단·하단·홈 바로가기는 이 화면에서 이름, 순서, 링크와 계층을 편집합니다. 메뉴 위치를 선택해 연결하세요. 상단의 묶음 제목은 사용자 정의 링크(URL: #), 그 아래 항목은 하위 메뉴로 배치합니다. 홈 바로가기 아이콘은 CSS 클래스 hpc-icon-1 ~ hpc-icon-8로 선택합니다. 로그인·로그아웃 등 계정 메뉴는 로그인 상태에 따라 자동 표시됩니다.</p><p><a href="'.esc_url(admin_url('options-general.php?page=hpct-texts')).'">공통 화면 문구 편집</a></p></div>';
});
