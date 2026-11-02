<?php
if(!defined('ABSPATH'))exit;
function hpb_groups(){return [''=>'홈페이지 이용 안내','g1'=>'원격 접속 및 인증','g2'=>'시스템 자원 및 작업 실행','g3'=>'프로그램 실행 및 소프트웨어 지원','g4'=>'연구데이터 및 스토리지','g5'=>'센터를 이해하는 11가지 질문'];}
add_action('add_meta_boxes',function(){add_meta_box('hpb-faq-options','FAQ 홈페이지 표시','hpb_faq_box','hkc_faq','side');});
function hpb_faq_box($post){wp_nonce_field('hpb-faq','hpb_faq_nonce');echo '<p><label for="hpb-group">분류</label></p><select id="hpb-group" name="hpb_group">';foreach(hpb_groups() as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected(get_post_meta($post->ID,'_hpb_group',true),$k,false).'>'.esc_html($v).'</option>';echo '</select><p><label for="hpb-note">안내 표시</label></p><input id="hpb-note" name="hpb_note" class="widefat" value="'.esc_attr(get_post_meta($post->ID,'_hpb_note',true)).'"><p>발행된 글만 홈페이지에 표시됩니다. 순서는 페이지 속성에서 지정합니다.</p>';}
add_action('save_post_hkc_faq',function($id){if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(!current_user_can('edit_post',$id)||!isset($_POST['hpb_faq_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hpb_faq_nonce'])),'hpb-faq'))return;$group=sanitize_key($_POST['hpb_group']??'');if(!array_key_exists($group,hpb_groups()))return;update_post_meta($id,'_hpb_group',$group);update_post_meta($id,'_hpb_note',sanitize_text_field(wp_unslash($_POST['hpb_note']??'')));});
add_filter('manage_hkc_faq_posts_columns',function($c){$c['hpb_group']='홈페이지 분류';return $c;});
add_action('manage_hkc_faq_posts_custom_column',function($c,$id){if($c==='hpb_group')echo esc_html(hpb_groups()[get_post_meta($id,'_hpb_group',true)]??'미분류');},10,2);
// Public Q&A must pass a separate review checkbox on every publication/edit.
add_action('add_meta_boxes_hkc_public_qna',function(){add_meta_box('hkc-review','공개 검토',function(){wp_nonce_field('hkc_review','hkc_review_nonce');echo '<label><input type="checkbox" name="hkc_reviewed" value="1"> 개인정보를 제거했고 공개 답변으로 검토했습니다.</label><p>발행 또는 수정 시 체크하지 않으면 초안으로 저장됩니다.</p>';},'hkc_public_qna','side');});
add_filter('wp_insert_post_data',function($data){
 if($data['post_type']==='hkc_public_qna' && $data['post_status']==='publish' && !(defined('WP_CLI')&&WP_CLI)){
  if(!current_user_can('manage_options')||empty($_POST['hkc_reviewed'])||empty($_POST['hkc_review_nonce'])||!is_string($_POST['hkc_review_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hkc_review_nonce'])),'hkc_review'))$data['post_status']='draft';
 }return $data;
});
// Classic editing ensures the review checkbox and publication occur in one authenticated request.
add_filter('use_block_editor_for_post_type',fn($enabled,$type)=>in_array($type,['hkc_faq','hkc_public_qna'],true)?false:$enabled,10,2);
