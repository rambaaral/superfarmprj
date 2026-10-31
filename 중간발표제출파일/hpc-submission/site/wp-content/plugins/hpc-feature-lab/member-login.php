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
      <div class="hpc-member-welcome"><span class="eyebrow">MY PAGE</span><h2 id="member-login-title">로그인하고<br> 나의 이용 내역을 확인하세요.</h2><p>신청서 작성부터 진행 현황 확인까지,<br>마이페이지에서 이어갈 수 있습니다.</p><ul><li>이용신청 작성 및 수정</li><li>신청 진행 상황 확인</li><li>나의 문의와 답변 확인</li></ul><p class="hpc-member-trial">시험 운영 중입니다. 가상 정보로 이용해 주세요.</p></div>
      <div class="hpc-member-fields"><h3>회원 로그인</h3><p>가입한 이메일 주소 또는 사용자명을 입력해 주세요.</p>
      <?php if(isset($_GET['login_status'])&&$_GET['login_status']==='failed'): ?><p class="error" role="alert">로그인하지 못했습니다. 입력 내용을 확인해 주세요. 반복해서 실패하면 잠시 후 다시 시도하거나 비밀번호 찾기를 이용해 주세요.</p><?php endif; ?>
      <?php
      $extra=function($html,$args){return $html.wp_nonce_field('hpc_member_login','hpc_member_login_nonce',true,false);};
      add_filter('login_form_bottom',$extra,10,2);
      echo wp_login_form(['echo'=>false,'redirect'=>$target,'form_id'=>'hpc-member-login-form','label_username'=>'이메일 또는 사용자명','label_password'=>'비밀번호','label_remember'=>'로그인 상태 유지','label_log_in'=>'로그인','remember'=>true]);
      remove_filter('login_form_bottom',$extra,10);
      ?>
      <div class="hpc-member-links"><a href="<?php echo esc_url(hpcm_url()); ?>">회원가입 · 이메일 인증</a><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">비밀번호 찾기</a></div>
      </div>
    </section>
    <?php return ob_get_clean();
}
add_action('wp_enqueue_scripts',function(){
    if(function_exists('hpc_portal_page_key')&&hpc_portal_page_key()==='my-page'&&!is_user_logged_in())wp_enqueue_style('hpc-member-login',plugins_url('member-login.css',__FILE__),['hpc-naas-adapter'],filemtime(__DIR__.'/member-login.css'));
},30);
