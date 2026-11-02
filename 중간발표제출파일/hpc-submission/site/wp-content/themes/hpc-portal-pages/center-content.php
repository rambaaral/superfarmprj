<?php
if(!defined('ABSPATH'))exit;
add_shortcode('hpc_notices',function(){
 $id=isset($_GET['notice_id'])&&is_scalar($_GET['notice_id'])?absint($_GET['notice_id']):0;
 ob_start();
 if($id){$p=hpcc_notice($id);if(!$p){echo (''.'<p>'.esc_html(hpct_text('tea221db88c1b14cd9a88')).'</p>'.'');}else{
 echo '<article class="hpcc-notice"><h2>'.esc_html($p->post_title).'</h2><p class="hpcc-date">'.esc_html(get_the_date('Y.m.d',$p)).'</p><div class="hpcc-content">'.wp_kses_post(wpautop(do_blocks($p->post_content))).'</div></article>';
 }echo '<a class="button secondary" href="'.esc_url(hpcc_board_url()).(''.'">'.esc_html(hpct_text('t4fb41a1c3b2e14b1a6af')).'</a>'.'');
 }else{
 if(current_user_can('manage_options'))echo '<p><a class="button" href="'.esc_url(admin_url('post-new.php?post_type=hpc_notice')).(''.'">'.esc_html(hpct_text('te55a1c2949385a748d96')).'</a>'.''.'</p>'.'');
 $page=max(1,absint(is_scalar($_GET['notice_page']??null)?$_GET['notice_page']:1));
 $q=new WP_Query(['post_type'=>'hpc_notice','post_status'=>'publish','has_password'=>false,'posts_per_page'=>10,'paged'=>$page]);
 echo '<ul class="hpcc-notice-list">';
 foreach($q->posts as $p)echo '<li><a href="'.esc_url(hpcc_board_url($p->ID)).'"><span>'.esc_html($p->post_title).'</span><time>'.esc_html(get_the_date('Y.m.d',$p)).'</time></a></li>';
 if(!$q->posts)echo (''.'<li class="hpcc-empty">'.esc_html(hpct_text('t101b938ba4be48f06d82')).'</li>'.'');echo '</ul>';
 if($q->max_num_pages>1)echo (''.('<nav class="pagination" aria-label="'.esc_attr(hpct_text('t2fb959fa55000da49742')).'">').'').paginate_links(['base'=>add_query_arg('notice_page','%#%',hpcc_board_url()),'format'=>'','current'=>$page,'total'=>$q->max_num_pages]).'</nav>';
 }
 return ob_get_clean();
});
add_shortcode('hpc_home_updates',function(){
 ob_start();echo (''.'<section class="section hpcc-updates">'.''.'<div class="wrap hpcc-update-grid">'.''.'<section class="hpcc-news">'.''.'<div class="hpcc-heading">'.''.'<h2>'.esc_html(hpct_text('ta8f562bb9bb06d2a6f40')).'</h2>'.''.'<a href="'.'').esc_url(hpcc_board_url()).(''.'">'.esc_html(hpct_text('t11b0966bab17c05d8be2')).'</a>'.''.'</div>'.''.'<ul class="hpcc-notice-list">'.'');
 $posts=get_posts(['post_type'=>'hpc_notice','post_status'=>'publish','has_password'=>false,'numberposts'=>5]);
 foreach($posts as $p)echo '<li><a href="'.esc_url(hpcc_board_url($p->ID)).'"><span>'.esc_html($p->post_title).'</span><time>'.esc_html(get_the_date('Y.m.d',$p)).'</time></a></li>';
 if(!$posts)echo (''.'<li class="hpcc-empty">'.esc_html(hpct_text('t101b938ba4be48f06d82')).'</li>'.'');
 echo (''.'</ul>'.''.'</section>'.''.(''.('<section class="hpcc-banners" aria-label="'.esc_attr(hpct_text('tb08054c5db244acf63c4')).'">').'').''.'<div class="hpcc-heading">'.''.'<h2>'.esc_html(hpct_text('tb08054c5db244acf63c4')).'</h2>'.''.'</div>'.''.'<div class="hpcc-carousel" data-hpcc-carousel>'.'');
 echo '<a class="hpcc-slide hpcc-center-slide" href="'.esc_url(hp_url('center')).(''.'">'.''.'<span class="hpcc-center-art">'.''.'<span class="hpcc-center-kicker">'.'SUPERCOMPUTING CENTER'.'</span>'.''.'<strong>'.esc_html(hpct_text('tb771ce91ea1a18c82724')).'<br>'.esc_html(hpct_text('t26bd551efdba9b898efe')).'</strong>'.''.'<span class="hpcc-center-link">'.esc_html(hpct_text('tea19a2f1c5a862cfbdd5')).'</span>'.''.'<span class="hpcc-center-orbit" aria-hidden="true">'.''.'</span>'.''.'</span>'.''.'</a>'.'');
 $i=1;foreach(get_posts(['post_type'=>'hpc_banner','post_status'=>'publish','has_password'=>false,'numberposts'=>-1,'orderby'=>['menu_order'=>'ASC','date'=>'DESC']]) as $p){
 $n=hpcc_notice((int)get_post_meta($p->ID,'_hpcc_notice',true));if(!$n||!get_post_thumbnail_id($p->ID))continue;
 echo '<a class="hpcc-slide" '.($i?'hidden':'').' href="'.esc_url(hpcc_board_url($n->ID)).'">'.wp_get_attachment_image(get_post_thumbnail_id($p->ID),'large',false,['alt'=>$p->post_title?:$n->post_title,'loading'=>$i?'lazy':'eager']).'</a>';$i++;
 }
 echo (''.('<div class="hpcc-carousel-controls" role="group" aria-label="'.esc_attr(hpct_text('tb3df40be8f9990d19f28')).'">').''.('<button type="button" data-slide-prev aria-label="'.esc_attr(hpct_text('tc97add834ae81cde6177')).'" title="'.esc_attr(hpct_text('tc97add834ae81cde6177')).'">').''.'<span aria-hidden="true">'.'‹'.'</span>'.''.'</button>'.''.('<button type="button" data-slide-next aria-label="'.esc_attr(hpct_text('t3f0e0ebdd86cb30cffe0')).'" title="'.esc_attr(hpct_text('t3f0e0ebdd86cb30cffe0')).'">').''.'<span aria-hidden="true">'.'›'.'</span>'.''.'</button>'.''.('<button type="button" data-slide-pause aria-label="'.esc_attr(hpct_text('t3f410596d1f3774b83e0')).'" title="'.esc_attr(hpct_text('t3f410596d1f3774b83e0')).'" aria-pressed="false">').''.'<span aria-hidden="true">'.'Ⅱ'.'</span>'.''.'</button>'.''.'</div>'.''.'<span class="hpcc-slide-count" data-slide-count aria-label="전체 '.'').$i.'개 중 1번째 이미지">1/'.$i.'</span>';
 echo '</div></section></div></section>';return ob_get_clean();
});
add_shortcode('hpc_calendar',function(){
 ob_start(); ?>
 <section class="calendar-panel hpcc-calendar" aria-labelledby="calendar-heading"><div class="section-heading"><div><span class="eyebrow">SCHEDULE</span><h2 id="calendar-heading"><?php echo esc_html(hpct_text('td4fec2d03767dd6f308f')); ?></h2></div><?php if(current_user_can('manage_options')): ?><a href="<?php echo esc_url(admin_url('edit.php?post_type=hpc_event')); ?>"><?php echo esc_html(hpct_text('t5b62b21c4e5f0ac0c2d2')); ?></a><?php endif; ?></div>
 <div class="calendar-layout"><div data-center-calendar><div class="calendar-toolbar"><h3 data-calendar-title aria-live="polite"></h3><div><button type="button" data-month="-1" aria-label="<?php echo esc_attr(hpct_text('t77f9e60849376668ca6b')); ?>">‹</button><button type="button" data-month="today"><?php echo esc_html(hpct_text('t3bc6235e1d18162fb94c')); ?></button><button type="button" data-month="1" aria-label="<?php echo esc_attr(hpct_text('t4c33c6a0b753342a4b80')); ?>">›</button></div></div>
 <div class="hpcc-weekdays" aria-hidden="true"><?php foreach([hpct_text('tdfc76879e2f5c3ead091'),hpct_text('ted8a406d02b187b85782'),hpct_text('t950afe85bf406364ecdd'),hpct_text('tdcd19f5b5caac9771378'),hpct_text('t5fbc9add7bd4911bb918'),hpct_text('td6f030e0b2b54ac203dc'),hpct_text('t164f51aa4871af06c5c3')] as $day)echo '<span>'.$day.'</span>'; ?></div><div data-month-grid role="group" aria-label="<?php echo esc_attr(hpct_text('teba97bd622eb74e4577a')); ?>"></div><p class="calendar-help"><?php echo esc_html(hpct_text('tefd35b662ea55ae2a5dd')); ?></p><noscript><?php echo esc_html(hpct_text('tc15c6bf890594c22f950')); ?></noscript></div>
 <aside class="schedule-detail"><span class="eyebrow"><?php echo esc_html(hpct_text('tec56bf39ff25c5f1a442')); ?></span><h3 data-selected-date></h3><div data-event-list aria-live="polite"></div></aside></div>
 <script type="application/json" class="hpcc-events"><?php echo wp_json_encode(['today'=>current_datetime()->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m-d'),'events'=>hpcc_events(),'selected'=>isset($_GET['center_event'])&&is_scalar($_GET['center_event'])?hpcc_event_date(absint($_GET['center_event'])):''],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script></section>
 <?php return ob_get_clean();
});
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_style('hpcc-center',get_template_directory_uri().'/center-content.css',['hpc-naas-adapter'],filemtime(__DIR__.'/center-content.css'));
 wp_enqueue_script('hpcc-center',get_template_directory_uri().'/center-content.js',['hpc-portal'],filemtime(__DIR__.'/center-content.js'),true);
},30);
