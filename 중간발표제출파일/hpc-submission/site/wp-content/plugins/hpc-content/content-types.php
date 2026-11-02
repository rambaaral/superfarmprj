<?php
if(!defined('ABSPATH'))exit;
add_action('init',function(){
 $caps=array_fill_keys(['edit_post','read_post','delete_post','edit_posts','edit_others_posts','publish_posts','read_private_posts','delete_posts','delete_private_posts','delete_published_posts','delete_others_posts','edit_private_posts','edit_published_posts','create_posts'],'manage_options');
 foreach(['hpc_notice'=>[hpct_text('t3ca5220d50ee5f9fc048'),hpct_text('ta8f562bb9bb06d2a6f40'),'dashicons-megaphone'], 'hpc_event'=>[hpct_text('td4fec2d03767dd6f308f'),hpct_text('t510d062ea38e7953e887'),'dashicons-calendar-alt'],'hpc_banner'=>[hpct_text('tb5e23bbaf310bb0312df'),hpct_text('t3e84adf82201655a6072'),'dashicons-images-alt2']] as $type=>$v){
 register_post_type($type,['labels'=>['name'=>$v[0],'singular_name'=>$v[1],'add_new_item'=>$v[1].hpct_text('t5b2f5a5f4012c14fb857'),'edit_item'=>$v[1].hpct_text('t9ae6f1ce7b4c4aa2d129'),'all_items'=>$v[0],'not_found'=>hpct_text('t7ac08303efe1506024aa')],'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'show_in_rest'=>false,'publicly_queryable'=>false,'rewrite'=>false,'query_var'=>false,'capabilities'=>$caps,'map_meta_cap'=>false,'menu_icon'=>$v[2],'supports'=>$type==='hpc_banner'?['title','thumbnail','page-attributes']:['title','editor','revisions'],'exclude_from_search'=>true]);
 }
});
add_action('init',function(){
 foreach (['hkc_faq'=>hpct_text('t8033f201e1e94726c64e'),'hkc_public_qna'=>hpct_text('t2a67c07ed44c9f1743d1')] as $type=>$name) {
  register_post_type($type,['labels'=>['name'=>$name,'singular_name'=>$name,'add_new_item'=>$name.hpct_text('t5b2f5a5f4012c14fb857')],
   'public'=>true,'show_in_rest'=>true,'rewrite'=>false,'supports'=>['title','editor','revisions'],
   'capabilities'=>['edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','read_private_posts'=>'manage_options','delete_posts'=>'manage_options'],'map_meta_cap'=>true]);
 }
},15);
add_action('init', function(){
 add_post_type_support('hkc_faq','page-attributes');
 $type=get_post_type_object('hkc_faq'); if($type){$type->labels->name='FAQ';$type->labels->singular_name='FAQ';$type->labels->menu_name='FAQ';$type->labels->add_new_item='FAQ 추가';$type->labels->edit_item='FAQ 편집';}
}, 100);
