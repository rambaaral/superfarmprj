<?php
if(!defined('ABSPATH'))exit;
function hpc_member_login_url($redirect=''){
    $map=get_option('hpc_portal_pages_v1',[]);$url=!empty($map['my-page'])?get_permalink($map['my-page']):home_url('/my-page/');
    if($redirect)$url=add_query_arg('redirect_to',wp_validate_redirect($redirect,$url),$url);
    return $url;
}
add_filter('login_url',function($url,$redirect,$reauth){
    if($reauth&&is_user_logged_in())return $url;
    return hpc_member_login_url($redirect);
},20,3);
// Only ordinary, anonymous GET login views move into the portal.
// Password recovery, reauthentication, interim logins and all POSTs remain native.
add_action('login_init',function(){
    $action=isset($_REQUEST['action'])&&is_scalar($_REQUEST['action'])?(string)$_REQUEST['action']:'login';
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'||is_user_logged_in()||$action!=='login'||isset($_REQUEST['interim-login'])||isset($_REQUEST['checkemail']))return;
    $redirect=isset($_GET['redirect_to'])&&is_string($_GET['redirect_to'])?wp_unslash($_GET['redirect_to']):'';
    wp_safe_redirect(hpc_member_login_url($redirect));exit;
});
// Redirect only failures originating in the portal form, before response output.
function hpc_member_login_failed(){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||!isset($_POST['hpc_member_login_nonce'])||!is_string($_POST['hpc_member_login_nonce'])||!wp_verify_nonce(wp_unslash($_POST['hpc_member_login_nonce']),'hpc_member_login'))return;
    $redirect=isset($_POST['redirect_to'])&&is_string($_POST['redirect_to'])?wp_unslash($_POST['redirect_to']):'';
    wp_safe_redirect(add_query_arg('login_status','failed',hpc_member_login_url($redirect)));exit;
}
add_action('wp_login_failed','hpc_member_login_failed',999);
add_action('login_form_login',function(){
    if(empty($_POST['log'])||empty($_POST['pwd']))hpc_member_login_failed();
});
function hpc_member_login_panel(){
    $target=hpc_member_login_url();
    if(isset($_GET['redirect_to'])&&is_string($_GET['redirect_to']))$target=wp_validate_redirect(wp_unslash($_GET['redirect_to']),$target);
    ob_start(); ?>
    <section class="hpc-member-login" aria-labelledby="member-login-title">
      <div class="hpc-member-welcome"><span class="eyebrow">MY PAGE</span><h2 id="member-login-title"><?php echo esc_html(hpct_text('t9d97c7df471d37c6f7db')); ?><br><?php echo esc_html(hpct_text('te2cf12da3be45f840b4f')); ?></h2><p><?php echo esc_html(hpct_text('t84d1fb9abe7c1b9ee326')); ?><br><?php echo esc_html(hpct_text('t66c54b156abc4c52eb43')); ?></p><ul><li><?php echo esc_html(hpct_text('t24ad8b05ca7f809f8c22')); ?></li><li><?php echo esc_html(hpct_text('t92c1793ef24202bcde17')); ?></li><li><?php echo esc_html(hpct_text('t6825d0118c020c5a8f47')); ?></li></ul><p class="hpc-member-trial"><?php echo esc_html(hpct_text('tbe863d9e4f6da7db93f8')); ?></p></div>
      <div class="hpc-member-fields"><h3><?php echo esc_html(hpct_text('t46867a3aa4bc75865fa6')); ?></h3><p><?php echo esc_html(hpct_text('tb142ca23d0118aa5ed81')); ?></p>
      <?php if(isset($_GET['login_status'])&&$_GET['login_status']==='failed'): ?><p class="error" role="alert"><?php echo esc_html(hpct_text('tbd4e349dbfae7079e31e')); ?></p><?php endif; ?>
      <?php
      $extra=function($html,$args){return $html.wp_nonce_field('hpc_member_login','hpc_member_login_nonce',true,false);};
      add_filter('login_form_bottom',$extra,10,2);
      echo wp_login_form(['echo'=>false,'redirect'=>$target,'form_id'=>'hpc-member-login-form','label_username'=>hpct_text('t6987f469b69bd7d3c85e'),'label_password'=>hpct_text('t4fa210a06f9faa90aadc'),'label_remember'=>hpct_text('t0aa553ce491354f59e70'),'label_log_in'=>hpct_text('t6d253673db906e71d346'),'remember'=>true]);
      remove_filter('login_form_bottom',$extra,10);
      ?>
      <div class="hpc-member-links"><a href="<?php echo esc_url(hpcm_url()); ?>"><?php echo esc_html(hpct_text('te71b5804966df3065763')); ?></a><a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php echo esc_html(hpct_text('t97e57997eea649a1f4eb')); ?></a></div>
      </div>
    </section>
    <?php return ob_get_clean();
}
add_action('wp_enqueue_scripts',function(){
    if(function_exists('hpc_portal_page_key')&&hpc_portal_page_key()==='my-page'&&!is_user_logged_in())wp_enqueue_style('hpc-member-login',plugins_url('member-login.css',__FILE__),['hpc-naas-adapter'],filemtime(__DIR__.'/member-login.css'));
},30);
