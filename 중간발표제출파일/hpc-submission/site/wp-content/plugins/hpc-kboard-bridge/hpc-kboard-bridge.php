<?php
/* Plugin Name: HPC KBoard Bridge
Description: Existing member permissions, Q&A privacy and answer notifications for KBoard.
Version: 1.0.0
*/
defined('ABSPATH') || exit;
function hkb_ids(){return get_option('hkb_boards',[]);}
function hkb_kind($id){return array_search((int)$id,array_map('intval',hkb_ids()),true);}
function hkb_row($id){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kboard_board_content WHERE uid=%d",$id));}
function hkb_member(){return is_user_logged_in()&&function_exists('hpcm_verified')&&hpcm_verified(get_current_user_id());}
function hkb_forum(){return function_exists('hpcf_allowed')&&hpcf_allowed(get_current_user_id());}
function hkb_read($r){
 if(!$r)return false;if(current_user_can('manage_options'))return true;
 if(!get_option('hkb_enabled'))return false;
 if($r->status!=='')return false;
 if(hkb_kind($r->board_id)==='forum')return hkb_forum();
 if(hkb_kind($r->board_id)==='qna')return !$r->secret||(get_current_user_id()>0&&(int)$r->member_uid===get_current_user_id());
 return false;
}
add_filter('kboard_is_reader',function($allow,$owner,$secret,$board){$kind=hkb_kind($board->id);if(!$kind)return $allow;if(current_user_can('manage_options'))return true;if(!get_option('hkb_enabled'))return false;if($kind==='forum')return hkb_forum();return !$secret||(get_current_user_id()>0&&(int)$owner===get_current_user_id());},100,4);
add_filter('kboard_is_writer',function($allow,$board){$k=hkb_kind($board->id);if(!$k)return $allow;return current_user_can('manage_options')||(get_option('hkb_enabled')&&($k==='forum'?hkb_forum():hkb_member()));},100,2);
add_filter('kboard_is_editor',function($allow,$owner,$prevent,$board){$k=hkb_kind($board->id);if(!$k)return $allow;return current_user_can('manage_options')||(get_option('hkb_enabled')&&(int)$owner===get_current_user_id()&&get_current_user_id()>0&&($k==='forum'?hkb_forum():hkb_member()));},100,4);
add_filter('kboard_is_reply',function($allow,$board){return hkb_kind($board->id)?false:$allow;},100,2);
add_filter('kboard_comments_is_writer',function($allow,$builder){$b=$builder->board??null;return $b&&hkb_kind($b->id)?hkb_kind($b->id)==='forum'&&hkb_forum():$allow;},100,2);
function hkb_comment_guard($id,$content_id,$board){if(hkb_kind($board->id)&&(!hkb_read(hkb_row($content_id))||hkb_kind($board->id)!=='forum'))wp_die(hpct_text('td9b221b76cf60b28b76b'),403);}
foreach(['kboard_comments_pre_insert','kboard_pre_comments_insert','kboard_comments_pre_update','kboard_comments_pre_delete'] as $hook)add_action($hook,'hkb_comment_guard',1,3);
// Enforce the same rules for every KBoard list, including widgets and search.
function hkb_where($where,$board_id){
 if(current_user_can('manage_options'))return $where;
 global $wpdb;$ids=hkb_ids();$t=$wpdb->prefix.'kboard_board_content';$q=(int)($ids['qna']??0);$f=(int)($ids['forum']??0);$u=get_current_user_id();
 if(!get_option('hkb_enabled'))return "($where) AND $t.board_id NOT IN ($q,$f)";
 $guard="($t.board_id<>$q OR ($t.status='' AND ($t.secret='' OR ($t.member_uid=$u AND $u>0))))";
 if(!hkb_forum())$guard.=" AND $t.board_id<>$f";
 return "($where) AND ($guard)";
}
foreach(['kboard_list_where','kboard_latest_where','kboard_notice_list_where','kboard_popular_list_where','kboard_reply_list_where'] as $hook)add_filter($hook,'hkb_where',100,2);
add_filter('kboard_content_value',function($value,$name,$content){
 $r=$content->row;if(!$r||empty($r->board_id)||!hkb_kind($r->board_id))return $value;
 if(in_array($name,['title','content'],true)&&!hkb_read($r))return $name==='title'?hpct_text('t4691e025e1cb3aae5814'):'';
 if($name==='content'&&hkb_kind($r->board_id)==='qna'&&function_exists('kboard_mod')&&kboard_mod()==='document'){
  $answer=(new KBContentOption($r->uid))->hkb_answer;
  $value.=(''.'<section class="hkb-answer">'.''.'<h2>'.esc_html(hpct_text('td8365e908b2834dc0cca')).'</h2>'.'').($answer?wpautop(esc_html($answer)):(''.'<p>'.esc_html(hpct_text('t7210a3e98a355bb49ac3')).'</p>'.'')).'</section>';
 }
 return $value;
},100,3);
function hkb_prewrite($id,$bid,$content,$board){
 $k=hkb_kind($bid);if(!$k)return;
 foreach(array_keys($_POST) as $key)if(strpos($key,'kboard_option_hkb_')===0)unset($_POST[$key]);
 if(!current_user_can('manage_options')&&(!get_option('hkb_enabled')||!($k==='forum'?hkb_forum():hkb_member())))wp_die(hpct_text('t3cc59cc56c1f3f1d378b'),403);
 if($content->parent_uid)wp_die(hpct_text('ta1eed622f994cb9136be'));
 if($id&&!current_user_can('manage_options')){$old=hkb_row($id);if(!$old||(int)$old->member_uid!==get_current_user_id())wp_die(hpct_text('tc7bff45dcbb38631632a'),403);$content->member_uid=$old->member_uid;$content->status=$old->status;}
 if($k==='qna'){
  $o=$id?new KBContentOption($id):null;
  if(!current_user_can('manage_options')&&$o&&$o->hkb_private_lock)$content->secret='true';
  elseif(isset($_POST['hkb_visibility']))$content->secret=$_POST['hkb_visibility']==='public'?'':'true';
  elseif(!$id)$content->secret='true';
  if($id&&current_user_can('manage_options')&&$content->secret)$o->hkb_private_lock='1';
  $content->search=$content->secret?'3':'1';
 }else $content->search='3';
}
add_action('kboard_pre_document_insert','hkb_prewrite',1,4);
add_action('kboard_pre_document_update','hkb_prewrite',1,4);
// Dedicated public/private control, without changing the vendor skin files.
add_action('kboard_skin_header',function($builder){},10,1);
add_filter('kboard_skin_editor',function($html){return $html;});
add_action('wp_footer',function(){if(!is_page(16))return;?>
<script>document.querySelectorAll('.kboard-form').forEach(function(form){if(form.querySelector('[name="hkb_visibility"]'))return;const old=form.querySelector('[name="secret"]');const wrap=document.createElement('div');wrap.className='kboard-attr-row';const label=document.createElement('label');label.textContent=window.hpctText('tc43afe1bdbfaed6c2537');const select=document.createElement('select');select.name='hkb_visibility';select.innerHTML='<option value="private">'+window.hpctEscape(window.hpctText('t35930ce08aecee00a47b'))+'</option><option value="public">'+window.hpctEscape(window.hpctText('t7110cb765ecc8b9192ec'))+'</option>';if(old){const uid=form.querySelector('[name=uid]');select.value=uid&&uid.value&&uid.value!=='0'?(old.checked?'private':'public'):'private';old.closest('.kboard-attr-row')?.style.setProperty('display','none','important');}label.appendChild(select);wrap.appendChild(label);const note=document.createElement('p');note.textContent='관리자가 비공개로 전환한 문의는 작성자가 다시 공개할 수 없습니다. 답변이 등록되면 가입 이메일로 알림을 보냅니다.';wrap.appendChild(note);form.insertBefore(wrap,form.querySelector('.kboard-control'));});</script>
<?php });
add_action('init',function(){if(!get_option('hkb_enabled'))return;
 add_shortcode('hpc_portal_qna',function(){return (''.'<p>'.esc_html(hpct_text('tb68d51c74e3c25724079')).'</p>'.'').do_shortcode('[kboard id="'.(int)hkb_ids()['qna'].'"]');});
 add_shortcode('hpc_portal_forum',function(){if(!hkb_forum())return (''.'<p>'.esc_html(hpct_text('t40afcda97edb2662186f')).'</p>'.'');return do_shortcode('[kboard id="'.(int)hkb_ids()['forum'].'"]');});
},1000);
add_action('template_redirect',function(){
 if(!get_option('hkb_enabled'))return;
 $pages=get_option('hpc_portal_pages_v1',[]);$forum=(int)($pages['forum']??0);$qna=(int)($pages['qna']??0);
 if(($qna&&is_page($qna))||($forum&&is_page($forum)))nocache_headers();
 if($forum&&is_page($forum)&&isset($_GET['topic'])){
  $map=get_option('hkb_migration_map',[]);$legacy=is_scalar($_GET['topic'])?absint($_GET['topic']):0;
  $id=(int)($map['forum'][$legacy]??0);$url=get_permalink($forum);
  if($id)$url=add_query_arg(['mod'=>'document','uid'=>$id],$url);
  wp_safe_redirect($url,301);exit;
 }
},1);
function hkb_mail_queue($uid,$answer){
 if(trim($answer)==='')return;$key='hkb_notice_'.$uid.'_'.hash('sha256',$answer);if(!add_option($key,['uid'=>$uid,'state'=>'queued','tries'=>0], '', false))return;
 hkb_mail_send($key);
}
function hkb_mail_send($key){
 if(strpos($key,'hkb_notice_')!==0)return;$job=get_option($key);if(!$job||$job['state']==='sent')return;
 $r=hkb_row($job['uid']);if(!$r||hkb_kind($r->board_id)!=='qna')return;$u=get_userdata($r->member_uid);
 $job['tries']++;$ok=false;
 if($u&&is_email($u->user_email))$ok=wp_mail($u->user_email,hpct_text('tecb0de595a50d65826fe'),hpct_text('tf87d5c019641ad0e92b0').add_query_arg(['mod'=>'document','uid'=>$r->uid],home_url('/qna/')).hpct_text('t110939308ad3fcb43e19'));
 $job['state']=$ok?'sent':'failed';$job['updated']=current_time('mysql');update_option($key,$job,false);
 $o=new KBContentOption($r->uid);$o->hkb_mail_state=$ok?hpct_text('t363921fa555aac1fa96d'):hpct_text('tc76bcedd965ccb8fc3bf');
 if(!$ok&&$job['tries']<3&&!wp_next_scheduled('hkb_mail_retry',[$key]))wp_schedule_single_event(time()+300,'hkb_mail_retry',[$key]);
}
add_action('hkb_mail_retry','hkb_mail_send');
add_action('admin_menu',function(){
 if(!get_option('hkb_enabled'))return;
 remove_submenu_page('hpb-boards','hpb-qna');remove_submenu_page('hpb-boards','hpb-forum');
 add_submenu_page('hpb-boards',hpct_text('t837ae1853737e9f881b5'),hpct_text('t837ae1853737e9f881b5'),'manage_options','hkb-qna','hkb_admin');
 add_submenu_page('hpb-boards',hpct_text('t9a661b25500c7e994ce5'),hpct_text('t9a661b25500c7e994ce5'),'manage_options','hkb-forum','hkb_admin');
},200);
add_action('admin_init',function(){if(!get_option('hkb_enabled'))return;$page=sanitize_key($_GET['page']??'');if(in_array($page,['hpb-qna','hpb-forum'],true)){wp_safe_redirect(admin_url('admin.php?page='.str_replace('hpb-','hkb-',$page)));exit;}});
function hkb_admin(){
 if(!current_user_can('manage_options'))wp_die(hpct_text('ta20ccb3e6b01e3245b66'));global $wpdb;$forum=($_GET['page']??'')==='hkb-forum';$kind=$forum?'forum':'qna';$bid=(int)hkb_ids()[$kind];$slug='hkb-'.$kind;$uid=absint($_GET['uid']??0);
 echo '<div class="wrap"><h1>'.($forum?hpct_text('t9a661b25500c7e994ce5'):hpct_text('t837ae1853737e9f881b5')).'</h1>';
 if($uid){$r=hkb_row($uid);if(!$r||(int)$r->board_id!==$bid)wp_die(hpct_text('t8ca39005a239e2ce0233'));$o=new KBContentOption($uid);
  if($_SERVER['REQUEST_METHOD']==='POST'){
   check_admin_referer('hkb-save-'.$uid);$hash=hash('sha256',wp_json_encode([$r,$o->hkb_answer]));
   if(!hash_equals($hash,sanitize_text_field(wp_unslash($_POST['version']??''))))echo (''.'<div class="notice notice-error">'.''.'<p>'.esc_html(hpct_text('tc006dca9a9e01195b27c')).'</p>'.''.'</div>'.'');
   else{$c=new KBContent();$c->initWithUID($uid);$c->title=sanitize_text_field(wp_unslash($_POST['title']??''));$c->content=wp_kses_post(wp_unslash($_POST['body']??''));$c->secret=isset($_POST['private'])?'true':'';$c->status=isset($_POST['hidden'])?'pending_approval':'';$c->search=$forum||$c->secret?'3':'1';$c->update=current_time('YmdHis');
    if(!$c->title)echo (''.'<div class="notice notice-error">'.''.'<p>'.esc_html(hpct_text('ta1df5e01ab83eece6330')).'</p>'.''.'</div>'.'');
    else{$c->updateContent();if(!$forum){$old=$o->hkb_answer;$answer=sanitize_textarea_field(wp_unslash($_POST['answer']??''));$o->hkb_answer=$answer;$o->hkb_private_lock=$c->secret?'1':'';if($old!==$answer)hkb_mail_queue($uid,$answer);}echo (''.'<div class="notice notice-success">'.''.'<p>'.esc_html(hpct_text('t513ee667b349dacab2d0')).'</p>'.''.'</div>'.''); $r=hkb_row($uid);$o=new KBContentOption($uid);}
   }
  }
  echo '<p><a href="'.esc_url(admin_url('admin.php?page='.$slug)).(''.'">'.esc_html(hpct_text('td7fcbd676ea7c6604e02')).'</a>'.''.'</p>'.''.'<p>'.esc_html(hpct_text('t1f070b53e30865228655'))).(int)$r->member_uid.hpct_text('t533b98a0a5df9cbd3e1f').esc_html($r->date).'</p><form method="post">';wp_nonce_field('hkb-save-'.$uid);
  echo '<input type="hidden" name="version" value="'.esc_attr(hash('sha256',wp_json_encode([$r,$o->hkb_answer]))).(''.'">'.''.'<p>'.''.'<label for="hkb-title">'.esc_html(hpct_text('t08b17e433630742bc700')).'</label>'.''.'</p>'.''.'<input class="large-text" id="hkb-title" name="title" maxlength="127" value="'.'').esc_attr($r->title).(''.'">'.''.'<p>'.''.'<label for="hkb-body">'.esc_html(hpct_text('tcb0f24046b508710d631')).'</label>'.''.'</p>'.''.'<textarea id="hkb-body" name="body" class="large-text" rows="10">'.'').esc_textarea($r->content).'</textarea>';
  if(!$forum)echo '<p><label><input type="checkbox" name="private" '.checked((bool)$r->secret,true,false).(''.'>'.esc_html(hpct_text('t5deac364e07a694d2ddb')).'</label>'.''.'</p>'.''.'<p>'.''.'<label for="hkb-answer">'.esc_html(hpct_text('td8365e908b2834dc0cca')).'</label>'.''.'</p>'.''.'<textarea name="answer" id="hkb-answer" rows="8" class="large-text">'.'').esc_textarea($o->hkb_answer).(''.'</textarea>'.''.'<p>'.esc_html(hpct_text('tff90816843482eefadfe')).'</p>'.''.'<p>'.esc_html(hpct_text('t3dd023f9b3bae7722898'))).esc_html($o->hkb_mail_state?:hpct_text('t371c38636d4f70fab874')).'</p>';
  echo '<p><label><input type="checkbox" name="hidden" '.checked($r->status!=='',true,false).(''.'>'.esc_html(hpct_text('t849ed40f75deb146529f')).'</label>'.''.'</p>'.'');submit_button(hpct_text('t5fb926229090001c0a61'));echo '</form></div>';return;
 }
 $s=sanitize_text_field(wp_unslash($_GET['s']??''));$state=sanitize_key($_GET['status']??'');$page=max(1,absint($_GET['paged']??1));$where=$wpdb->prepare("board_id=%d AND status<>'trash'",$bid);
 if($s!==''){$like='%'.$wpdb->esc_like($s).'%';$where.=$wpdb->prepare(' AND (title LIKE %s OR content LIKE %s)',$like,$like);}if($state==='private')$where.=" AND secret<>''";if($state==='hidden')$where.=" AND status<>''";
 $table=$wpdb->prefix.'kboard_board_content';$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY uid DESC LIMIT 25 OFFSET %d",($page-1)*25));
 echo (''.'<p>'.esc_html(hpct_text('t1e5caa3ac20f1a9aedd7')).'<a href="'.'').esc_url(admin_url('admin.php?page=kboard_content_list')).(''.'">'.esc_html(hpct_text('tf1e17f8a1b57fcf94bea')).'</a>'.' · '.'<a href="'.'').esc_url(home_url('/'.$kind.'/')).(''.'">'.esc_html(hpct_text('ta560c01b09e9785d5aeb')).'</a>'.''.'</p>'.''.'<form>'.''.'<input type="hidden" name="page" value="'.'').esc_attr($slug).(''.'">'.''.'<label>'.esc_html(hpct_text('t71b593f8528fba4b5288')).'<input name="s" value="'.'').esc_attr($s).(''.'">'.''.'</label>'.' '.'<button class="button">'.esc_html(hpct_text('t4b8d541ebe6d9003aba3')).'</button>'.''.'</form>'.''.'<p>'.esc_html(hpct_text('t90252b07abe2958686a5'))).$total.(esc_html(hpct_text('tf5fd44f22ad30ad631dc')).'</p>'.''.'<table class="widefat striped">'.''.'<thead>'.''.'<tr>'.''.'<th>'.esc_html(hpct_text('t08b17e433630742bc700')).'</th>'.''.'<th>'.esc_html(hpct_text('t50cee238f4b72d21cadd')).'</th>'.''.'<th>'.esc_html(hpct_text('te10195a1239fb321327b')).'</th>'.''.'<th>'.esc_html(hpct_text('tbcf903c5d5c8ae1e904c')).'</th>'.''.'</tr>'.''.'</thead>'.''.'<tbody>'.'');
 foreach($rows as $r){$o=new KBContentOption($r->uid);echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page='.$slug.'&uid='.$r->uid)).'">'.esc_html($r->title).'</a></td><td>'.($r->secret?hpct_text('te79eae03696aad798bb1'):($forum?hpct_text('t1df09eb588d49205ca4f'):hpct_text('t9d1ba9f49a6e6992f15d'))).'</td><td>'.($r->status?hpct_text('t0eeb676f581789493719'):($forum?hpct_text('t43c0ab2bce568cfbe2bd'):($o->hkb_answer?hpct_text('t69b0fad0fe5bd72b257d'):hpct_text('td953c55f09131cfe7c55')))).'</td><td>'.(int)$r->member_uid.'</td></tr>';}
 echo '</tbody></table><p>'.paginate_links(['base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$page,'total'=>max(1,ceil($total/25))]).'</p></div>';
}
function hkb_home(){if(!current_user_can('manage_options'))wp_die(hpct_text('ta20ccb3e6b01e3245b66'));global $wpdb;$ids=hkb_ids();$rows=[];foreach(['qna'=>hpct_text('t837ae1853737e9f881b5'),'forum'=>hpct_text('tca35e1a4c50b4a0dc2de')] as $k=>$label){$n=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kboard_board_content WHERE board_id=%d AND status<>'trash'",$ids[$k]));$rows[]=[$label,$n.hpct_text('tf5fd44f22ad30ad631dc'),'admin.php?page=hkb-'.$k];}$rows[]=['FAQ',wp_count_posts('hkc_faq')->publish.hpct_text('tf5fd44f22ad30ad631dc'),'edit.php?post_type=hkc_faq'];$rows[]=[hpct_text('ta8f562bb9bb06d2a6f40'),wp_count_posts('hpc_notice')->publish.hpct_text('tf5fd44f22ad30ad631dc'),'edit.php?post_type=hpc_notice'];echo (''.'<div class="wrap">'.''.'<h1>'.esc_html(hpct_text('t278b3db56d1f46e7033b')).'</h1>'.''.'<p>'.esc_html(hpct_text('t373db0f6573153b1ac0b')).'</p>'.''.'<table class="widefat striped">'.''.'<thead>'.''.'<tr>'.''.'<th>'.esc_html(hpct_text('t4732a58f5f977c4b3be7')).'</th>'.''.'<th>'.esc_html(hpct_text('t26a70f243076b72154af')).'</th>'.''.'<th>'.esc_html(hpct_text('t29ae8f308ff1d9963b4d')).'</th>'.''.'</tr>'.''.'</thead>'.''.'<tbody>'.'');foreach($rows as $r)echo '<tr><td>'.esc_html($r[0]).'</td><td>'.esc_html($r[1]).'</td><td><a class="button" href="'.esc_url(admin_url($r[2])).(''.'">'.esc_html(hpct_text('t8503492d41a2a0c70643')).'</a>'.''.'</td>'.''.'</tr>'.'');echo (''.'</tbody>'.''.'</table>'.''.'<p>'.esc_html(hpct_text('tefcadb057372b4432588')).'</p>'.''.'<p>'.esc_html(hpct_text('t4c76f884c57393fb6651')).'</p>'.''.'</div>'.'');}
