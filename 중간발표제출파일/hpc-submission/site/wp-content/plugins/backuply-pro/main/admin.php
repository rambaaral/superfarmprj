<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

add_action('admin_init', 'backuply_pro_admin_init');

function backuply_pro_admin_init(){
	add_action('admin_footer', 'backuply_pre_update_modal');
	add_action('admin_enqueue_scripts', 'backuply_pro_enqueue');
}

// Ajax handler for pre-update backup. Registered at file scope so it fires
// on admin-ajax.php (admin_init does NOT fire on admin-ajax.php).
add_action('wp_ajax_backuply_pre_update_backup', 'backuply_pre_update_backup');

// Enqueue pro admin assets. Only on update-core.php and only when the
// pre-update backup setting is enabled. Declares backuply-js as a
// dependency so the modal/progress globals exist before this script runs.
function backuply_pro_enqueue($hook){
	global $backuply;

	if($hook !== 'update-core.php' || empty($backuply['settings']['pre_update_backup'])){
		return;
	}

	wp_enqueue_script('backuply-pro-admin-js', BACKUPLY_PRO_DIR_URL . '/assets/js/admin.js', array('backuply-js', 'jquery-ui-dialog'), BACKUPLY_PRO_VERSION, true);
}

// Pre-Update Backup modal - Injected on update-core.php page
function backuply_pre_update_modal(){
	global $backuply;

	$screen = get_current_screen();

	if(!$screen || $screen->id !== 'update-core'){
		return;
	}

	if(empty($backuply['settings']) || empty($backuply['settings']['pre_update_backup'])){
		return;
	}

	backuply_modal_progress();
}

// Starts a backup before a WordPress core update. Both files and DB are
// backed up to the location configured in Backuply settings.
function backuply_pre_update_backup(){

	// Verify nonce + capability
	backuply_ajax_nonce_verify();

	// Refuse if a backup/restore is already in progress
	if(function_exists('backuply_active') && backuply_active()){
		wp_send_json(array(
			'success' => false,
			'message' => __('A backup is already running, please wait for it to finish or stop it before updating.', 'backuply-pro'),
		));
	}

	global $backuply;

	$backup_location = '';
	if(!empty($backuply['settings']['backup_location'])){
		$backup_location = $backuply['settings']['backup_location'];
	}

	// Build the backup options - back up both files and database
	$bak_options = array(
		'backup_dir'     => 1,
		'backup_db'      => 1,
		'backup_location'=> $backup_location,
		'auto_backup'    => false,
	);

	backuply_create_log_file();

	update_option('backuply_backup_stopped', false);
	$bak_options['last_update'] = time(); // Marks it active until the first loop takes over
	update_option('backuply_status', $bak_options);
	backuply_status_log('Initializing...', 'info', 13);
	backuply_status_log('Creating a job to start Backup', 'info', 17);

	if(backuply_backup_request()){
		wp_schedule_single_event(time() + BACKUPLY_TIMEOUT_TIME, 'backuply_timeout_check', array('is_restore' => false));
		wp_send_json(array('success' => true));
	}

	wp_send_json(array(
		'success' => false,
		'message' => __('Unable to start a backup at this moment, Please try again later', 'backuply-pro'),
	));
}