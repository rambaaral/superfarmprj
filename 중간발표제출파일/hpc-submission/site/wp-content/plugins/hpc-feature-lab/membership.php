<?php
// Email-first enrollment. WordPress remains the only password/session authority.
if (!defined('ABSPATH')) exit;
function hpcm_table($kind='pending') { global $wpdb; return $wpdb->prefix.'hpc_member_'.$kind; }
function hpcm_install() {
    global $wpdb;
    if (get_option('hpcm_schema')==='1') return;
    require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
    dbDelta('CREATE TABLE '.hpcm_table()." (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      email varchar(254) NOT NULL,
      email_key char(64) NOT NULL,
      token_hash char(64) NOT NULL,
      user_id bigint unsigned NOT NULL DEFAULT 0,
      expires bigint unsigned NOT NULL,
      created bigint unsigned NOT NULL,
      PRIMARY KEY  (id), UNIQUE KEY email_key (email_key), KEY expires (expires)
    ) ENGINE=InnoDB $c;");
    dbDelta('CREATE TABLE '.hpcm_table('limits')." (
      bucket char(64) NOT NULL,
      hits int unsigned NOT NULL DEFAULT 0,
      expires bigint unsigned NOT NULL,
      PRIMARY KEY  (bucket), KEY expires (expires)
    ) ENGINE=InnoDB $c;");
    update_option('hpcm_schema','1',false);
    if (!wp_next_scheduled('hpcm_cleanup')) wp_schedule_event(time()+3600,'hourly','hpcm_cleanup');
}
add_action('init','hpcm_install',1);
add_action('hpcm_cleanup',function(){global $wpdb; $now=time(); $wpdb->query($wpdb->prepare('DELETE FROM '.hpcm_table().' WHERE expires < %d',$now)); $wpdb->query($wpdb->prepare('DELETE FROM '.hpcm_table('limits').' WHERE expires < %d',$now));});
function hpcm_url() { return add_query_arg('hpc_member','join',home_url('/')); }
function hpcm_key($value) { return hash_hmac('sha256',$value,wp_salt('auth')); }
function hpcm_email_key($email) { return hpcm_key(strtolower(trim($email))); }
function hpcm_verified($uid=0) {
    $u=get_userdata($uid?:get_current_user_id());
    return $u && (user_can($u,'manage_options') || hash_equals(hpcm_email_key($u->user_email),(string)get_user_meta($u->ID,'hpcm_verified_email',true)));
}
function hpcm_require() { if (!hpcm_verified()) throw new RuntimeException('이메일 인증을 완료한 후 이용해 주세요.'); }
// Atomic per-window counters, including limits for requests involving nonexistent users.
function hpcm_limit($scope,$value,$max,$seconds) {
    global $wpdb; $now=time(); $bucket=hpcm_key($scope.'|'.$value.'|'.intdiv($now,$seconds)); $t=hpcm_table('limits');
    $ok=$wpdb->query($wpdb->prepare("INSERT INTO $t (bucket,hits,expires) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1",$bucket,$now+$seconds*2));
    return $ok!==false && (int)$wpdb->get_var($wpdb->prepare("SELECT hits FROM $t WHERE bucket=%s",$bucket)) <= $max;
}
function hpcm_request($email) {
    global $wpdb; $email=trim($email);
    if (strlen($email)>254 || !is_email($email)) throw new RuntimeException('올바른 이메일 주소를 입력해 주세요.');
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown'); // Never trust client-supplied forwarding headers.
    if (!hpcm_limit('ip',$ip,20,3600) || !hpcm_limit('site','all',200,3600)) throw new RuntimeException('요청이 많습니다. 잠시 후 다시 시도해 주세요.');
    $key=hpcm_email_key($email);
    if (!hpcm_limit('email-minute',$key,1,60)) throw new RuntimeException('재발송은 60초 후 다시 시도해 주세요.');
    if (!hpcm_limit('email-hour',$key,5,3600)) throw new RuntimeException('이 이메일의 시간당 발송 요청 한도에 도달했습니다. 한 시간 후 다시 시도해 주세요.');
    $u=get_user_by('email',$email);
    if (is_user_logged_in() && (!$u || $u->ID!==get_current_user_id())) throw new RuntimeException('현재 로그인한 계정의 이메일로 인증해 주세요.');
    if ($u && hpcm_verified($u->ID)) {
        // Explain existing accounts privately by email; never reset passwords or grant a session.
        $body="슈퍼컴퓨팅센터 계정 이용 안내\n\n이 이메일로 이미 등록된 계정이 있습니다. 새 계정을 만들 필요 없이 기존 비밀번호로 로그인해 주세요.\n".wp_login_url(hpcm_url())."\n\n비밀번호를 잊었다면 아래에서 재설정을 요청할 수 있습니다.\n".wp_lostpassword_url()."\n\n일반 회원 가입 시험에는 기존 관리자 계정과 다른 이메일을 사용해 주세요.\n직접 요청하지 않았다면 이 메일을 무시해 주세요. 계정이나 비밀번호는 변경되지 않았습니다.";
        if (!wp_mail($email,'[슈퍼컴퓨팅센터] 계정 이용 안내',$body)) {
            update_option('hpcm_mail_failure_at',gmdate('c'),false);
            throw new RuntimeException('안내 메일을 보내지 못했습니다. 잠시 후 다시 시도하거나 운영자에게 문의해 주세요.');
        }
        return;
    }
    $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $now=time();
    $ok=$wpdb->query($wpdb->prepare('INSERT INTO '.hpcm_table().' (email,email_key,token_hash,user_id,expires,created) VALUES (%s,%s,%s,%d,%d,%d) ON DUPLICATE KEY UPDATE email=VALUES(email),token_hash=VALUES(token_hash),user_id=VALUES(user_id),expires=VALUES(expires),created=VALUES(created)',$email,$key,$hash,$u?$u->ID:0,$now+1800,$now));
    if ($ok===false) throw new RuntimeException('인증 요청을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    $url=add_query_arg(['verify'=>$token,'key'=>$key],hpcm_url());
    $body="슈퍼컴퓨팅센터 이메일 인증 안내\n\n아래 링크를 열고 사이트에서 인증을 계속해 주세요.\n".$url."\n\n유효시간은 30분이며 한 번만 사용할 수 있습니다.\n직접 요청하지 않았다면 이 메일을 무시해 주세요.\n비밀번호나 신청서 내용을 메일로 보내지 마세요.";
    if (!wp_mail($email,'[슈퍼컴퓨팅센터] 회원가입 이메일 인증',$body)) {
        $wpdb->query($wpdb->prepare('DELETE FROM '.hpcm_table().' WHERE email_key=%s AND token_hash=%s',$key,$hash));
        update_option('hpcm_mail_failure_at',gmdate('c'),false);
        throw new RuntimeException('인증 메일을 보내지 못했습니다. 잠시 후 다시 시도하거나 운영자에게 문의해 주세요.');
    }
}
function hpcm_pending($key,$token,$lock=false) {
    global $wpdb;
    if (!preg_match('/^[a-f0-9]{64}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
    $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpcm_table().' WHERE email_key=%s'.($lock?' FOR UPDATE':''),$key));
    return $row && (int)$row->expires>time() && hash_equals($row->token_hash,hash('sha256',$token)) ? $row : null;
}
function hpcm_complete($key,$token,$name,$password,$confirm) {
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    try {
        $r=hpcm_pending($key,$token,true);
        if (!$r) throw new RuntimeException('링크가 만료되었거나 이미 사용되었습니다. 인증 메일을 다시 요청해 주세요.');
        if ($r->user_id) {
            $u=get_userdata((int)$r->user_id);
            if (!$u || get_current_user_id()!==$u->ID || !hash_equals(hpcm_email_key($u->user_email),$key)) throw new RuntimeException('인증을 요청한 기존 계정으로 먼저 로그인해 주세요.');
            $uid=$u->ID;
        } else {
            if (is_user_logged_in() || email_exists($r->email)) throw new RuntimeException('이미 가입된 계정은 로그인 후 이메일 인증을 진행해 주세요.');
            if (mb_strlen($name)<1 || mb_strlen($name)>60) throw new RuntimeException('이름은 1~60자로 입력해 주세요.');
            if (mb_strlen($password)<15 || mb_strlen($password)>128 || $password!==$confirm) throw new RuntimeException('비밀번호는 15~128자로 입력하고 확인란과 같게 입력해 주세요. 긴 문장도 사용할 수 있습니다.');
            $uid=wp_insert_user(['user_login'=>'hpc_'.bin2hex(random_bytes(12)), 'user_email'=>$r->email,'display_name'=>$name,'nickname'=>$name,'user_pass'=>$password,'role'=>'subscriber']);
            if (is_wp_error($uid)) throw new RuntimeException('가입을 완료하지 못했습니다. 이미 가입했다면 로그인해 주세요.');
        }
        update_user_meta($uid,'hpcm_verified_email',$key);
        update_user_meta($uid,'hpcm_verified_at',gmdate('c'));
        update_user_meta($uid,'hpcm_notice_version','2026-10-20');
        $wpdb->delete(hpcm_table(),['id'=>$r->id],['%d']);
        if ($wpdb->query('COMMIT')===false) throw new RuntimeException('저장에 실패했습니다. 다시 시도해 주세요.');
        clean_user_cache($uid); return $uid;
    } catch(Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
}
function hpcm_cookie($name,$value,$expires) {
    setcookie($name,$value,['expires'=>$expires,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
}
function hpcm_input($name) { return isset($_POST[$name])&&is_string($_POST[$name]) ? wp_unslash($_POST[$name]) : ''; }
function hpcm_csrf($browser) { return hash_hmac('sha256','form|'.$browser,wp_salt('nonce')); }
add_filter('register_url',fn($url)=>hpcm_url());
add_action('login_form_register',function(){wp_safe_redirect(hpcm_url());exit;});
add_filter('login_message',function($message){return $message.'<p class="message"><a href="'.esc_url(hpcm_url()).'">이메일 인증으로 회원가입 / 기존 계정 이메일 인증</a></p>';});
// Existing users may log in to verify; no application or document access until verified.
add_action('template_redirect',function(){
    if (isset($_GET['hpc_member'])) { hpcm_page(); exit; }
    $key=function_exists('hpc_portal_page_key')?hpc_portal_page_key():'';
    if ((isset($_GET['hpc_lab']) || in_array($key,['application','application-status','my-page','qna'],true)) && is_user_logged_in() && !hpcm_verified()) { nocache_headers(); wp_safe_redirect(hpcm_url()); exit; }
},-20);
add_action('wp_ajax_hpc_live_preview',function(){ if (!hpcm_verified()) wp_send_json_error(['message'=>'이메일 인증을 완료해 주세요.'],403); },-20);
add_filter('manage_users_columns',function($cols){$cols['hpcm_verified']='이메일 인증';return $cols;});
add_filter('manage_users_custom_column',function($value,$column,$uid){if($column!=='hpcm_verified')return $value; $u=get_userdata($uid);return user_can($u,'manage_options')?'관리자 (별도 관리)':(hpcm_verified($uid)?'인증 완료 · '.esc_html(get_user_meta($uid,'hpcm_verified_at',true)):'인증 필요');},10,3);
add_action('admin_notices',function(){if(current_user_can('manage_options') && get_option('hpcm_mail_failure_at')) echo '<div class="notice notice-warning"><p>회원 인증 메일 발송 실패 기록이 있습니다. GoSMTP 시험 발송을 확인하세요. 최근 실패(UTC): '.esc_html(get_option('hpcm_mail_failure_at')).'</p></div>';});
function hpcm_page() {
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
    do_action('litespeed_control_set_nocache','HPC email verification'); nocache_headers();
    header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow'); header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $browser=isset($_COOKIE['hpcm_browser'])&&is_string($_COOKIE['hpcm_browser'])?$_COOKIE['hpcm_browser']:'';
    if (!preg_match('/^[a-f0-9]{64}$/D',$browser)) { $browser=bin2hex(random_bytes(32)); hpcm_cookie('hpcm_browser',$browser,time()+3600); }
    // GET sets a short-lived HttpOnly continuation cookie, but never verifies/consumes a token.
    if (isset($_GET['verify'],$_GET['key']) && is_string($_GET['verify']) && is_string($_GET['key'])) {
        $value=$_GET['key'].'.'.$_GET['verify'];
        if (preg_match('/^[a-f0-9]{64}\.[a-f0-9]{64}$/D',$value)) hpcm_cookie('hpcm_link',$value,time()+1800);
        wp_safe_redirect(hpcm_url()); exit;
    }
    $parts=explode('.',is_string($_COOKIE['hpcm_link']??null)?$_COOKIE['hpcm_link']:'');
    [$key,$token]=count($parts)===2?$parts:['','']; $pending=hpcm_pending($key,$token);
    $error='';$notice='';$done=false;
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        try {
            if (!isset($_COOKIE['hpcm_browser']) || !hash_equals(hpcm_csrf($browser),hpcm_input('csrf'))) throw new RuntimeException('화면이 만료되었습니다. 새로고침 후 다시 진행해 주세요.');
            if (hpcm_input('op')==='request') {
                if (hpcm_input('agree')!=='yes') throw new RuntimeException('개인정보 수집·이용 안내를 확인해 주세요.');
                hpcm_request(hpcm_input('email'));
                $notice='인증 또는 기존 계정 이용 안내 메일의 발송 요청을 완료했습니다. 받은편지함과 스팸함을 확인해 주세요. 재발송은 60초 후 가능합니다.';
            } elseif (hpcm_input('op')==='complete') {
                if (!hpcm_limit('complete',(string)($_SERVER['REMOTE_ADDR']??''),30,3600)) throw new RuntimeException('요청이 많습니다. 잠시 후 다시 시도해 주세요.');
                if(hpcm_input('agree')!=='yes') throw new RuntimeException('개인정보 수집·이용 안내를 확인해 주세요.');
                hpcm_complete($key,$token,sanitize_text_field(hpcm_input('member_name')),hpcm_input('password'),hpcm_input('confirm'));
                hpcm_cookie('hpcm_link','',time()-3600);$pending=null;$done=true;
                $notice='이메일 인증이 완료되었습니다. 이메일 주소와 비밀번호로 로그인해 주세요.';
            } else throw new RuntimeException('잘못된 요청입니다.');
        } catch(Throwable $e) { status_header(400); $error=$e->getMessage(); }
    }
    $csrf=hpcm_csrf($browser); $u=wp_get_current_user();
    ?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>회원가입 · 슈퍼컴퓨팅센터</title><style>
    *{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#233347;font:16px/1.7 system-ui,sans-serif}header{background:#fff;border-bottom:4px solid #003675;padding:22px max(20px,calc((100% - 1000px)/2));font-weight:700}a{color:#004a91}main{max-width:660px;margin:40px auto;background:white;padding:36px;border:1px solid #dce4eb;border-radius:12px}h1{font-size:29px;margin-top:0}.steps{color:#526479}label{display:block;font-weight:600;margin:22px 0 6px}input:not([type=checkbox]){width:100%;padding:13px;border:1px solid #8998a8;border-radius:5px;font:inherit}button,.button{display:inline-block;background:#003675;color:#fff;border:0;border-radius:5px;padding:13px 22px;font:inherit;cursor:pointer;margin-top:20px;text-decoration:none}.notice{padding:16px;background:#edf6ff}.error{padding:16px;background:#fff0ed;color:#8b2424}.small,details{font-size:14px;color:#526479}nav{display:flex;gap:20px;margin-top:25px;flex-wrap:wrap}input[type=checkbox]{width:20px;height:20px;vertical-align:middle}details{background:#f5f7fa;padding:15px;margin-top:20px}input:focus,button:focus,a:focus{outline:3px solid #79b9ed;outline-offset:3px}@media(max-width:700px){main{margin:20px 12px;padding:24px}}

/* Shared membership identity, without changing the verification flow. */
body{background:radial-gradient(ellipse at top left,#e2f0f1,transparent 48%),#f4f7fa;color:#193349}
header{background:#102a43;border-bottom:0;padding-top:24px;padding-bottom:24px}
header a{color:#fff;text-decoration:none;letter-spacing:-.5px}
main{border:1px solid #dce5ed;border-radius:18px;box-shadow:0 14px 45px #16314d08;margin:44px auto;padding:38px}
h1{letter-spacing:-1px;color:#142c42;font-size:30px}
.steps{font-size:13px;color:#087e82;font-weight:600;padding-bottom:18px;border-bottom:1px solid #e3eaf0}
input:not([type=checkbox]){border-radius:9px;background:#fcfdff;border-color:#bccbd7;min-height:50px}
button,.button{background:#087e82;border-radius:9px;min-height:48px;font-weight:600}
button:hover,.button:hover{background:#08676b}
form>button{width:100%}
details,.notice,.error{border-radius:10px}
a{color:#08737a}nav{border-top:1px solid #e3eaf0;padding-top:22px}
input[type=checkbox]{accent-color:#087e82}
@media(max-width:700px){main{margin:24px 16px;padding:24px}h1{font-size:26px}}

    </style></head><body><header><a href="<?php echo esc_url(home_url('/')); ?>">슈퍼컴퓨팅센터</a></header><main><p class="steps">이메일 확인 → 비밀번호 설정 → 가입 완료</p><h1><?php echo $done?'인증 완료':($pending?'인증 계속하기':(is_user_logged_in()?'기존 계정 이메일 인증':'회원가입')); ?></h1>
    <?php if($error)echo '<p class="error" role="alert">'.esc_html($error).'</p>'; if($notice)echo '<p class="notice" role="status">'.esc_html($notice).'</p>'; ?>
    <?php if(!$done && !hpcm_verified()): ?>
    <form method="post" action="<?php echo esc_url(hpcm_url()); ?>"><input type="hidden" name="csrf" value="<?php echo esc_attr($csrf); ?>">
    <input type="hidden" name="op" value="<?php echo $pending?'complete':'request'; ?>">
    <?php if($pending): ?>
      <p>메일함 소유를 확인한 뒤 아래 버튼으로 인증을 완료합니다. 링크를 열기만 해서는 가입되지 않습니다.</p>
      <?php if($pending->user_id): ?><p>기존 계정은 해당 계정으로 로그인한 상태에서 인증을 완료해 주세요. 기존 비밀번호는 바뀌지 않습니다.</p>
      <?php else: ?><label for="member-name">이름</label><input id="member-name" name="member_name" maxlength="60" autocomplete="name" required>
      <label for="member-password">비밀번호</label><input id="member-password" type="password" name="password" minlength="15" maxlength="128" autocomplete="new-password" required><p class="small">15~128자의 긴 비밀번호를 입력해 주세요. 비밀번호 관리자의 붙여넣기를 사용할 수 있습니다.</p>
      <label for="member-confirm">비밀번호 확인</label><input id="member-confirm" type="password" name="confirm" autocomplete="new-password" required><?php endif; ?>
    <?php else: ?>
      <?php if($token)echo '<p class="error">이전 링크가 만료되었거나 이미 사용되었습니다. 인증 메일을 다시 요청해 주세요.</p>'; ?>
      <label for="member-email">이메일 주소</label><input type="email" id="member-email" name="email" maxlength="254" autocomplete="email" value="<?php echo is_user_logged_in()?esc_attr($u->user_email):''; ?>" required <?php if(is_user_logged_in())echo 'readonly'; ?>>
      <p class="small">인증 링크는 30분 동안 유효합니다. 다시 요청하면 이전 링크는 사용할 수 없습니다.</p>
    <?php endif; ?>
    <details open><summary>개인정보 수집·이용 안내 (시험 운영)</summary><p>목적: 회원 식별, 이메일 인증, 로그인 및 신청 내역 연결. 항목: 이메일, 이름, 비밀번호 해시, 인증 시각. 미완료 인증 요청은 30분 후 만료되며 정기 정리 시 삭제됩니다. 회원 정보는 탈퇴 처리 시까지 보관하며 신청 기록의 보관은 별도 정책에 따릅니다. 동의를 거부할 수 있으나 회원가입과 회원 전용 서비스는 이용할 수 없습니다. 현재는 시험 운영으로 가상 정보만 사용해 주세요.</p></details>
    <label><input type="checkbox" name="agree" value="yes" required> 위 개인정보 수집·이용에 동의합니다.</label>
    <button type="submit"><?php echo $pending?'인증 및 가입 완료':'인증 메일 보내기'; ?></button></form>
    <?php elseif(!$done): ?><p class="notice">이 계정은 서비스를 이용할 수 있습니다. 새 회원가입은 로그아웃 후 진행해 주세요.</p><a class="button" href="<?php echo esc_url(home_url('/')); ?>">사이트로 돌아가기</a><?php endif; ?>
    <nav><a href="<?php echo esc_url(wp_login_url(hpcm_url())); ?>">로그인</a><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">비밀번호 찾기</a><a href="<?php echo esc_url(home_url('/')); ?>">메인페이지</a></nav><p class="small">메일함 인증은 실명 또는 기관 소속 확인을 대신하지 않습니다.</p></main></body></html><?php
}
