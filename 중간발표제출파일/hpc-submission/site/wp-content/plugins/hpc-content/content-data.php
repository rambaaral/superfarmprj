<?php
if(!defined('ABSPATH'))exit;
function hpcc_board_url($id=0){$map=get_option('hpc_portal_pages_v1',[]);$url=!empty($map['notices'])?get_permalink($map['notices']):home_url('/notices/');return $id?add_query_arg('notice_id',(int)$id,$url):$url;}
function hpcc_date($s){if(!is_string($s))return false;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);return $d&&$d->format('Y-m-d')===$s;}
function hpcc_notice($id){$p=get_post($id);return $p&&$p->post_type==='hpc_notice'&&$p->post_status==='publish'&&!$p->post_password?$p:null;}
function hpcc_event_date($id){
 $p=get_post($id);
 if(!$p||$p->post_type!=='hpc_event'||$p->post_status!=='publish'||$p->post_password)return '';
 $start=get_post_meta($p->ID,'_hpcc_start',true);$end=get_post_meta($p->ID,'_hpcc_end',true);
 return hpcc_date($start)&&hpcc_date($end)&&$end>=$start?$start:'';
}
function hpcc_event_url($id){
 if(!hpcc_event_date($id))return '';
 $page=(int)get_option('page_on_front');
 if(!$page||get_post_status($page)!=='publish'||!has_shortcode(get_post_field('post_content',$page),'hpc_calendar'))return '';
 return add_query_arg('center_event',(int)$id,get_permalink($page)).'#calendar-heading';
}
function hpcc_events(){
 $out=[];foreach(get_posts(['post_type'=>'hpc_event','post_status'=>'publish','has_password'=>false,'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']) as $p){
 $a=get_post_meta($p->ID,'_hpcc_start',true);$b=get_post_meta($p->ID,'_hpcc_end',true);
 if(!hpcc_date($a)||!hpcc_date($b)||$b<$a)continue;
 $out[]=['title'=>$p->post_title,'start'=>$a,'end'=>$b,'description'=>wp_strip_all_tags(strip_shortcodes($p->post_content))];
 }return $out;
}
