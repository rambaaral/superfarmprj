<?php
if(!defined('ABSPATH'))exit;
add_action('add_meta_boxes',function(){
 add_meta_box('hpcc-event',hpct_text('t22e7d13aa17a08e6e304'),function($p){
 wp_nonce_field('hpcc_save','hpcc_nonce');$start=get_post_meta($p->ID,'_hpcc_start',true);$end=get_post_meta($p->ID,'_hpcc_end',true);
 echo (''.'<p>'.esc_html(hpct_text('t88720e14746664d63aee')).'</p>'.''.'<p>'.''.'<label>'.esc_html(hpct_text('teda1653e2ae3b1c510b4')).'<input type="date" name="hpcc_start" value="'.'').esc_attr($start).(''.'" required>'.''.'</label>'.''.'</p>'.''.'<p>'.''.'<label>'.esc_html(hpct_text('te6291ac05009de671531')).'<input type="date" name="hpcc_end" value="'.'').esc_attr($end).'" required></label></p>';
 },'hpc_event','normal','high');
 add_meta_box('hpcc-banner',hpct_text('teb003084a98175245d19'),function($p){
 wp_nonce_field('hpcc_save','hpcc_nonce');$selected=(int)get_post_meta($p->ID,'_hpcc_notice',true);
 echo (''.'<p>'.esc_html(hpct_text('t1aebd3dd86868ba9de03')).'</p>'.''.'<label>'.esc_html(hpct_text('t712ef9f41586ba0d962a')).'<select name="hpcc_notice" required>'.''.'<option value="">'.esc_html(hpct_text('t79f10eeeffa576c995c3')).'</option>'.'');
 foreach(get_posts(['post_type'=>'hpc_notice','post_status'=>'publish','numberposts'=>-1,'has_password'=>false]) as $n)echo '<option value="'.$n->ID.'" '.selected($selected,$n->ID,false).'>'.esc_html($n->post_title).'</option>';
 echo '</select></label>';
 },'hpc_banner','normal','high');
});
add_action('save_post',function($id,$p){
 if(!in_array($p->post_type,['hpc_event','hpc_banner'],true)||wp_is_post_revision($id)||wp_is_post_autosave($id)||!current_user_can('manage_options'))return;
 if(!isset($_POST['hpcc_nonce'])||!is_string($_POST['hpcc_nonce'])||!wp_verify_nonce(wp_unslash($_POST['hpcc_nonce']),'hpcc_save'))return;
 $error='';
 if($p->post_type==='hpc_event'){
 $a=isset($_POST['hpcc_start'])&&is_string($_POST['hpcc_start'])?sanitize_text_field(wp_unslash($_POST['hpcc_start'])):'';
 $b=isset($_POST['hpcc_end'])&&is_string($_POST['hpcc_end'])?sanitize_text_field(wp_unslash($_POST['hpcc_end'])):'';
 if(!hpcc_date($a)||!hpcc_date($b)||$b<$a)$error=hpct_text('tc3da6381201fb592e41d');
 else{update_post_meta($id,'_hpcc_start',$a);update_post_meta($id,'_hpcc_end',$b);}
 }else{
 $n=isset($_POST['hpcc_notice'])&&is_scalar($_POST['hpcc_notice'])?absint($_POST['hpcc_notice']):0;
 if(!hpcc_notice($n)||!get_post_thumbnail_id($id))$error=hpct_text('t095c20f622d225030bfa');
 else update_post_meta($id,'_hpcc_notice',$n);
 }
 if($error){
 set_transient('hpcc_error_'.get_current_user_id(),$error,120);
 if($p->post_status==='publish'){global $wpdb;$wpdb->update($wpdb->posts,['post_status'=>'draft'],['ID'=>$id]);clean_post_cache($id);}
 }
},20,2);
add_action('admin_notices',function(){if(!current_user_can('manage_options'))return;$k='hpcc_error_'.get_current_user_id();if($m=get_transient($k)){delete_transient($k);echo '<div class="notice notice-error"><p>'.esc_html($m).(esc_html(hpct_text('t6739e02ac5c3854ac190')).'</p>'.''.'</div>'.'');}});
foreach(['hpc_event','hpc_banner'] as $pt){
 add_filter('manage_'.$pt.'_posts_columns',function($c)use($pt){$c['hpcc_info']=$pt==='hpc_event'?hpct_text('tb918d5af77b8733a4892'):hpct_text('ta79c9517e708bf505438');return $c;});
 add_action('manage_'.$pt.'_posts_custom_column',function($c,$id)use($pt){if($c!=='hpcc_info')return;echo esc_html($pt==='hpc_event'?get_post_meta($id,'_hpcc_start',true).' ~ '.get_post_meta($id,'_hpcc_end',true):get_the_title((int)get_post_meta($id,'_hpcc_notice',true)));},10,2);
}
