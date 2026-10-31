<?php
if (!defined('ABSPATH')) exit;

function hpcw_key($uid) { return 'hpc_withdrawal_'.(int)$uid; }
function hpcw_pending($uid) { return get_option(hpcw_key($uid), false); }
function hpcw_url() { return add_query_arg('hpc_withdrawal','1',home_url('/')); }

// Serialize portal requests and erasure so submissions cannot race eligibility checks.
// Named locks are connection scoped, and also released if PHP terminates unexpectedly.
function hpcw_lock() {
    global $wpdb;
    if (!empty($GLOBALS['hpcw_locked'])) return;
    $name='hpc-erase-'.substr(hash('sha256',DB_NAME.'|'.$wpdb->prefix),0,32);
    if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$name))!=='1')
        throw new RuntimeException('다른 신청 작업을 처리하고 있습니다. 잠시 후 다시 시도해 주세요.');
    $GLOBALS['hpcw_locked']=$name;
}
function hpcw_unlock() {
    global $wpdb;
    if (!empty($GLOBALS['hpcw_locked'])) {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$GLOBALS['hpcw_locked']));
        unset($GLOBALS['hpcw_locked']);
    }
}
add_action('shutdown','hpcw_unlock',PHP_INT_MAX);

function hpcw_eligible($uid) {
    global $wpdb;
    $u=get_userdata($uid);
    if (!$u || is_multisite() || $u->roles!==['subscriber'] || user_can($u,'manage_options'))
        throw new RuntimeException('일반 회원 계정만 직접 탈퇴할 수 있습니다. 운영 계정은 관리자에게 문의해 주세요.');
    $n=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.hpc_table()." WHERE owner=%d AND state NOT IN ('draft','selected','rejected')",$uid));
    if ($wpdb->last_error || $n===null) throw new RuntimeException('신청 상태를 확인할 수 없습니다.');
    if ((int)$n>0) throw new RuntimeException('진행 중인 신청이 있어 탈퇴할 수 없습니다. 접수·심사·보완 처리가 끝난 뒤 다시 시도해 주세요.');
    // A former staff account may own shared site content. Never erase it implicitly.
    $posts=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author=%d",$uid));
    if ($wpdb->last_error || $posts===null || (int)$posts>0)
        throw new RuntimeException('사이트 게시 콘텐츠가 연결된 계정입니다. 관리자에게 탈퇴 처리를 문의해 주세요.');
    return $u;
}
function hpcw_request($uid,$password,$confirmed,$nonce) {
    if (!$uid || $uid!==get_current_user_id() || !wp_verify_nonce($nonce,'hpc_withdrawal_'.$uid))
        throw new RuntimeException('본인 확인 화면이 만료되었습니다. 다시 로그인해 주세요.');
    if (!$confirmed) throw new RuntimeException('삭제 범위와 복구 불가 안내에 동의해 주세요.');
    if (!hpcm_limit('withdrawal-password',(string)$uid,5,900))
        throw new RuntimeException('확인 시도가 많습니다. 15분 뒤 다시 시도해 주세요.');
    hpcw_lock();
    $u=hpcw_eligible($uid);
    if (!wp_check_password($password,$u->user_pass,$uid)) throw new RuntimeException('현재 비밀번호가 일치하지 않습니다.');
    if (!hpcw_pending($uid) && !add_option(hpcw_key($uid),['uid'=>$uid,'requested'=>time(),'attempts'=>0,'error'=>''],'','no'))
        throw new RuntimeException('탈퇴 요청을 저장하지 못했습니다.');
    WP_Session_Tokens::get_instance($uid)->destroy_all();
}

add_filter('wp_authenticate_user',function($u){
    if ($u instanceof WP_User && hpcw_pending($u->ID)) return new WP_Error('hpc_withdrawing','탈퇴 처리 중인 계정입니다. 처리가 지연되면 운영자에게 문의해 주세요.');
    return $u;
},100);
add_action('init',function(){
    if (get_current_user_id() && hpcw_pending(get_current_user_id())) { wp_clear_auth_cookie(); wp_set_current_user(0); }
},0);
add_action('template_redirect',function(){
    $key=function_exists('hpc_portal_page_key')?hpc_portal_page_key():'';
    if (isset($_GET['hpc_withdrawal']) || isset($_GET['hpc_lab']) || in_array($key,['application','application-status','my-page','qna','forum'],true)) {
        try { hpcw_lock(); } catch(Throwable $e) { wp_die(esc_html($e->getMessage()),'',503); }
        // A request authenticated before another request queued erasure must not proceed.
        if (get_current_user_id() && hpcw_pending(get_current_user_id())) { wp_clear_auth_cookie(); wp_set_current_user(0); }
    }
    if (isset($_GET['hpc_withdrawal'])) { hpcw_page(); exit; }
},-100);
add_action('wp_ajax_hpc_live_preview',function(){
    try { hpcw_lock(); if(hpcw_pending(get_current_user_id())) throw new RuntimeException('탈퇴 처리 중'); }
    catch(Throwable $e) { wp_send_json_error(['message'=>'처리 중인 요청이 있습니다. 잠시 후 다시 로그인해 확인해 주세요.'],403); }
},-100);

// Refuse symlinks and paths outside the private root, including linked ancestors.
function hpcw_remove($path,$root) {
    $root=rtrim($root,'/');
    if (!str_starts_with($path,$root.'/') || str_contains(substr($path,strlen($root)+1),'..')) throw new RuntimeException('unsafe_path');
    $cursor=$root;
    foreach(explode('/',substr($path,strlen($root)+1)) as $part) {
        $cursor.='/'.$part;
        if(is_link($cursor)) throw new RuntimeException('symlink');
    }
    if(!file_exists($path)) return;
    $real=realpath($path);
    if(!$real || !str_starts_with($real,$root.'/')) throw new RuntimeException('unsafe_path');
    if(is_dir($path)) {
        $children=scandir($path); if($children===false) throw new RuntimeException('read_failed');
        foreach($children as $child) if($child!=='.' && $child!=='..') hpcw_remove($path.'/'.$child,$root);
        if(!rmdir($path)) throw new RuntimeException('delete_failed');
    } elseif(!is_file($path) || !unlink($path)) throw new RuntimeException('delete_failed');
}
function hpcw_erase($uid) {
    global $wpdb;
    if(PHP_SAPI!=='cli' || !hpcw_pending($uid)) throw new RuntimeException('not_queued');
    hpcw_lock();
    $u=hpcw_eligible($uid);
    $base=hpc_private_base();$root=realpath($base);
    if(!$root || is_link($base) || $root==='/') throw new RuntimeException('storage_unavailable');
    $apps=$wpdb->get_results($wpdb->prepare('SELECT id,files FROM '.hpc_table().' WHERE owner=%d',$uid));
    if($wpdb->last_error) throw new RuntimeException('db_read_failed');
    $tokens=[];$paths=[];
    foreach($apps as $a) {
        $files=json_decode($a->files,true);
        if(!is_array($files)) throw new RuntimeException('invalid_manifest');
        foreach($files as $f) {
            if(!preg_match('/^[a-f0-9]{40}$/D',$f['token']??'') || !in_array($f['ext']??'',['pdf','hwp','hwpx'],true)) throw new RuntimeException('invalid_manifest');
            // Corrupt cross-account references must fail closed, not erase another member's file.
            $shared=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.hpc_table().' WHERE owner<>%d AND files LIKE %s',$uid,'%'.$wpdb->esc_like($f['token']).'%'));
            if($wpdb->last_error || $shared===null || (int)$shared>0) throw new RuntimeException('shared_file');
            $tokens[]=$f['token'];$paths[]=$root.'/uploads/'.$f['token'].'.'.$f['ext'];
        }
        if(is_link($root.'/generated')) throw new RuntimeException('symlink');
        $dirs=glob($root.'/generated/'.(int)$a->id.'-*');
        if($dirs===false) throw new RuntimeException('read_failed');
        foreach($dirs as $dir) {
            if(!preg_match('/^'.(int)$a->id.'-[a-f0-9]{16}-r[0-9]+$/D',basename($dir))) throw new RuntimeException('invalid_generated_path');
            $paths[]=$dir;
        }
    }
    // Keep DB ownership records until all files are gone. A failed run is retryable.
    foreach(array_unique($paths) as $path) hpcw_remove($path,$root);
    require_once ABSPATH.'wp-admin/includes/user.php';
    if($wpdb->query('START TRANSACTION')===false) throw new RuntimeException('db_failed');
    try {
        foreach(array_unique($tokens) as $token) if($wpdb->delete(hpco_table(),['token'=>$token])===false) throw new RuntimeException('db_failed');
        foreach([hpc_table(),hpc_table('messages')] as $table) if($wpdb->delete($table,['owner'=>$uid])===false) throw new RuntimeException('db_failed');
        if($wpdb->query($wpdb->prepare('DELETE FROM '.hpcm_table().' WHERE user_id=%d OR email_key=%s',$uid,hpcm_email_key($u->user_email)))===false) throw new RuntimeException('db_failed');
        foreach(get_comments(['user_id'=>$uid,'status'=>'all','number'=>0]) as $comment) if(!wp_delete_comment($comment->comment_ID,true)) throw new RuntimeException('comment_delete_failed');
        if(function_exists('hpcf_table') && $wpdb->update(hpcf_table(),['owner'=>0,'title'=>'탈퇴한 회원의 글','body'=>'탈퇴한 회원의 글입니다.','hidden'=>1],['owner'=>$uid])===false) throw new RuntimeException('forum_erase_failed');
        if(!wp_delete_user($uid)) throw new RuntimeException('account_delete_failed');
        $remaining=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE ID=%d",$uid));
        if($wpdb->last_error || $remaining===null || (int)$remaining!==0) throw new RuntimeException('account_delete_failed');
        if($wpdb->query('COMMIT')===false) throw new RuntimeException('db_failed');
        delete_option(hpcw_key($uid));
    } catch(Throwable $e) { $wpdb->query('ROLLBACK'); clean_user_cache($uid); throw $e; }
}
function hpcw_worker() {
    global $wpdb;
    if(PHP_SAPI!=='cli') return;
    $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id LIMIT 2",$wpdb->esc_like('hpc_withdrawal_').'%'));
    foreach($keys as $key) {
        $job=get_option($key);$uid=(int)($job['uid']??0);
        if(!$uid || $key!==hpcw_key($uid)) continue;
        try { hpcw_erase($uid); }
        catch(Throwable $e) { $job['attempts']=(int)($job['attempts']??0)+1;$job['error']='처리 실패 · 관리자 점검 필요';$job['last_attempt']=time();update_option($key,$job,false); }
        finally { hpcw_unlock(); }
    }
}
add_action('hpc_ops_tick','hpcw_worker',20);
add_action('admin_notices',function(){
    if(!current_user_can('manage_options')) return;
    global $wpdb;$keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('hpc_withdrawal_').'%'));
    $failed=0;foreach($keys as $key) { $job=get_option($key);if(!empty($job['error']))$failed++; }
    if($keys) echo '<div class="notice notice-warning"><p>회원 탈퇴 처리 대기: '.count($keys).'건 / 점검 필요: '.$failed.'건. 서버 정기 작업이 삭제를 처리합니다. 오류가 지속되면 개발 담당자에게 문의해 주세요.</p></div>';
});
function hpcw_page() {
    nocache_headers();header('X-Robots-Tag: noindex, nofollow');$error='';$done=false;
    if(!is_user_logged_in()) { auth_redirect(); return; }
    $uid=get_current_user_id();
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        try { hpcw_request($uid,hpcm_input('current_password'),hpcm_input('erase_confirm')==='yes',hpcm_input('_wpnonce'));wp_logout();$done=true; }
        catch(Throwable $e) { $error=$e->getMessage(); }
    }
    status_header($error?400:200);
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>회원 탈퇴</title><style>body{font:17px/1.8 system-ui,sans-serif;background:#f4f6f8;color:#182b43;margin:0}main{max-width:680px;margin:40px auto;padding:28px;background:white;border-radius:12px}input[type=password]{display:block;width:95%;padding:12px;margin:10px 0}button{padding:12px 24px;background:#a21d2a;color:white;border:0;border-radius:6px;font:inherit}a{color:#155fa0}.error{color:#a21d2a}</style></head><body><main><h1>회원 탈퇴</h1>';
    if($done) echo '<h2>탈퇴 요청이 접수되었습니다.</h2><p>모든 로그인 세션이 종료되었습니다. 계정과 자료는 서버 정기 작업에서 삭제됩니다. 보통 5분 이내에 처리를 시작하지만 대기량이나 오류에 따라 지연될 수 있습니다. 이 화면은 삭제 완료 확인이 아닙니다.</p>';
    else {
        if($error) echo '<p class="error" role="alert">'.esc_html($error).'</p>';
        try { hpcw_eligible($uid);$allowed=true; } catch(Throwable $e) { $allowed=false;echo '<p>'.esc_html($e->getMessage()).'</p>'; }
        echo '<p>탈퇴하면 로그인 계정, 작성 중 및 선정·미선정 신청서, 서명 첨부파일, 생성 문서와 본인 문의·답변이 삭제됩니다. 본인이 작성한 포럼 글·댓글의 내용과 작성자 정보도 제거됩니다. 제출·접수·검토·심의·보완 중 신청이 있으면 탈퇴할 수 없습니다.</p><p>삭제 후 복구할 수 없습니다. 필요한 문서는 먼저 내려받으세요. 기존 백업 사본과 별도 보안·메일 로그는 이 기능으로 즉시 삭제되지 않습니다.</p>';
        if($allowed) { echo '<form method="post" action="'.esc_url(hpcw_url()).'">';wp_nonce_field('hpc_withdrawal_'.$uid);echo '<label for="current-password">현재 비밀번호</label><input id="current-password" type="password" name="current_password" autocomplete="current-password" required><p><label><input type="checkbox" name="erase_confirm" value="yes" required> 위 삭제 범위와 복구 불가 안내를 확인했으며 회원 탈퇴에 동의합니다.</label></p><button type="submit">회원 탈퇴 및 자료 삭제 요청</button></form>'; }
    }
    echo '<p><a href="'.esc_url(home_url('/')).'">메인으로 돌아가기</a></p></main></body></html>';
}
