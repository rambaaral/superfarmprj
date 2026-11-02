<?php
/**
 * Plugin Name: HPC Knowledge Chat
 * Description: 공개 자료와 열람 권한을 확인한 포럼 문단을 활용하는 센터 챗봇.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;
define('HKC_VERSION','1.0.1');
require_once __DIR__.'/search.php';
require_once __DIR__.'/api.php';
require_once __DIR__.'/admin.php';
function hkc_config(){return wp_parse_args(get_option('hkc_settings',[]),['email'=>'example@example.com','phone'=>'063-0000-0000','temporary'=>1,'daily_limit'=>100,'forum_enabled'=>1]);}
function hkc_links(){
 $cfg=hkc_config(); $map=get_option('hpc_portal_pages_v1',[]);
 $page=!empty($map['qna'])?get_post((int)$map['qna']):get_page_by_path('qna');
 $qna=$page?get_permalink($page):home_url('/qna/');
 return ['qna'=>$qna,'email'=>sanitize_email($cfg['email']),'phone'=>preg_replace('/[^0-9+\- ]/','',$cfg['phone']),'temporary'=>(bool)$cfg['temporary']];
}
function hkc_key(){
 if (defined('HPC_CHAT_API_KEY')) return HPC_CHAT_API_KEY;
 if (getenv('GEMINI_API_KEY')) return getenv('GEMINI_API_KEY');
 $file=dirname(rtrim(ABSPATH,'/')).'/.hpc-chat-key.php';
 return is_readable($file)?(string)include($file):'';
}
function hkc_forum_allowed(){return !empty(hkc_config()['forum_enabled']) && is_user_logged_in() && function_exists('hpcf_allowed') && hpcf_allowed(get_current_user_id());}
add_action('init',function(){add_shortcode('hkc_chat','hkc_render_chat');},99);
add_action('template_redirect',function(){
 if(is_singular() && has_shortcode((string)get_post_field('post_content',get_queried_object_id()),'hkc_chat')) {
  if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
  nocache_headers(); do_action('litespeed_control_set_nocache','Authenticated knowledge chat');
 }
});
function hkc_render_chat(){
 wp_enqueue_style('hkc',plugins_url('chat.css',__FILE__),[],HKC_VERSION);
 wp_enqueue_script('hkc',plugins_url('chat.js',__FILE__),[],HKC_VERSION,true);
 $links=hkc_links(); ob_start(); ?>
 <section class="hkc" data-api="<?php echo esc_url(rest_url('hpc-chat/v1/chat')); ?>" data-nonce="<?php echo is_user_logged_in()?esc_attr(wp_create_nonce('wp_rest')):''; ?>">
 <p class="hkc-eyebrow"><?php echo esc_html(hpct_text('t69e2c2d742d474889763')); ?></p><h2><?php echo esc_html(hpct_text('t9dcaff43b4c16d8cfc1a')); ?></h2>
 <p><?php echo esc_html(hpct_text('t13279f9b7cd7e7d87989')); ?></p>
 <p class="hkc-scope"><?php echo hkc_forum_allowed()?hpct_text('t44eb0c6a8955439ac38a'):hpct_text('tae0264a33e2b4f402863'); ?></p>
 <p class="hkc-note"><?php echo esc_html(hpct_text('t8d68c22168023cd76c8e')); ?></p>
 <div class="hkc-examples"><button type="button"><?php echo esc_html(hpct_text('t0f8dcde6debc328b7554')); ?></button><button type="button"><?php echo esc_html(hpct_text('t4a1eee1ae018064c17cf')); ?></button><button type="button"><?php echo esc_html(hpct_text('tc842e766c8469b92b476')); ?></button></div>
 <div class="hkc-log" role="log" aria-live="polite"></div>
 <form><label><?php echo esc_html(hpct_text('t32729cc3f7b6d44a6210')); ?><input name="question" maxlength="500" required autocomplete="off" placeholder="<?php echo esc_attr(hpct_text('t9854d8452d20f2d6035b')); ?>"></label><button type="submit"><?php echo esc_html(hpct_text('t6709f051130ef0048437')); ?></button></form>
 <p class="hkc-note"><?php echo esc_html(hpct_text('te72f50ebfee9dafc88fc')); ?></p>
 <div class="hkc-contact"><strong><?php echo esc_html(hpct_text('t1b314ace44e6c6d485f6')); ?></strong><a href="<?php echo esc_url($links['qna']); ?>"><?php echo esc_html(hpct_text('td43f0e91c65e26ebb432')); ?></a>
 <?php if($links['email']): ?><a href="mailto:<?php echo esc_attr($links['email']); ?>"><?php echo esc_html($links['email']); ?></a><?php endif; ?>
 <?php if($links['phone']): ?><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/','',$links['phone'])); ?>"><?php echo esc_html($links['phone']); ?></a><?php endif; ?>
 <?php if($links['temporary']): ?><small><?php echo esc_html(hpct_text('t202dd709e9faa168a59c')); ?></small><?php endif; ?></div></section>
 <?php return ob_get_clean();
}
