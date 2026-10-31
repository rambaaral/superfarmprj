<?php
if (!defined('ABSPATH')) exit;

// Deliberately separate from WordPress posts: no public REST, feeds, search or sitemap entries.
function hpcf_table() { return hpc_table('forum'); }
function hpcf_install() {
    global $wpdb;
    if (get_option('hpc_forum_schema') === '1') return;
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta('CREATE TABLE '.hpcf_table()." (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        parent bigint unsigned NOT NULL DEFAULT 0,
        owner bigint unsigned NOT NULL,
        title varchar(150) NOT NULL DEFAULT '',
        body text NOT NULL,
        hidden tinyint NOT NULL DEFAULT 0,
        created datetime NOT NULL,
        updated datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY parent (parent),
        KEY owner (owner)
    ) ENGINE=InnoDB ".$wpdb->get_charset_collate().';');
    if ($wpdb->last_error) throw new RuntimeException('포럼 저장소를 준비하지 못했습니다.');
    update_option('hpc_forum_schema','1',false);
}
add_action('init','hpcf_install',20);

function hpcf_allowed($uid=0) {
    global $wpdb;
    $uid=$uid?:get_current_user_id();
    if (!$uid || !get_userdata($uid) || hpcw_pending($uid) || !hpcm_verified($uid)) return false;
    if (user_can($uid,'manage_options')) return true;
    $id=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.hpc_table()." WHERE owner=%d AND state='selected' LIMIT 1",$uid));
    return !$wpdb->last_error && (bool)$id;
}
function hpcf_require() {
    if (!hpcf_allowed()) throw new RuntimeException('선정·이용 승인된 회원만 포럼을 이용할 수 있습니다.');
}
function hpcf_url($args=[]) {
    $ids=get_option('hpc_portal_pages_v1',[]);
    return add_query_arg($args,!empty($ids['forum'])?get_permalink($ids['forum']):home_url('/forum/'));
}
function hpcf_row($id) {
    hpcf_require(); global $wpdb;
    $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpcf_table().' WHERE id=%d',$id));
    if (!$row || ($row->hidden && !hpc_admin())) throw new RuntimeException('글을 찾을 수 없습니다.');
    if ($row->parent) {
        $parent=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpcf_table().' WHERE id=%d AND parent=0',$row->parent));
        if (!$parent || ($parent->hidden && !hpc_admin())) throw new RuntimeException('글을 찾을 수 없습니다.');
    }
    return $row;
}
function hpcf_write() {
    hpcw_lock(); hpcf_require();
    if (!wp_verify_nonce(hpc_input('_wpnonce'),'hpc_forum')) throw new RuntimeException('화면이 만료되었습니다. 새로고침 후 다시 시도해 주세요.');
    global $wpdb;
    $op=hpc_input('forum_op'); $uid=get_current_user_id(); $now=current_time('mysql');
    if (!in_array($op,['topic','reply','edit','hide','restore'],true)) throw new RuntimeException('잘못된 요청입니다.');
    $row=null;$parent=0;
    if (in_array($op,['edit','hide','restore'],true)) {
        $row=hpcf_row(absint(hpc_input('entry')));
        if (!hpc_admin() && (int)$row->owner!==$uid) throw new RuntimeException('본인 글만 수정하거나 숨길 수 있습니다.');
        if ($op==='restore' && !hpc_admin()) throw new RuntimeException('복원은 관리자만 할 수 있습니다.');
        if (in_array($op,['hide','restore'],true)) {
            if ($wpdb->update(hpcf_table(),['hidden'=>$op==='hide'?1:0,'updated'=>$now],['id'=>$row->id])===false) throw new RuntimeException('저장하지 못했습니다.');
            return $op==='hide'&&!$row->parent ? 0 : (int)($row->parent?:$row->id);
        }
    }
    if ($op==='reply') {
        $topic=hpcf_row(absint(hpc_input('topic')));
        if ($topic->parent || $topic->hidden) throw new RuntimeException('댓글을 작성할 수 없는 글입니다.');
        $parent=(int)$topic->id;
    }
    $body=sanitize_textarea_field(hpc_input('body'));
    $is_topic=$op==='topic'||($op==='edit'&&!$row->parent);
    $title=$is_topic?sanitize_text_field(hpc_input('title')):'';
    if ($body==='' || mb_strlen($body)>5000 || ($is_topic&&($title===''||mb_strlen($title)>150))) throw new RuntimeException('제목은 150자, 내용은 5,000자 이내로 입력해 주세요.');
    if (!hpcm_limit('forum-write',(string)$uid,30,600)) throw new RuntimeException('작성 요청이 많습니다. 잠시 후 다시 시도해 주세요.');
    if ($op==='edit') {
        if ($wpdb->update(hpcf_table(),['title'=>$title,'body'=>$body,'updated'=>$now],['id'=>$row->id])===false) throw new RuntimeException('저장하지 못했습니다.');
        return (int)($row->parent?:$row->id);
    }
    if (!$wpdb->insert(hpcf_table(),['parent'=>$parent,'owner'=>$uid,'title'=>$title,'body'=>$body,'created'=>$now,'updated'=>$now])) throw new RuntimeException('저장하지 못했습니다.');
    return $parent?: (int)$wpdb->insert_id;
}
add_action('template_redirect',function() {
    if (hpc_portal_page_key()!=='forum') return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
    do_action('litespeed_control_set_nocache','Approved member forum');
    nocache_headers(); header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (!is_user_logged_in()) { auth_redirect(); exit; }
    if (!hpcf_allowed()) { status_header(403); return; }
    try {
        if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
            $id=hpcf_write(); wp_safe_redirect(hpcf_url($id?['topic'=>$id,'saved'=>1]:['saved'=>1]),303); exit;
        }
        if (isset($_GET['topic'])) {
            $id=absint(hpc_portal_query_value('topic')); $row=hpcf_row($id);
            if ($row->parent) throw new RuntimeException('글을 찾을 수 없습니다.');
        }
    } catch(Throwable $e) { $GLOBALS['hpc_forum_error']=$e->getMessage(); status_header(400); }
},6);

function hpcf_form($op,$row=null,$topic=0) {
    $suffix=$row?'edit-'.$row->id:$op;
    echo '<form method="post" class="question-form forum-form" action="'.esc_url(hpcf_url($topic?['topic'=>$topic]:[])).'">';
    wp_nonce_field('hpc_forum');
    echo '<input type="hidden" name="forum_op" value="'.esc_attr($op).'"><input type="hidden" name="entry" value="'.(int)($row->id??0).'"><input type="hidden" name="topic" value="'.$topic.'">';
    if ($op==='topic'||($op==='edit'&&!$row->parent)) echo '<label for="title-'.$suffix.'">제목 <span class="required">필수</span></label><input id="title-'.$suffix.'" name="title" maxlength="150" required value="'.esc_attr($row->title??'').'">';
    echo '<label for="body-'.$suffix.'">내용 <span class="required">필수</span></label><textarea id="body-'.$suffix.'" name="body" rows="6" maxlength="5000" required>'.esc_textarea($row->body??'').'</textarea><button type="submit">'.($op==='edit'?'수정 저장':($op==='reply'?'댓글 등록':'글 등록')).'</button></form>';
}
function hpcf_entry($row,$topic) {
    $user=get_userdata($row->owner);
    echo '<article class="forum-entry"><p class="forum-meta">'.esc_html($user?$user->display_name:'탈퇴한 회원').' · '.esc_html($row->created).($row->hidden?' · 숨김':'').'</p><div class="forum-body">'.nl2br(esc_html($row->body)).'</div>';
    if (hpc_admin() || (int)$row->owner===get_current_user_id()) {
        echo '<details><summary>글 수정</summary>';hpcf_form('edit',$row,$topic);echo '</details><form method="post" class="forum-moderation">';wp_nonce_field('hpc_forum');
        echo '<input type="hidden" name="entry" value="'.(int)$row->id.'"><input type="hidden" name="forum_op" value="'.($row->hidden?'restore':'hide').'"><button type="submit" class="secondary">'.($row->hidden?'글 복원':'글 숨기기').'</button></form>';
    }
    echo '</article>';
}
add_shortcode('hpc_portal_forum',function() {
    if (!hpcf_allowed()) return '<div class="notice-box"><strong>이용 승인 회원 전용 포럼입니다.</strong><p>신청 상태가 ‘선정’인 회원만 글과 댓글을 읽고 작성할 수 있습니다. 최종 제출만 완료한 경우에는 이용할 수 없습니다.</p><a class="button" href="'.esc_url(hpc_url(['view'=>'status'])).'">신청현황 확인</a></div>';
    ob_start(); echo '<div class="portal-service forum-service">';
    echo '<p class="notice-box">승인 회원과 관리자가 함께 이용하는 포럼입니다. 글과 댓글은 포럼 이용자에게 공개됩니다. 개인정보·비밀번호·비공개 연구 자료를 게시하지 마세요.</p>';
    if (!empty($GLOBALS['hpc_forum_error'])) echo '<p class="error" role="alert">'.esc_html($GLOBALS['hpc_forum_error']).'</p>';
    elseif (isset($_GET['saved'])) echo '<p class="success" role="status">저장되었습니다.</p>';
    try {
        global $wpdb;
        if (isset($_GET['topic'])) {
            $topic=hpcf_row(absint(hpc_portal_query_value('topic')));
            if ($topic->parent) throw new RuntimeException('글을 찾을 수 없습니다.');
            echo '<p><a href="'.esc_url(hpcf_url()).'">← 포럼 목록</a></p><h2>'.esc_html($topic->title).'</h2>';hpcf_entry($topic,(int)$topic->id);
            $where=$wpdb->prepare('parent=%d',$topic->id).(hpc_admin()?'':' AND hidden=0');
            $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.hpcf_table().' WHERE '.$where);
            $page=max(1,min(max(1,(int)ceil($total/20)),absint(hpc_portal_query_value('list_page'))));
            echo '<h2>댓글 ('.$total.')</h2>';
            foreach($wpdb->get_results('SELECT * FROM '.hpcf_table().' WHERE '.$where.$wpdb->prepare(' ORDER BY id ASC LIMIT 20 OFFSET %d',($page-1)*20)) as $reply) hpcf_entry($reply,(int)$topic->id);
            hpc_portal_pagination($total,$page,20);
            if (!$topic->hidden) { echo '<h2>댓글 작성</h2>';hpcf_form('reply',null,(int)$topic->id); }
        } else {
            hpc_portal_search('포럼 제목·내용 검색');
            $where='parent=0'.(hpc_admin()?'':' AND hidden=0'); $q=mb_substr(hpc_portal_query_value('q'),0,100);
            if ($q!=='') $where.=$wpdb->prepare(' AND (title LIKE %s OR body LIKE %s)','%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%');
            $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.hpcf_table().' WHERE '.$where);
            $page=max(1,min(max(1,(int)ceil($total/10)),absint(hpc_portal_query_value('list_page'))));
            echo '<div class="section-heading"><p>총 '.$total.'건</p><a class="button" href="#forum-write">글 작성</a></div><div class="forum-list">';
            foreach($wpdb->get_results('SELECT * FROM '.hpcf_table().' WHERE '.$where.$wpdb->prepare(' ORDER BY id DESC LIMIT 10 OFFSET %d',($page-1)*10)) as $row) {
                $user=get_userdata($row->owner);
                echo '<article class="forum-entry"><h2><a href="'.esc_url(hpcf_url(['topic'=>(int)$row->id])).'">'.esc_html($row->title).($row->hidden?' [숨김]':'').'</a></h2><p class="forum-meta">'.esc_html($user?$user->display_name:'탈퇴한 회원').' · '.esc_html(substr($row->created,0,10)).'</p></article>';
            }
            if (!$total) echo '<p class="empty-state">'.($q!==''?'검색 결과가 없습니다.':'아직 등록된 글이 없습니다. 첫 질문이나 사용 경험을 공유해 주세요.').'</p>';
            echo '</div>'; hpc_portal_pagination($total,$page);
            echo '<h2 id="forum-write">글 작성</h2>';hpcf_form('topic');
        }
    } catch(Throwable $e) { echo '<p class="error" role="alert">'.esc_html($e->getMessage()).'</p>'; }
    echo '</div>';return ob_get_clean();
});

// The old practice page is retired reversibly; bookmarks lead to the application guide.
add_action('template_redirect',function() {
    $ids=get_option('hpc_portal_pages_v1',[]);
    $path=rtrim((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
    $old=isset($_GET['page_id'])&&is_scalar($_GET['page_id'])?(int)$_GET['page_id']:0;
    if (hpc_portal_page_key()==='application-simulation' || $path===rtrim((string)wp_parse_url(home_url('/application-simulation'),PHP_URL_PATH),'/') || ($old && $old===(int)($ids['application-simulation']??0))) {
        wp_safe_redirect(!empty($ids['application-training'])?get_permalink($ids['application-training']):home_url('/'),301);exit;
    }
},1);
