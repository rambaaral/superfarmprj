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
function hpcm_require() { if (!hpcm_verified()) throw new RuntimeException(hpct_text('t1ea0b198ca196cf8dc74')); }
// Atomic per-window counters, including limits for requests involving nonexistent users.
function hpcm_limit($scope,$value,$max,$seconds) {
    global $wpdb; $now=time(); $bucket=hpcm_key($scope.'|'.$value.'|'.intdiv($now,$seconds)); $t=hpcm_table('limits');
    $ok=$wpdb->query($wpdb->prepare("INSERT INTO $t (bucket,hits,expires) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1",$bucket,$now+$seconds*2));
    return $ok!==false && (int)$wpdb->get_var($wpdb->prepare("SELECT hits FROM $t WHERE bucket=%s",$bucket)) <= $max;
}
function hpcm_request($email) {
    global $wpdb; $email=trim($email);
    if (strlen($email)>254 || !is_email($email)) throw new RuntimeException(hpct_text('tad44e6dcb53b34408098'));
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown'); // Never trust client-supplied forwarding headers.
    if (!hpcm_limit('ip',$ip,20,3600) || !hpcm_limit('site','all',200,3600)) throw new RuntimeException(hpct_text('t342642f837c870b31e6a'));
    $key=hpcm_email_key($email);
    if (!hpcm_limit('email-minute',$key,1,60)) throw new RuntimeException(hpct_text('ta0cbce64aaf26cc68f95'));
    if (!hpcm_limit('email-hour',$key,5,3600)) throw new RuntimeException(hpct_text('t56b887acc0fa4ec6fee3'));
    $u=get_user_by('email',$email);
    if (is_user_logged_in() && (!$u || $u->ID!==get_current_user_id())) throw new RuntimeException(hpct_text('tc63b0bee7efe6cb181e6'));
    if ($u && hpcm_verified($u->ID)) {
        // Explain existing accounts privately by email; never reset passwords or grant a session.
        $body=hpct_text('t6f75673d5925f2e4d6d7').wp_login_url(hpcm_url()).hpct_text('t87aaeb793362b382122b').wp_lostpassword_url().hpct_text('tb8eb639d526824b11a76');
        if (!wp_mail($email,hpct_text('t388bfcf50e627606e917'),$body)) {
            update_option('hpcm_mail_failure_at',gmdate('c'),false);
            throw new RuntimeException(hpct_text('t8754f4f134dfb4e46b12'));
        }
        return;
    }
    $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $now=time();
    $ok=$wpdb->query($wpdb->prepare('INSERT INTO '.hpcm_table().' (email,email_key,token_hash,user_id,expires,created) VALUES (%s,%s,%s,%d,%d,%d) ON DUPLICATE KEY UPDATE email=VALUES(email),token_hash=VALUES(token_hash),user_id=VALUES(user_id),expires=VALUES(expires),created=VALUES(created)',$email,$key,$hash,$u?$u->ID:0,$now+1800,$now));
    if ($ok===false) throw new RuntimeException(hpct_text('t89df1e1c4025dff4ba99'));
    $url=add_query_arg(['verify'=>$token,'key'=>$key],hpcm_url());
    $body=hpct_text('tdcd76b837c987ac94773').$url.hpct_text('t12e661b3d64c10541ac2');
    if (!wp_mail($email,hpct_text('t49709cbe71c46d917f62'),$body)) {
        $wpdb->query($wpdb->prepare('DELETE FROM '.hpcm_table().' WHERE email_key=%s AND token_hash=%s',$key,$hash));
        update_option('hpcm_mail_failure_at',gmdate('c'),false);
        throw new RuntimeException(hpct_text('t59318ad6e46daa7d7ed1'));
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
        if (!$r) throw new RuntimeException(hpct_text('tb05a74236c5b1f758fa8'));
        if ($r->user_id) {
            $u=get_userdata((int)$r->user_id);
            if (!$u || get_current_user_id()!==$u->ID || !hash_equals(hpcm_email_key($u->user_email),$key)) throw new RuntimeException(hpct_text('t5929908c9b919da3507f'));
            $uid=$u->ID;
        } else {
            if (is_user_logged_in() || email_exists($r->email)) throw new RuntimeException(hpct_text('t94daa11ce68d4b6f4303'));
            if (mb_strlen($name)<1 || mb_strlen($name)>60) throw new RuntimeException(hpct_text('t71e6e48976def9588137'));
            if (mb_strlen($password)<15 || mb_strlen($password)>128 || $password!==$confirm) throw new RuntimeException(hpct_text('t61852a68eb1cd640a2a4'));
            $uid=wp_insert_user(['user_login'=>'hpc_'.bin2hex(random_bytes(12)), 'user_email'=>$r->email,'display_name'=>$name,'nickname'=>$name,'user_pass'=>$password,'role'=>'subscriber']);
            if (is_wp_error($uid)) throw new RuntimeException(hpct_text('t2763c4a685875b120973'));
        }
        update_user_meta($uid,'hpcm_verified_email',$key);
        update_user_meta($uid,'hpcm_verified_at',gmdate('c'));
        update_user_meta($uid,'hpcm_notice_version','2026-10-20');
        $wpdb->delete(hpcm_table(),['id'=>$r->id],['%d']);
        if ($wpdb->query('COMMIT')===false) throw new RuntimeException(hpct_text('t5f310f33ab123b612a97'));
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
add_filter('login_message',function($message){return $message.'<p class="message"><a href="'.esc_url(hpcm_url()).(''.'">'.esc_html(hpct_text('t1d119aea3a42c14a0b4e')).'</a>'.''.'</p>'.'');});
// Existing users may log in to verify; no application or document access until verified.
add_action('template_redirect',function(){
    if (isset($_GET['hpc_member'])) { hpcm_page(); exit; }
    $key=function_exists('hpc_portal_page_key')?hpc_portal_page_key():'';
    if (!isset($_GET['hpc_lab']) && $key==='qna' && function_exists('hpc_portal_uses_kboard_qna') && hpc_portal_uses_kboard_qna()) return;
    if ((isset($_GET['hpc_lab']) || in_array($key,['application','application-status','my-page','qna'],true)) && is_user_logged_in() && !hpcm_verified()) { nocache_headers(); wp_safe_redirect(hpcm_url()); exit; }
},-20);
add_action('wp_ajax_hpc_live_preview',function(){ if (!hpcm_verified()) wp_send_json_error(['message'=>hpct_text('ta191fabf44db978bbbc9')],403); },-20);
add_filter('manage_users_columns',function($cols){$cols['hpcm_verified']=hpct_text('t2f905abd52ed4bc76902');return $cols;});
add_filter('manage_users_custom_column',function($value,$column,$uid){if($column!=='hpcm_verified')return $value; $u=get_userdata($uid);return user_can($u,'manage_options')?hpct_text('t949b96355d36c4543b6d'):(hpcm_verified($uid)?hpct_text('t17e5fdad93268d68c38b').esc_html(get_user_meta($uid,'hpcm_verified_at',true)):hpct_text('t3f38a45f6f1185b6aa39'));},10,3);
add_action('admin_notices',function(){if(current_user_can('manage_options') && get_option('hpcm_mail_failure_at')) echo (''.'<div class="notice notice-warning">'.''.'<p>'.esc_html(hpct_text('td6eae8f5039ad36c0061'))).esc_html(get_option('hpcm_mail_failure_at')).'</p></div>';});
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
            if (!isset($_COOKIE['hpcm_browser']) || !hash_equals(hpcm_csrf($browser),hpcm_input('csrf'))) throw new RuntimeException(hpct_text('t31a21d895652ea89deb4'));
            if (hpcm_input('op')==='request') {
                if (hpcm_input('agree')!=='yes') throw new RuntimeException(hpct_text('t3cc04880b0327c368f87'));
                hpcm_request(hpcm_input('email'));
                $notice=hpct_text('tad8e59f1e54f0fca8c8c');
            } elseif (hpcm_input('op')==='complete') {
                if (!hpcm_limit('complete',(string)($_SERVER['REMOTE_ADDR']??''),30,3600)) throw new RuntimeException(hpct_text('t342642f837c870b31e6a'));
                if(hpcm_input('agree')!=='yes') throw new RuntimeException(hpct_text('t3cc04880b0327c368f87'));
                hpcm_complete($key,$token,sanitize_text_field(hpcm_input('member_name')),hpcm_input('password'),hpcm_input('confirm'));
                hpcm_cookie('hpcm_link','',time()-3600);$pending=null;$done=true;
                $notice=hpct_text('te66fa2c73840bc9239dd');
            } else throw new RuntimeException(hpct_text('tba44d61a5dc6d955f774'));
        } catch(Throwable $e) { status_header(400); $error=$e->getMessage(); }
    }
    $csrf=hpcm_csrf($browser); $u=wp_get_current_user();
    ?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html(hpct_text('tb2a7f3e607a7b23a6397')); ?></title><style>
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

    </style></head><body><header><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(hpct_text('t26bd551efdba9b898efe')); ?></a></header><main><p class="steps"><?php echo esc_html(hpct_text('t05c4183580cc910b6954')); ?></p><h1><?php echo $done?hpct_text('t567a1e5b06b59f6a0589'):($pending?hpct_text('t76faa0354cb3c20ed426'):(is_user_logged_in()?hpct_text('t28f1db1622677f4087c1'):hpct_text('te668cc2bec841912922f'))); ?></h1>
    <?php if($error)echo '<p class="error" role="alert">'.esc_html($error).'</p>'; if($notice)echo '<p class="notice" role="status">'.esc_html($notice).'</p>'; ?>
    <?php if(!$done && !hpcm_verified()): ?>
    <form method="post" action="<?php echo esc_url(hpcm_url()); ?>"><input type="hidden" name="csrf" value="<?php echo esc_attr($csrf); ?>">
    <input type="hidden" name="op" value="<?php echo $pending?'complete':'request'; ?>">
    <?php if($pending): ?>
      <p><?php echo esc_html(hpct_text('t2d211899a64c685f5839')); ?></p>
      <?php if($pending->user_id): ?><p><?php echo esc_html(hpct_text('t5df918355143e9cef458')); ?></p>
      <?php else: ?><label for="member-name"><?php echo esc_html(hpct_text('t253d1510caf5362045ce')); ?></label><input id="member-name" name="member_name" maxlength="60" autocomplete="name" required>
      <label for="member-password"><?php echo esc_html(hpct_text('t4fa210a06f9faa90aadc')); ?></label><input id="member-password" type="password" name="password" minlength="15" maxlength="128" autocomplete="new-password" required><p class="small"><?php echo esc_html(hpct_text('t86a1ce7afbe7c6fbff0b')); ?></p>
      <label for="member-confirm"><?php echo esc_html(hpct_text('t61081c91f5168899c42a')); ?></label><input id="member-confirm" type="password" name="confirm" autocomplete="new-password" required><?php endif; ?>
    <?php else: ?>
      <?php if($token)echo (''.'<p class="error">'.esc_html(hpct_text('t314d7e8706d4c13b1e18')).'</p>'.''); ?>
      <label for="member-email"><?php echo esc_html(hpct_text('te9abda44c42a3a280e5e')); ?></label><input type="email" id="member-email" name="email" maxlength="254" autocomplete="email" value="<?php echo is_user_logged_in()?esc_attr($u->user_email):''; ?>" required <?php if(is_user_logged_in())echo 'readonly'; ?>>
      <p class="small"><?php echo esc_html(hpct_text('t82105c76baf2d0a5ad56')); ?></p>
    <?php endif; ?>
    <details open><summary><?php echo esc_html(hpct_text('t648278e2b154c1ebf1db')); ?></summary><p><?php echo esc_html(hpct_text('t393ecc3e85b4aedda8d9')); ?></p></details>
    <label><input type="checkbox" name="agree" value="yes" required><?php echo esc_html(hpct_text('t1c6e4a380fc220706d26')); ?></label>
    <button type="submit"><?php echo $pending?hpct_text('tfb957c50503ce3a27a0d'):hpct_text('t09695c61d177d44fe45d'); ?></button></form>
    <?php elseif(!$done): ?><p class="notice"><?php echo esc_html(hpct_text('t3e5f17bb0203b0604e3c')); ?></p><a class="button" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(hpct_text('t5f5cb379fde1cc7dd501')); ?></a><?php endif; ?>
    <nav><a href="<?php echo esc_url(wp_login_url(hpcm_url())); ?>"><?php echo esc_html(hpct_text('t6d253673db906e71d346')); ?></a><a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php echo esc_html(hpct_text('t97e57997eea649a1f4eb')); ?></a><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(hpct_text('ta8ce0724326b833ac770')); ?></a></nav><p class="small"><?php echo esc_html(hpct_text('tae0335242dabc460e2f7')); ?></p></main></body></html><?php
}
