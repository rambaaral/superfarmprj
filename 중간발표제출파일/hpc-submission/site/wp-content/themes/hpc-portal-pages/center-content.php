<?php
if(!defined('ABSPATH'))exit;
add_action('init',function(){
 $caps=array_fill_keys(['edit_post','read_post','delete_post','edit_posts','edit_others_posts','publish_posts','read_private_posts','delete_posts','delete_private_posts','delete_published_posts','delete_others_posts','edit_private_posts','edit_published_posts','create_posts'],'manage_options');
 foreach(['hpc_notice'=>['센터 공지사항','공지사항','dashicons-megaphone'], 'hpc_event'=>['센터 일정','일정','dashicons-calendar-alt'],'hpc_banner'=>['센터 알림판','알림판 이미지','dashicons-images-alt2']] as $type=>$v){
 register_post_type($type,['labels'=>['name'=>$v[0],'singular_name'=>$v[1],'add_new_item'=>$v[1].' 추가','edit_item'=>$v[1].' 수정','all_items'=>$v[0],'not_found'=>'등록된 항목이 없습니다.'],'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'show_in_rest'=>false,'publicly_queryable'=>false,'rewrite'=>false,'query_var'=>false,'capabilities'=>$caps,'map_meta_cap'=>false,'menu_icon'=>$v[2],'supports'=>$type==='hpc_banner'?['title','thumbnail','page-attributes']:['title','editor','revisions'],'exclude_from_search'=>true]);
 }
});
function hpcc_board_url($id=0){$map=get_option('hpc_portal_pages_v1',[]);$url=!empty($map['notices'])?get_permalink($map['notices']):home_url('/notices/');return $id?add_query_arg('notice_id',(int)$id,$url):$url;}
function hpcc_date($s){if(!is_string($s))return false;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);return $d&&$d->format('Y-m-d')===$s;}
function hpcc_notice($id){$p=get_post($id);return $p&&$p->post_type==='hpc_notice'&&$p->post_status==='publish'&&!$p->post_password?$p:null;}
add_action('add_meta_boxes',function(){
 add_meta_box('hpcc-event','일정 기간 (날짜 단위)',function($p){
 wp_nonce_field('hpcc_save','hpcc_nonce');$start=get_post_meta($p->ID,'_hpcc_start',true);$end=get_post_meta($p->ID,'_hpcc_end',true);
 echo '<p>하루 일정은 시작일과 종료일을 같은 날짜로 입력합니다. 기간의 시작일과 종료일 모두 캘린더에 포함됩니다.</p><p><label>시작일 <input type="date" name="hpcc_start" value="'.esc_attr($start).'" required></label></p><p><label>종료일 <input type="date" name="hpcc_end" value="'.esc_attr($end).'" required></label></p>';
 },'hpc_event','normal','high');
 add_meta_box('hpcc-banner','공지사항 연결',function($p){
 wp_nonce_field('hpcc_save','hpcc_nonce');$selected=(int)get_post_meta($p->ID,'_hpcc_notice',true);
 echo '<p>특성 이미지에 알림판 이미지를 지정하고, 클릭했을 때 열 공지사항을 선택하세요. 제목은 이미지의 설명으로 사용됩니다. 순서는 페이지 속성의 순서 값이 작은 항목부터 표시됩니다.</p><label>연결 공지사항 <select name="hpcc_notice" required><option value="">선택해 주세요</option>';
 foreach(get_posts(['post_type'=>'hpc_notice','post_status'=>'publish','numberposts'=>-1,'has_password'=>false]) as $n)echo '<option value="'.$n->ID.'" '.selected($selected,$n->ID,false).'>'.esc_html($n->post_title).'</option>';
 echo '</select></label>';
 },'hpc_banner','normal','high');
});
add_action('save_post',function($id,$p){
 if(!in_array($p->post_type,['hpc_event','hpc_banner'],true)||wp_is_post_revision($id)||wp_is_post_autosave($id)||!current_user_can('manage_options'))return;
 if(!isset($_POST['hpcc_nonce'])||!is_string($_POST['hpcc_nonce'])||!wp_verify_nonce(wp_unslash($_POST['hpcc_nonce']),'hpcc_save'))return;
 $error='';
 if($p->post_type==='hpc_event'){
 $a=isset($_POST['hpcc_start'])&&is_string($_POST['hpcc_start'])?sanitize_text_field(wp_unslash($_POST['hpcc_start'])):'';
 $b=isset($_POST['hpcc_end'])&&is_string($_POST['hpcc_end'])?sanitize_text_field(wp_unslash($_POST['hpcc_end'])):'';
 if(!hpcc_date($a)||!hpcc_date($b)||$b<$a)$error='시작일과 종료일을 확인하세요. 종료일은 시작일보다 빠를 수 없습니다.';
 else{update_post_meta($id,'_hpcc_start',$a);update_post_meta($id,'_hpcc_end',$b);}
 }else{
 $n=isset($_POST['hpcc_notice'])&&is_scalar($_POST['hpcc_notice'])?absint($_POST['hpcc_notice']):0;
 if(!hpcc_notice($n)||!get_post_thumbnail_id($id))$error='공개된 공지사항과 특성 이미지를 모두 지정해 주세요.';
 else update_post_meta($id,'_hpcc_notice',$n);
 }
 if($error){
 set_transient('hpcc_error_'.get_current_user_id(),$error,120);
 if($p->post_status==='publish'){global $wpdb;$wpdb->update($wpdb->posts,['post_status'=>'draft'],['ID'=>$id]);clean_post_cache($id);}
 }
},20,2);
add_action('admin_notices',function(){if(!current_user_can('manage_options'))return;$k='hpcc_error_'.get_current_user_id();if($m=get_transient($k)){delete_transient($k);echo '<div class="notice notice-error"><p>'.esc_html($m).' 공개되지 않고 초안으로 보관됩니다.</p></div>';}});
foreach(['hpc_event','hpc_banner'] as $pt){
 add_filter('manage_'.$pt.'_posts_columns',function($c)use($pt){$c['hpcc_info']=$pt==='hpc_event'?'기간':'연결 공지사항';return $c;});
 add_action('manage_'.$pt.'_posts_custom_column',function($c,$id)use($pt){if($c!=='hpcc_info')return;echo esc_html($pt==='hpc_event'?get_post_meta($id,'_hpcc_start',true).' ~ '.get_post_meta($id,'_hpcc_end',true):get_the_title((int)get_post_meta($id,'_hpcc_notice',true)));},10,2);
}
add_shortcode('hpc_notices',function(){
 $id=isset($_GET['notice_id'])&&is_scalar($_GET['notice_id'])?absint($_GET['notice_id']):0;
 ob_start();
 if($id){$p=hpcc_notice($id);if(!$p){echo '<p>공개된 공지사항을 찾을 수 없습니다.</p>';}else{
 echo '<article class="hpcc-notice"><h2>'.esc_html($p->post_title).'</h2><p class="hpcc-date">'.esc_html(get_the_date('Y.m.d',$p)).'</p><div class="hpcc-content">'.wp_kses_post(wpautop(do_blocks($p->post_content))).'</div></article>';
 }echo '<a class="button secondary" href="'.esc_url(hpcc_board_url()).'">공지사항 목록</a>';
 }else{
 if(current_user_can('manage_options'))echo '<p><a class="button" href="'.esc_url(admin_url('post-new.php?post_type=hpc_notice')).'">공지사항 작성</a></p>';
 $page=max(1,absint(is_scalar($_GET['notice_page']??null)?$_GET['notice_page']:1));
 $q=new WP_Query(['post_type'=>'hpc_notice','post_status'=>'publish','has_password'=>false,'posts_per_page'=>10,'paged'=>$page]);
 echo '<ul class="hpcc-notice-list">';
 foreach($q->posts as $p)echo '<li><a href="'.esc_url(hpcc_board_url($p->ID)).'"><span>'.esc_html($p->post_title).'</span><time>'.esc_html(get_the_date('Y.m.d',$p)).'</time></a></li>';
 if(!$q->posts)echo '<li class="hpcc-empty">등록된 공지사항이 없습니다.</li>';echo '</ul>';
 if($q->max_num_pages>1)echo '<nav class="pagination" aria-label="공지사항 페이지">'.paginate_links(['base'=>add_query_arg('notice_page','%#%',hpcc_board_url()),'format'=>'','current'=>$page,'total'=>$q->max_num_pages]).'</nav>';
 }
 return ob_get_clean();
});
add_shortcode('hpc_home_updates',function(){
 ob_start();echo '<section class="section hpcc-updates"><div class="wrap hpcc-update-grid"><section class="hpcc-news"><div class="hpcc-heading"><h2>공지사항</h2><a href="'.esc_url(hpcc_board_url()).'">전체보기 →</a></div><ul class="hpcc-notice-list">';
 $posts=get_posts(['post_type'=>'hpc_notice','post_status'=>'publish','has_password'=>false,'numberposts'=>5]);
 foreach($posts as $p)echo '<li><a href="'.esc_url(hpcc_board_url($p->ID)).'"><span>'.esc_html($p->post_title).'</span><time>'.esc_html(get_the_date('Y.m.d',$p)).'</time></a></li>';
 if(!$posts)echo '<li class="hpcc-empty">등록된 공지사항이 없습니다.</li>';
 echo '</ul></section><section class="hpcc-banners" aria-label="알림판"><div class="hpcc-heading"><h2>알림판</h2></div><div class="hpcc-carousel" data-hpcc-carousel>';
 echo '<a class="hpcc-slide hpcc-center-slide" href="'.esc_url(hp_url('center')).'"><span class="hpcc-center-art"><span class="hpcc-center-kicker">SUPERCOMPUTING CENTER</span><strong>연구의 가능성을 넓히는<br>슈퍼컴퓨팅센터</strong><span class="hpcc-center-link">센터소개 바로가기 →</span><span class="hpcc-center-orbit" aria-hidden="true"></span></span></a>';
 $i=1;foreach(get_posts(['post_type'=>'hpc_banner','post_status'=>'publish','has_password'=>false,'numberposts'=>-1,'orderby'=>['menu_order'=>'ASC','date'=>'DESC']]) as $p){
 $n=hpcc_notice((int)get_post_meta($p->ID,'_hpcc_notice',true));if(!$n||!get_post_thumbnail_id($p->ID))continue;
 echo '<a class="hpcc-slide" '.($i?'hidden':'').' href="'.esc_url(hpcc_board_url($n->ID)).'">'.wp_get_attachment_image(get_post_thumbnail_id($p->ID),'large',false,['alt'=>$p->post_title?:$n->post_title,'loading'=>$i?'lazy':'eager']).'</a>';$i++;
 }
 echo '<div class="hpcc-carousel-controls" role="group" aria-label="알림판 재생 제어"><button type="button" data-slide-prev aria-label="이전 이미지" title="이전 이미지"><span aria-hidden="true">‹</span></button><button type="button" data-slide-next aria-label="다음 이미지" title="다음 이미지"><span aria-hidden="true">›</span></button><button type="button" data-slide-pause aria-label="자동 넘김 정지" title="자동 넘김 정지" aria-pressed="false"><span aria-hidden="true">Ⅱ</span></button></div><span class="hpcc-slide-count" data-slide-count aria-label="전체 '.$i.'개 중 1번째 이미지">1/'.$i.'</span>';
 echo '</div></section></div></section>';return ob_get_clean();
});
function hpcc_events(){
 $out=[];foreach(get_posts(['post_type'=>'hpc_event','post_status'=>'publish','has_password'=>false,'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']) as $p){
 $a=get_post_meta($p->ID,'_hpcc_start',true);$b=get_post_meta($p->ID,'_hpcc_end',true);
 if(!hpcc_date($a)||!hpcc_date($b)||$b<$a)continue;
 $out[]=['title'=>$p->post_title,'start'=>$a,'end'=>$b,'description'=>wp_strip_all_tags(strip_shortcodes($p->post_content))];
 }return $out;
}
add_shortcode('hpc_calendar',function(){
 ob_start(); ?>
 <section class="calendar-panel hpcc-calendar" aria-labelledby="calendar-heading"><div class="section-heading"><div><span class="eyebrow">SCHEDULE</span><h2 id="calendar-heading">센터 일정</h2></div><?php if(current_user_can('manage_options')): ?><a href="<?php echo esc_url(admin_url('edit.php?post_type=hpc_event')); ?>">일정 관리 →</a><?php endif; ?></div>
 <div class="calendar-layout"><div data-center-calendar><div class="calendar-toolbar"><h3 data-calendar-title aria-live="polite"></h3><div><button type="button" data-month="-1" aria-label="이전 달">‹</button><button type="button" data-month="today">오늘</button><button type="button" data-month="1" aria-label="다음 달">›</button></div></div>
 <div class="hpcc-weekdays" aria-hidden="true"><?php foreach(['일','월','화','수','목','금','토'] as $day)echo '<span>'.$day.'</span>'; ?></div><div data-month-grid role="group" aria-label="센터 월간 일정"></div><p class="calendar-help">기간 일정은 이어진 막대로 표시됩니다. 일정이나 날짜를 선택하면 아래에서 상세 내용을 볼 수 있습니다.</p><noscript>날짜별 캘린더를 보려면 JavaScript를 켜 주세요.</noscript></div>
 <aside class="schedule-detail"><span class="eyebrow">선택한 날짜</span><h3 data-selected-date></h3><div data-event-list aria-live="polite"></div></aside></div>
 <script type="application/json" class="hpcc-events"><?php echo wp_json_encode(['today'=>current_datetime()->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m-d'),'events'=>hpcc_events()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script></section>
 <?php return ob_get_clean();
});
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_style('hpcc-center',get_template_directory_uri().'/center-content.css',['hpc-naas-adapter'],filemtime(__DIR__.'/center-content.css'));
 wp_enqueue_script('hpcc-center',get_template_directory_uri().'/center-content.js',['hpc-portal'],filemtime(__DIR__.'/center-content.js'),true);
},30);
