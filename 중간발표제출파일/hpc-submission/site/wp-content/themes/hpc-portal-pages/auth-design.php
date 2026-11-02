<?php
if (!defined('ABSPATH')) exit;
// Presentation only: authentication, nonces and redirect targets remain WordPress-owned.
add_action('login_enqueue_scripts', function(){
    wp_enqueue_style('hpc-auth',get_template_directory_uri().'/auth.css',[],filemtime(__DIR__.'/auth.css'));
});
add_filter('login_headerurl',fn()=>home_url('/'));
add_filter('login_headertext',fn()=>hpct_text('t26bd551efdba9b898efe'));
add_action('login_header',function(){
    echo '<header class="hpc-auth-header"><a href="'.esc_url(home_url('/')).(''.'">'.''.'<span class="hpc-auth-mark" aria-hidden="true">'.'✦'.'</span>'.''.'<span>'.esc_html(hpct_text('t26bd551efdba9b898efe')).'<small>'.'SUPERCOMPUTING CENTER'.'</small>'.''.'</span>'.''.'</a>'.''.'<span class="hpc-auth-badge">'.esc_html(hpct_text('t1c914bacac237a0ea8d4')).'</span>'.''.'</header>'.'');
    echo (''.('<aside class="hpc-auth-story" aria-label="'.esc_attr(hpct_text('t69e2c2d742d474889763')).'">').''.'<div class="hpc-auth-story-inner">'.''.'<p class="hpc-auth-kicker">'.'RESEARCH BEGINS HERE'.'</p>'.''.'<h2>'.esc_html(hpct_text('td22a1ce1cb722417e505')).'<br>'.''.'<em>'.esc_html(hpct_text('t43ded6c3a81a32bee3e2')).'</em>'.''.'</h2>'.''.'<p class="hpc-auth-lead">'.esc_html(hpct_text('t253be3a10775106a33e6')).'<br>'.esc_html(hpct_text('tde20e63d0837943418a8')).'</p>'.''.'<div class="hpc-auth-grid" aria-hidden="true">'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'<span>'.''.'</span>'.''.'</div>'.''.'<ol class="hpc-auth-features">'.''.'<li>'.''.'<b>'.'01'.'</b>'.''.'<span>'.esc_html(hpct_text('t070cb31e2b43447d3f75')).'<small>'.esc_html(hpct_text('t6c2c7b2bbb8c756afdb1')).'</small>'.''.'</span>'.''.'</li>'.''.'<li>'.''.'<b>'.'02'.'</b>'.''.'<span>'.esc_html(hpct_text('te86b093fc5a11364757e')).'<small>'.esc_html(hpct_text('t3e990dcd1958ca0e8413')).'</small>'.''.'</span>'.''.'</li>'.''.'<li>'.''.'<b>'.'03'.'</b>'.''.'<span>'.esc_html(hpct_text('tb0bc198e3bd96f7d866a')).'<small>'.esc_html(hpct_text('t9e714d913cc696f2cbf9')).'</small>'.''.'</span>'.''.'</li>'.''.'</ol>'.''.'<p class="hpc-auth-test">'.esc_html(hpct_text('t9af7a0010487bd83a0b7')).'<br>'.esc_html(hpct_text('tae156c414b4cfbcee68a')).'</p>'.''.'</div>'.''.'</aside>'.'');
});
add_filter('login_message',function($message){
    $action=isset($_REQUEST['action'])&&is_scalar($_REQUEST['action'])?sanitize_key($_REQUEST['action']):'login';
    $titles=['lostpassword'=>[hpct_text('t93b93e29021432449923'),hpct_text('t5db43e7bc11df5f8205f')],'retrievepassword'=>[hpct_text('t97e57997eea649a1f4eb'),hpct_text('t8abc11975e126f18bf06')],'rp'=>[hpct_text('ta3d420e51a370d1ff9ac'),hpct_text('td73c0fe6e9139ca4fec2')],'resetpass'=>[hpct_text('ta3d420e51a370d1ff9ac'),hpct_text('tb95605093d32f40fa1c4')],'logout'=>[hpct_text('t919c1b32322583720f00'),hpct_text('te3e4183d54474262d384')],'confirm_admin_email'=>[hpct_text('t476d4d09d3455dfe0999'),hpct_text('t7e7390482ad9c7723b3d')]];
    [$title,$desc]=$titles[$action]??[hpct_text('t7d4c513bb4951bc5220f'),hpct_text('t400438629958c2b6e149')];
    return '<div class="hpc-auth-intro"><p class="hpc-auth-kicker">MEMBER ACCESS</p><h2>'.esc_html($title).'</h2><p>'.esc_html($desc).'</p></div>'.$message;
},20);
