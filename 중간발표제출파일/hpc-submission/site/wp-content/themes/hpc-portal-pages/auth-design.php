<?php
if (!defined('ABSPATH')) exit;
// Presentation only: authentication, nonces and redirect targets remain WordPress-owned.
add_action('login_enqueue_scripts', function(){
    wp_enqueue_style('hpc-auth',get_template_directory_uri().'/auth.css',[],filemtime(__DIR__.'/auth.css'));
});
add_filter('login_headerurl',fn()=>home_url('/'));
add_filter('login_headertext',fn()=>'슈퍼컴퓨팅센터');
add_action('login_header',function(){
    echo '<header class="hpc-auth-header"><a href="'.esc_url(home_url('/')).'"><span class="hpc-auth-mark" aria-hidden="true">✦</span><span>슈퍼컴퓨팅센터<small>SUPERCOMPUTING CENTER</small></span></a><span class="hpc-auth-badge">시험 운영</span></header>';
    echo '<aside class="hpc-auth-story" aria-label="센터 이용 안내"><div class="hpc-auth-story-inner"><p class="hpc-auth-kicker">RESEARCH BEGINS HERE</p><h2>연구의 다음 단계,<br><em>함께 시작하세요.</em></h2><p class="hpc-auth-lead">이용 안내부터 신청과 진행 현황까지.<br>연구를 위한 준비를 한곳에서 이어갑니다.</p><div class="hpc-auth-grid" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div><ol class="hpc-auth-features"><li><b>01</b><span>준비하기<small>이용 절차와 교육 안내 확인</small></span></li><li><b>02</b><span>신청하기<small>신청서 작성과 문서 확인</small></span></li><li><b>03</b><span>이어가기<small>신청 현황과 이용자 지원</small></span></li></ol><p class="hpc-auth-test">현재 제작·시험 운영 중입니다.<br>실제 접수는 진행하지 않으며 가상 정보로 이용해 주세요.</p></div></aside>';
});
add_filter('login_message',function($message){
    $action=isset($_REQUEST['action'])&&is_scalar($_REQUEST['action'])?sanitize_key($_REQUEST['action']):'login';
    $titles=['lostpassword'=>['비밀번호를 잊으셨나요?','가입한 이메일로 비밀번호 재설정 안내를 받으세요.'],'retrievepassword'=>['비밀번호 찾기','이메일에서 안내를 확인해 주세요.'],'rp'=>['새 비밀번호 설정','다른 곳에서 사용하지 않는 비밀번호로 설정해 주세요.'],'resetpass'=>['새 비밀번호 설정','새 비밀번호를 입력하고 확인해 주세요.'],'logout'=>['로그아웃','계정의 로그아웃 여부를 확인해 주세요.'],'confirm_admin_email'=>['이메일 확인','계정 안내를 확인하고 계속해 주세요.']];
    [$title,$desc]=$titles[$action]??['다시 만나 반갑습니다.','이메일 주소 또는 사용자명으로 로그인해 주세요.'];
    return '<div class="hpc-auth-intro"><p class="hpc-auth-kicker">MEMBER ACCESS</p><h2>'.esc_html($title).'</h2><p>'.esc_html($desc).'</p></div>'.$message;
},20);
