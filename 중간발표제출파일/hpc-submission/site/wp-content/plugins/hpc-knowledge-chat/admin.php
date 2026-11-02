<?php
if(!defined('ABSPATH'))exit;
add_action('admin_menu',function(){add_options_page('센터 AI 안내','센터 AI 안내','manage_options','hkc-settings','hkc_settings_page');});
add_action('admin_init',function(){register_setting('hkc-settings','hkc_settings',['sanitize_callback'=>function($v){
 if(!current_user_can('manage_options'))return hkc_config();
 return ['email'=>sanitize_email($v['email']??''),'phone'=>preg_replace('/[^0-9+\- ]/','',(string)($v['phone']??'')),
 'temporary'=>empty($v['temporary'])?0:1,'forum_enabled'=>empty($v['forum_enabled'])?0:1,'daily_limit'=>max(1,min(1000,(int)($v['daily_limit']??100)))];
}]);});
function hkc_settings_page(){
 if(!current_user_can('manage_options'))return;
 $c=hkc_config();global $wpdb;$usage=$wpdb->get_results('SELECT * FROM '.hkc_usage_table().' ORDER BY day DESC LIMIT 14');
 echo '<div class="wrap"><h1>센터 AI 안내</h1><p>Gemini 3.1 Flash-Lite · 관련 문단 최대 5개 · 문맥 목표 약 4,000토큰 · 출력 최대 768토큰</p><p>API 키: '.(hkc_key()?'설정됨':'미설정').'. 키는 웹 공개 경로 밖에 보관합니다.</p><form action="options.php" method="post">';
 settings_fields('hkc-settings');
 foreach(['email'=>'공개 문의 이메일','phone'=>'공개 문의 전화','daily_limit'=>'하루 최대 AI 호출 수 (UTC 기준)'] as $key=>$label)echo '<p><label>'.esc_html($label).' <input name="hkc_settings['.esc_attr($key).']" value="'.esc_attr($c[$key]).'"></label></p>';
 foreach(['temporary'=>'임시 연락처 표시','forum_enabled'=>'권한 확인 후 포럼 문단을 Google AI에 전달하도록 허용'] as $key=>$label)echo '<p><label><input type="checkbox" name="hkc_settings['.esc_attr($key).']" value="1" '.checked($c[$key],1,false).'> '.esc_html($label).'</label></p>';
 submit_button();echo '</form><h2>최근 사용량</h2><p>질문·대화 원문은 저장하지 않습니다. 출력에는 사고 토큰이 포함됩니다. 실제 비용은 Google 청구 내역이 기준입니다.</p><table class="widefat"><thead><tr><th>UTC 날짜</th><th>시도</th><th>입력 토큰</th><th>출력 토큰</th><th>연결 실패</th></tr></thead><tbody>';
 foreach($usage as $r)echo '<tr><td>'.esc_html($r->day).'</td><td>'.(int)$r->calls.'</td><td>'.(int)$r->input_tokens.'</td><td>'.(int)$r->output_tokens.'</td><td>'.(int)$r->failures.'</td></tr>';
 echo '</tbody></table></div>';
}
add_action('add_meta_boxes',function(){
 foreach(['post','page','hpc_notice','hpc_event'] as $type)add_meta_box('hkc-exclude','챗봇 검색',function($p){wp_nonce_field('hkc_exclude','hkc_exclude_nonce');echo '<label><input type="checkbox" name="hkc_exclude" value="1" '.checked(get_post_meta($p->ID,'_hkc_exclude',true),1,false).'> 챗봇 검색에서 제외</label>';},$type,'side');
});
add_action('save_post',function($id){
 if(!isset($_POST['hkc_exclude_nonce'])||!is_string($_POST['hkc_exclude_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hkc_exclude_nonce'])),'hkc_exclude'))return;
 if(!current_user_can('manage_options')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE))return;
 update_post_meta($id,'_hkc_exclude',empty($_POST['hkc_exclude'])?0:1);
});
