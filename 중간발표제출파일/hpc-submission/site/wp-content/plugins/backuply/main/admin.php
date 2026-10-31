<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

add_action('admin_init', 'backuply_admin_init');
add_action('admin_menu', 'backuply_admin_menu');

function backuply_admin_init(){
	$showing_promo = false; // flag for single nag
	
	if(current_user_can('install_plugins')){
		add_filter('upload_mimes', 'backuply_add_mime_types'); // In case tar or gz are not supported
		add_action('admin_post_backuply_download_backup', 'backuply_direct_download_file');

		add_action('admin_enqueue_scripts', 'backuply_enqueue');

		// === Trial Promo ===
		$trial_time = get_option('backuply_hide_trial', 0);
		
		// It will show one day after install
		if(empty($trial_time)){
			$trial_time = time();
			update_option('backuply_hide_trial', $trial_time, false);
		}

		if($trial_time >= 0 && empty($backuply['bcloud_key']) && $trial_time < (time() - (86400)) && isset($_GET['page']) && $_GET['page'] === 'backuply'){
			$showing_promo = true;
			add_action('admin_notices', 'backuply_free_trial_promo');
		}
		// === Trial Promo Ends here === //
		
		// === Last Backup Notice Start === //
		// Backup notice for user to backup the if its been a week user took a backup
		$last_backup = get_option('backuply_last_backup');
		$backup_nag = get_option('backuply_backup_nag');
		
		// We want to show it one day after install.
		if(empty($backup_nag)){
			update_option('backuply_backup_nag', time() - 518400, false);
		}
		
		if((time() - $last_backup) >= 604800 && (time() - $backup_nag) >= 604800){
			add_action('admin_notices', 'backuply_backup_nag');
		}
		
		// === Last Backup Notice End === //
		
		// === License Notice Start === //
		// Are we to disable the license notice for 2 months.
		if(isset($_REQUEST['backuply_license_notice']) && (int)$_REQUEST['backuply_license_notice'] == 0 ){
			if(!wp_verify_nonce(backuply_optreq('security'), 'backuply_promo_nonce')) {
				die('Security Check Failed');
			}

			update_option('backuply_license_notice', (0 - time()), false);
			die('DONE');
		}
		
		$backuply_license_notice = get_option('backuply_license_notice', 0);
		
		if(empty($backuply_license_notice)){
			$backuply_license_notice = time();
			update_option('backuply_license_notice', $backuply_license_notice);
		}

		// Here we are making sure that we have license and Cloud trial has ended and if dismissed it does not shows for next 2 months.
		if(!empty($backuply['license']) && !empty($backuply['license']['expires']) && isset($_GET['page']) && strpos($_GET['page'], 'backuply') !== FALSE && get_option('bcloud_trial_time', 0) <= 0 && ($backuply_license_notice > 0 || (abs($backuply_license_notice) + MONTH_IN_SECONDS * 2) < time())){
			$current_timestamp = time();
			$expiration_timestamp = strtotime($backuply['license']['expires']);
			$timediff = $expiration_timestamp - $current_timestamp;

			if($timediff <= WEEK_IN_SECONDS){
				add_action('admin_notices', 'backuply_license_renew');
			}
		}
		
		// === License Notice End === //
		
		// === Plugin Update Notice === //
		$plugin_update_notice = get_option('softaculous_plugin_update_notice');
		$available_update_list = get_site_transient('update_plugins'); 

		if(
			!empty($available_update_list) &&
			!empty($available_update_list->response) &&
			!empty($available_update_list->response['backuply/backuply.php']) && 
			(empty($plugin_update_notice) || empty($plugin_update_notice['backuply/backuply.php']) || (!empty($plugin_update_notice['backuply/backuply.php']) &&
			version_compare($plugin_update_notice['backuply/backuply.php'], $available_update_list->response['backuply/backuply.php']->new_version, '<')))
		){
			add_action('admin_notices', 'backuply_update_notice_handler');
			add_filter('softaculous_plugin_update_notice', 'backuply_update_notice_filter');
		}
		// === Plugin Update Notice === //

		// Schedule for backup folder tmp cleanup.
		if(!wp_next_scheduled('backuply_clean_tmp')){
			wp_schedule_event(time(), 'backuply_daily', 'backuply_clean_tmp');
		}
	}

	// Are we pro ?
	if(!defined('BACKUPLY_PRO') && current_user_can('install_plugins')){

		// The holiday promo time
		$holiday_time = get_option('backuply_hide_holiday');
		if(empty($holiday_time) || (time() - abs($holiday_time)) > 172800){
			$holiday_time = time();
			update_option('backuply_hide_holiday', $holiday_time, false);
		}

		$time = date('nj');
		$days = array(1225, 1224, 11);
		if(!empty($holiday_time) && $holiday_time > 0 && isset($_GET['page']) && $_GET['page'] === 'backuply' && in_array($time, $days) && empty($showing_promo)){
			$showing_promo = true;
			add_action('admin_notices', 'backuply_holiday_promo');
		}
		
		// Are we to disable the holiday promo for 48 hours
		if(isset($_REQUEST['backuply_holiday_promo']) && (int)$_REQUEST['backuply_holiday_promo'] == 0 ){
			if(!wp_verify_nonce(backuply_optreq('security'), 'backuply_promo_nonce')) {
				die('Security Check Failed');
			}

			update_option('backuply_hide_holiday', (0 - time()), false);
			die('DONE');
		}

		// The promo time
		$promo_time = get_option('backuply_promo_time');
		if(empty($promo_time)){
			$promo_time = time();
			update_option('backuply_promo_time', $promo_time, false);
		}

		// Are we to show the backuply promo, and it will show up after 7 days of install.
		if(empty($showing_promo) && !empty($promo_time) && $promo_time > 0 && $promo_time < (time() - (7 * 86400))){
			$showing_promo = true;
			add_action('admin_notices', 'backuply_promo');
		}

		// Are we to disable the promo
		if(isset($_REQUEST['backuply_promo']) && (int)$_REQUEST['backuply_promo'] == 0 ){
			if(!wp_verify_nonce(backuply_optreq('security'), 'backuply_promo_nonce')) {
				die('Security Check Failed');
			}

			update_option('backuply_promo_time', (0 - time()), false);
			die('DONE');
		}
		
		// The offer time
		$offer_time = get_option('backuply_offer_time', '');
		if(empty($offer_time)){
			$offer_time = time();
			update_option('backuply_offer_time', $offer_time, false);
		}

		// Are we to show the backuply offer, and it will show up after 7 days of install.
		if(empty($showing_promo) && !empty($offer_time) && ($offer_time > 0  || abs($offer_time + time()) > 15780000) && $offer_time  < time() - (7 * 86400) && !empty($_GET['page']) && strpos(backuply_optget('page'), 'backuply') !== FALSE){
			add_action('admin_notices', 'backuply_offer_handler');
		}
		
		// Are we to disable the offer
		if(isset($_REQUEST['backuply_offer']) && (int)$_REQUEST['backuply_offer'] == 0 ){
			if(!wp_verify_nonce(backuply_optreq('security'), 'backuply_promo_nonce')) {
				die('Security Check Failed');
			}

			update_option('backuply_offer_time', (0 - time()), false);
			die('DONE');
		}
	}
	
	if(!isset($_GET['page']) || $_GET['page'] !== 'backuply'){
		return;
	}

	$litespeed_time = get_option('backuply_litespeed_notice', time());

	if(backuply_is_litespeed() && $litespeed_time <= time()){
		if(!file_exists(ABSPATH .'.htaccess') || !preg_match('/noabort/i', file_get_contents(ABSPATH .'.htaccess'))){
			add_action('admin_notices', 'backuply_litespeed_handler');
		}
	}
}

// Shows a nag to the user, 1 week after last backup
function backuply_backup_nag(){
	
	$last_backup = get_option('backuply_last_backup');

	echo 
	'<div class="notice notice-error is-dismissible backuply-backup-nag">';
	
	if(!empty($last_backup)){
		$time_diff = time() - $last_backup;
		$days = floor(abs($time_diff / 86400));
		
		echo '<p>'. sprintf(esc_html__( 'It\'s been %1$s days you took a backup, would you like to take a backup with Backuply and secure your website!', 'backuply' ), $days).'&nbsp; <a href="'.menu_page_url('backuply', false).'" class="button button-primary">Backup Now</a>'.(!defined('BACKUPLY_PRO') ? ' <span style="float:right;">For automatic backup schedules please  <a href="https://backuply.com/pricing" target="_blank" class="button" style="background-color:#64b450; border-color:#64b450; color:white;">Upgrade to Pro</a></span>' : '').'</p>';
	} else{
		echo '<p>'. esc_html__( 'You haven\'t taken a backup since you activated Backuply, Take a backup and secure your website!', 'backuply' ).'&nbsp; <a href="'.menu_page_url('backuply', false).'" class="button button-primary">Backup Now</a></p>';
	}

	echo '</div>';
	
	wp_register_script('backuply_time_nag', '', array('jquery'), '', true);
	wp_enqueue_script('backuply_time_nag');
	
	wp_add_inline_script('backuply_time_nag' ,'

		jQuery(document).ready(function(){
			jQuery(".backuply-backup-nag .notice-dismiss").on("click", function(){
			
				jQuery.ajax({
					method : "GET",
					url : "' . admin_url('admin-ajax.php') .'?action=backuply_hide_backup_nag&security=' . wp_create_nonce('backuply_nonce'). '",
					success : function(res){
						console.log(res);
					}
				});
			});
		});'
	);

}

// Shows the admin menu of Backuply
function backuply_admin_menu(){
	global $backuply;

	$capability = 'activate_plugins';
	
	// Add the menu page
	add_menu_page(__('Backuply Dashboard', 'backuply'), __('Backuply', 'backuply'), $capability, 'backuply', 'backuply_settings_page_handle', BACKUPLY_URL .'/assets/images/icon.svg');
	
	// Dashboard
	add_submenu_page('backuply', __('Backuply Dashboard', 'backuply'), __('Dashboard', 'backuply'), $capability, 'backuply', 'backuply_settings_page_handle');
	
	if(defined('BACKUPLY_PRO')){
		add_submenu_page('backuply', __('License', 'backuply'), __('License', 'backuply'), $capability, 'backuply-license', 'backuply_license_page_handle');
	} else {
		add_submenu_page('backuply', __('Backuply Cloud', 'backuply'), __('Backuply Cloud', 'backuply'), $capability, 'backuply-license', 'backuply_license_page_handle');
	}

	// Its Free
	if(!defined('BACKUPLY_PRO')){

		// Go Pro link
		add_submenu_page('backuply', __('Backuply Go Pro'), __('Go Pro'), $capability, BACKUPLY_PRO_URL);

	}
}

// Backuply - Backup Page
function backuply_settings_page_handle(){
	include_once BACKUPLY_DIR . '/main/settings.php';

	backuply_page_backup();
	backuply_page_theme();
}

// Backuply - License Page
function backuply_license_page_handle(){
	include_once BACKUPLY_DIR . '/main/license.php';
	backuply_license_page();
}

// Show the promo
function backuply_promo(){
	include_once(BACKUPLY_DIR.'/main/promo.php');
	
	backuply_base_promo();
}

function backuply_holiday_promo(){
	include_once(BACKUPLY_DIR.'/main/promo.php');
	
	backuply_holiday_offers();
}

function backuply_license_renew(){
	if(!function_exists('backuply_check_expires')){
		include_once BACKUPLY_DIR.'/main/promo.php';
	}
	
	backuply_check_expires();
}

function backuply_free_trial_promo(){
	if(!function_exists('backuply_free_trial')){
		include_once(BACKUPLY_DIR.'/main/promo.php');
	}
	
	backuply_promo_scripts();
	backuply_free_trial();
}

function backuply_offer_handler(){
	if(!function_exists('backuply_regular_offer')){
		include_once(BACKUPLY_DIR.'/main/promo.php');
	}
	
	backuply_regular_offer();
}

function backuply_litespeed_handler(){
	if(!function_exists('backuply_litespeed_notice')){
		include_once(BACKUPLY_DIR.'/main/promo.php');
	}
	
	backuply_litespeed_notice();
}

function backuply_update_notice_handler(){
	if(!function_exists('backuply_update_notice')){
		include_once(BACKUPLY_DIR.'/main/promo.php');
	}
	
	backuply_update_notice();
}

function backuply_update_notice_filter($plugins = []){
	$plugins['backuply/backuply.php'] = 'Backuply';
	
	return $plugins;
}

// Enqueue All Files
function backuply_enqueue($hook){
	global $backuply;

	if(($hook !== 'update-core.php' || empty($backuply['settings']['pre_update_backup'])) && strpos($hook, 'backuply') === false){
		return;
	}
	
	$localize = false;

	if($hook === 'update-core.php'){
		if(defined('BACKUPLY_PRO')){
			wp_enqueue_style('backuply-styles', BACKUPLY_URL . '/assets/css/styles.css', array(), BACKUPLY_VERSION);
			wp_enqueue_style('backuply-jquery-ui', BACKUPLY_URL . '/assets/css/base-jquery-ui.css', array(), BACKUPLY_VERSION);
			
			wp_enqueue_script('backuply-js', BACKUPLY_URL . '/assets/js/backuply.js', array('jquery-ui-dialog'), BACKUPLY_VERSION, true);
			
			$localize = true;
		}
	} else {
		wp_enqueue_style('backuply-styles', BACKUPLY_URL . '/assets/css/styles.css', array(), BACKUPLY_VERSION);
    
		// TODO:: Is only being used for a modal so create custom modal.
		wp_enqueue_style('backuply-jquery-ui', BACKUPLY_URL . '/assets/css/base-jquery-ui.css', array(), BACKUPLY_VERSION);
		wp_enqueue_style('backuply-jstree', BACKUPLY_URL . '/assets/css/jstree.css', array(), BACKUPLY_VERSION);
    
		wp_enqueue_script('backuply-js', BACKUPLY_URL . '/assets/js/backuply.js', array('jquery-ui-dialog'), BACKUPLY_VERSION, true);
		wp_enqueue_script('backuply-jstree', BACKUPLY_URL . '/assets/js/jstree.min.js', array('jquery'), BACKUPLY_VERSION, true);
		
		$localize = true;
	}

	if(!empty($localize)){
		$pre_update_enabled = !empty($backuply['settings']['pre_update_backup']) ? 1 : 0;

		wp_localize_script('backuply-js', 'backuply_obj', array(
			'nonce' => wp_create_nonce('backuply_nonce'),
			'ajax_url' => admin_url('admin-ajax.php'),
			'cron_task' => get_option('backuply_cron_settings'),
			'images' => BACKUPLY_URL . '/assets/images/',
			'backuply_url' => BACKUPLY_URL,
			'creating_session' => wp_generate_password(32, false),
			'status_key' => urlencode(backuply_get_status_key()),
			'site_url' => site_url(),
			'pre_update_enabled' => $pre_update_enabled,
		));
	}
}

function backuply_modal_progress($is_restoring = false){
	global $backuply;

	$backuply_remote_backup_locs = get_option('backuply_remote_backup_locs', array());

	?>
	<div class="backuply-modal" id="backuply-backup-progress" <?php echo $is_restoring ? '' : 'style="display:none;"';?> data-process="<?php echo $is_restoring ? 'restore' : 'backup';?>">
		<div class="backuply-modal__inner">
			<div class="backuply-modal__header">
				<div class="backuply-modal-header__title">
					<?php 
					$active_proto = 'local';
					$active_name = 'Local';

					if(isset($backuply['status']['backup_location']) && !empty($backuply_remote_backup_locs[$backuply['status']['backup_location']]['protocol'])){
						$active_proto = $backuply_remote_backup_locs[$backuply['status']['backup_location']]['protocol'];
						$active_name = $backuply_remote_backup_locs[$backuply['status']['backup_location']]['name'];
					}
					//If status doesn't have location, check settings (for pre-update backups)
					elseif(!empty($backuply['settings']['backup_location']) && !empty($backuply_remote_backup_locs[$backuply['settings']['backup_location']]['protocol'])){
						$active_proto = $backuply_remote_backup_locs[$backuply['settings']['backup_location']]['protocol'];
						$active_name = $backuply_remote_backup_locs[$backuply['settings']['backup_location']]['name'];
					}

					if($is_restoring){
						$restro_info = backuply_get_restoration_data();

						if(!empty($restro_info)){
							$active_proto = $restro_info['protocol'];
							$active_name = $restro_info['name'];
						}
					}

					echo '<img src="'.BACKUPLY_URL . '/assets/images/'.esc_attr($active_proto).'.svg'.'" height="26" width="26" title="'.esc_attr($active_name).'" class="backuply-modal-bak-icon"/>';

					?>
					<span class="backuply-title-text">
						<span class="backuply-title-backup"><?php esc_html_e('Backup in Progress', 'backuply'); ?></span>
						<span class="backuply-title-restore"><?php esc_html_e('Restore in progress', 'backuply'); ?></span>
					</span>
				</div>

				<div class="backuply-modal-header__actions">
					<?php echo !empty($is_restoring) ? '' : '<span class="dashicons dashicons-no"></span>'; ?>
				</div>
			</div>
			<div class="backuply-modal__content">
				<p class="backuply-loc-bak-name" align="center"><?php echo esc_html__('Backup Location', 'backuply').':'.esc_html($active_name); ?></p>
				<p class="backuply-loc-restore-name" style="display:none" align="center">Restoring From: <?php echo esc_attr($active_name); ?></p>
				<p class="backuply-progress-extra-backup" align="center"><?php esc_html_e('We are backing up your site it may take some time.', 'backuply'); ?></p>
				<p class="backuply-progress-extra-restore" align="center" style="display:none;"><?php esc_html_e('We are restoring your site it may take some time.', 'backuply'); ?></p>
				<div class="backuply-progress-bar" ><div class="backuply-progress-value" style="width:0%;"  data-done="0%"></div></div>
				<p id="backuply-rate-on-restore" align="center" style="display:none;"><a href="https://wordpress.org/support/plugin/backuply/reviews/?filter=5#new-post" target="_blank"><?php esc_html_e('Rate Us if you find Backuply useful', 'backuply'); ?></a></p>
				<div class="backuply-backup-status"></div>
			</div>
			<div class="backuply-modal_footer">
				<div>
					<button class="button-secondary" id="backuply-kill-process-btn"><?php esc_html_e('Kill Process', 'backuply'); ?></button>
				</div>
				<div>
					<button class="backuply-btn backuply-btn--danger backuply-stop-backup"><?php esc_html_e('Stop', 'backuply'); ?></button>
					<button class="backuply-btn backuply-btn--success backuply-disabled backuply-backup-finish" disabled><?php esc_html_e('Finish', 'backuply'); ?></button>
				</div>
			</div>
		</div>
	</div>
	<?php
}