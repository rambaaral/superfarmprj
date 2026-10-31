<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

function loginizer_pro_firewall(){
	global $lz_error;

	$country_blocking = get_option('lz_pro_country_block', []);

	// Saving Country Block settings
	if(isset($_POST['lz_pro_save_cb'])){
		// MaxMind requires the php 7.2 or greater
		if(version_compare(phpversion(), '7.2.0', '<')){
			$lz_error['version_mismatch'] = __('This feature requires the php version 7.2 or greater', 'loginizer');
			lz_report_error($lz_error);
			return loginizer_pro_firewall_main_T();
		}

		// Authorization
		if(!current_user_can('manage_options')){
			$lz_error['invalid_capability'] = __('Sorry, but you do not have permission to access this page.', 'loginizer');
			lz_report_error($lz_error);
			return loginizer_pro_firewall_main_T();
		}
		
		// Security Check
		if(!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'loginizer-pro-options')){
			$lz_error['invalid_nonce'] = __('Empty or invalid nonce field.', 'loginizer');
			lz_report_error($lz_error);
			return loginizer_pro_firewall_main_T();
		}
		
		$countries = isset($_POST['lz_pro_country_code']) ? map_deep(wp_unslash($_POST['lz_pro_country_code']), 'sanitize_text_field') : [];

		// Checking if we should download the db on this save
		$should_download_db = true;
		if(!empty($country_blocking['countries'])){
			$upload_dir = wp_get_upload_dir();
			$db_available = file_exists($upload_dir['basedir'] .'/loginizer-config/lz-db-country.mmdb');
			$countries_diff = array_diff($countries, $country_blocking['countries']);

			if(empty($countries_diff) && $db_available){
				$should_download_db = false;
			}
		}
		
		// Post options
		$country_blocking['enabled'] = !empty($_POST['lz_pro_enable_country_blocking']);
		$country_blocking['action'] = sanitize_text_field(wp_unslash($_POST['lz_pro_cb_action']));
		$country_blocking['countries'] = $countries;
		$country_blocking['logging_enabled'] = !empty($_POST['lz_pro_cb_enable_logging']);
	
		// Saving the settings
		update_option('lz_pro_country_block', $country_blocking);

		// Handling Firewall loader option
		if(!empty($_POST['firewall_loader'])){
			$firewall_loader = sanitize_text_field(wp_unslash($_POST['firewall_loader']));
			
			if($firewall_loader == 'mu'){
				loginizer_pro_cb_update_mu_plugin(true);
			}
			
			if($firewall_loader == 'plugin'){
				loginizer_pro_cb_update_mu_plugin(false);
			}
		}
		
		// Setting schedule to enable download of database
		if(!empty($country_blocking) && !empty($country_blocking['countries']) && !empty($country_blocking['enabled']) && $should_download_db){
			// wp cron to download the mmdb file
			if(wp_next_scheduled('loginizer_pro_cb_download_db')){
				wp_clear_scheduled_hook('loginizer_pro_cb_download_db');
			}

			wp_schedule_single_event(time(), 'loginizer_pro_cb_download_db');
		}
		
		// Updating the whitelist
		$firewall_settings = [];
		$firewall_settings['country_blocking'] = $country_blocking;
		$firewall_settings['whitelist'] = get_option('loginizer_whitelist', []);
		
		// Updating the IP method
		$firewall_settings['ip_method'] = get_option('loginizer_ip_method', 0);
		$firewall_settings['custom_ip_method'] = get_option('loginizer_custom_ip_method', '');

		// Updating the config file
		if(!loginizer_pro_cb_update_firewall_config($firewall_settings)){
			$lz_error['file_update'] = __('Error in updating config file.', 'loginizer');
			lz_report_error($lz_error);
			return loginizer_pro_firewall_main_T();
		}

		// Mark as saved
		$GLOBALS['lz_saved'] = true;
	}

	loginizer_pro_firewall_main_T();
}

function loginizer_pro_firewall_main_T(){

	loginizer_page_header('Country Block');

	// Saved ?
	if(!empty($GLOBALS['lz_saved'])){
		echo '<div id="message" class="updated"><p>'. (is_string($GLOBALS['lz_saved']) ? esc_html($GLOBALS['lz_saved']) : __('The settings were saved successfully', 'loginizer')). '</p></div><br />';
	}

	echo '<div class="tabs-wrapper" style="margin-bottom:10px; width:100%;">
		<nav class="nav-tab-wrapper">
			<a href="?page=loginizer_firewall&settings=country_blocking" class="nav-tab '.((!isset($_GET['settings']) || $_GET['settings'] == 'country_blocking') ? 'nav-tab-active' : '').'">'.(__('Country Block', 'loginizer')).'</a>
			<a href="?page=loginizer_firewall&settings=firewall_logs" class="nav-tab '.((isset($_GET['settings']) && $_GET['settings'] == 'firewall_logs') ? 'nav-tab-active' : '').'">'.(__('Logs', 'loginizer')).'</a>
		</nav>
	</div>';

	if(!empty($_GET['settings'])){
		if($_GET['settings'] == 'country_blocking'){
			loginizer_pro_country_blocking_T();
		}

		if($_GET['settings'] == 'firewall_logs'){
			loginizer_pro_cb_error_logs_T();
		}
	}else{
		loginizer_pro_country_blocking_T();
	}

	loginizer_page_footer();
}

function loginizer_pro_country_blocking_T(){

	$countries = loginizer_pro_cb_get_countries();
	$country_blocking = get_option('lz_pro_country_block', []);
	
	$db_download_timestamp = wp_next_scheduled('loginizer_pro_cb_download_db');
	$updated_at = get_option('loginizer_country_block_db_download', '');
	$upload_dir = wp_get_upload_dir();
	$db_available = file_exists($upload_dir['basedir'] .'/loginizer-config/lz-db-country.mmdb');
	$last_download_error = get_transient('loginizer_cdb_error_log');
	$is_downloading = get_transient('loginizer_cdb_is_downloading');

	$blocked_country = !empty($country_blocking['countries']) ? $country_blocking['countries'] : [];
	
	// Function to detect auto prepend file to update popup for including and overriding the file in Loginizer Firewall
	$auto_prepend_file = ini_get('auto_prepend_file');
	// Check if the file is loginizer_firewall.php
	if(stripos(trim($auto_prepend_file), 'loginizer_firewall.php') !== false){
		$auto_prepend_file = '';
	}

	// Check if the our loginizer_firewall.php is aleady auto prepended
	$running_through_prepend = loginizer_pro_cb_through_auto_prepend();
	$server_type = loginizer_pro_cb_server_type();
	
	$current_firewall_loader = defined('LOGINIZER_FIREWALL') ? LOGINIZER_FIREWALL : 'plugin';

	// Get the config file for the download button text
	if(defined('SITEPAD')){
		global $sitepad;

		$htaccess_file = $sitepad['path'] . '/.htaccess';
		$user_ini_file = $sitepad['path'] . '/.user.ini';
	}else{
		$htaccess_file = ABSPATH . '.htaccess';
		$user_ini_file = ABSPATH . '.user.ini';
	}

	$backup_config_files = [];
	if(file_exists($user_ini_file)){
		$backup_config_files[] = '.user.ini';
	}

	if(file_exists($htaccess_file)){
		$backup_config_files[] = '.htaccess';
	}

	echo '<div id="" class="postbox">
		<div class="postbox-header">
			<h2 class="hndle ui-sortable-handle">
				<span>'. esc_html__('Block/Allow country', 'loginizer').'</span>
			</h2>
		</div>
		<div class="inside">
			<div class="lz-country-db-status-wrapper">
					<div class="lz-country-db-status-icon-wrap">
						<span class="dashicons dashicons-database"></span>
					</div>
					<div class="lz-country-db-status-main">
						<div class="lz-country-db-status-main-content">
							<div>
								<h3>'.esc_html__('Country Blocking DB', 'loginizer').'</h3>
								<p>';
							if(!empty($db_available)){
								echo '<span class="lz-country-block-db-state" style="background-color:#22c55e;"></span>'.esc_html__('Database available', 'loginizer').'&nbsp;<a id="lz-update-cb-db-link" href="#">Update Database</a><span class="spinner"></span>';
							}else{
								echo '<span class="lz-country-block-db-state" style="background-color:#ef4444;"></span>'.esc_html__('No Database available', 'loginizer'). '&nbsp;<a id="lz-update-cb-db-link" href="#">Download Database</a><span class="spinner"></span>';
							}

						echo '</div>
						<div class="lz-country-db-status-last-update">';
							if(!empty($db_available) && !empty($updated_at)){
								echo '<p class="description">'.esc_html__('DB last updated at ', 'loginizer').wp_date("j M Y, h:i A", $updated_at).'</p>';
							}
						echo '</div>
						</div>';
						if(!empty($db_download_timestamp)){
							echo '<p class="lz-country-block-download-notice"><span class="dashicons dashicons-update"></span><strong> '.esc_html__('Database is being downloaded', 'loginizer').'</strong><br/>
							<span class="description">'.esc_html__('We\'ve queued the country database download in the background. Refresh the page to view the latest info.', 'loginizer').'</span></p>';
						} elseif(!empty($last_download_error)){
							echo '<div class="lz-country-block-download-notice"><span style="color:#ef4444;" class="dashicons dashicons-warning"></span><strong> '.esc_html__('Error Downloading Country DB', 'loginizer').'</strong><br/>
							<span class="description">'.esc_html($last_download_error).'</span>&nbsp;
							<div style="display:inline-block;"><button class="lz-country-db-download button">'.__('Try Downloading', 'loginizer').'</button><span class="spinner"></span></div>
							</div>';
						} elseif(!empty($is_downloading)){
							echo '<p class="lz-country-block-download-notice"><span class="dashicons dashicons-download"></span><strong> '.esc_html__('Database is being downloaded', 'loginizer').'</strong><br/>
							<span class="description">'.esc_html__('Database download has started. Refresh the page to see the latest info.', 'loginizer').'</span></p>';
						}

				echo '</div>
			</div>
			
			<form action="" method="post">';
				wp_nonce_field('loginizer-pro-options');
				echo '<table class="form-table">
					<tr>
						<th scope="row" valign="top">
							<label for="lz_pro_enable_country_blocking">'.esc_html__('Enable Country Blocking', 'loginizer').'</label><br />
						</th>
						<td>
							<input type="checkbox" name="lz_pro_enable_country_blocking" id="lz_pro_enable_country_blocking" '. (!empty($country_blocking['enabled']) ? 'checked' : '') .' value="1"/>
							<p class="description">'.esc_html__('Click to enable this feature', 'loginizer').'</p>
							<br />
						</td>
					</tr>
					<tr>
						<th scope="row" valign="top">
							<label>'.esc_html__('Firewall Loader', 'loginizer').'</label><br /><br />
						</th>
						<td>
							'.(defined('LOGINIZER_FIREWALL') ? '<div>'.__('Currently Loading Through', 'loginizer').' <strong>'.esc_html($current_firewall_loader).'</strong></div>': '').'
							<select name="firewall_loader" id="lz-cb-firewall-loader">
								<option value="plugin" '.selected(strtolower($current_firewall_loader), 'plugin', false).'>Plugin</option>
								<option value="mu" '.selected(strtolower($current_firewall_loader), 'mu', false).'>MU Plugin</option>
								<option value="server" '.selected(strtolower($current_firewall_loader), 'server', false).'>Server(.htaccess/.user.ini file)</option>
							</select>
						'.(!$running_through_prepend ? '<button class="button button-primary lz-enable-server-loader" id="loginizer_htaccess_popup" style="display:none;">'.__('Enable Server Loader', 'loginizer').'</button>' : '<button class="button" id="loginizer_htaccess_popup">'.esc_attr__('Disable Server Loader', 'loginizer').'</button>').'
					</tr>
					<tr>
						<th scope="row" valign="top">
							<label>'.esc_html__('Whitelist', 'loginizer').'</label><br /><br />
						</th>
						<td>
						<a href="'.esc_url(admin_url('admin.php?page=loginizer_brute_force#lz-whitelist-ip')).'" class="button button-primary" >'.__('Whitelist IP', 'loginizer').'</a>
						<p class="description">'.__('Country block uses same Whitelist as Bruteforce', 'loginizer').'</p>
					</tr>
					<tr>
						<th scope="row" valign="top">
							<label for="lz_pro_enable_country_blocking">'.esc_html__('Logging', 'loginizer').'</label><br />
						</th>
						<td>
							<input type="checkbox" name="lz_pro_cb_enable_logging" id="lz_pro_cb_enable_logging" '. (!empty($country_blocking['logging_enabled']) ? 'checked' : '') .' value="1"/>
							<p class="description">'.esc_html__('Log blocked requests', 'loginizer').'</p>
							<br />
						</td>
					</tr>
					<tr>
						<th scope="row" value="top">
							<label>'.esc_html__('Select Action', 'loginizer').'</label>
						</th>
						<td>
							<select name="lz_pro_cb_action" id="lz_pro_cb_action">
								<option value="block_selected" '.(!empty($country_blocking['action']) && ($country_blocking['action'] == 'block_selected') ? 'selected' :'').'>'. esc_html__('Block selected countries', 'loginizer').'</option>
								<option value="allow_selected" '.(!empty($country_blocking['action']) && ($country_blocking['action'] == 'allow_selected') ? 'selected' :'').'>'.esc_html__('Allow selected countries', 'loginizer').'</option>
							</select>
							<p class="description">'.esc_html__('Choose wheather to whitelist or blacklist the selected region', 'loginizer').'</p>
						</td>
					</tr>
					<tr>
						<th scope="row" value="top">
							<label>'.esc_html__('Select countries', 'loginizer').'</label>
						</th>
						<td>
							<input type="text"id="lz_pro_country_search_chars" style="width:80%;" placeholder="'.esc_attr__('Search Countries...', 'loginizer').'">
							<ul id="lz_pro_country_container">';
								foreach($countries as $iso_code => $name){
								echo '<li>
										<label>
											<input type="checkbox" class="lz_pro_select_country" id="lz_pro_cb_code_'.esc_attr(strtoupper($iso_code)).'" name="lz_pro_country_code[]" value="'.esc_attr($iso_code).'" '.((!empty($blocked_country) && in_array($iso_code, $blocked_country) ? 'checked' : '' )).'>
											'.esc_html($name).'
										</label>
									</li>';
								}
								
								echo '<li style="display:none;" id="lz_pro_country_not_found"><p>'. esc_html__(' No Results found !', 'loginizer').'</p></li>
							</ul>
						</td>
					</tr>
					<tr>
						<th scope="row" value="top">
							<label>'.esc_html__('Selected Countries', 'loginizer').'</label>
						</th>
						<td class="lz_pro_blocked_country_container">';
						foreach($countries as $iso_code => $name){
							if(in_array($iso_code, $blocked_country)){
								echo '<div>
									<span>'.esc_html($name).'</span>
									<span class="dashicons dashicons-no-alt lz_pro_remove_country" id="'.esc_attr($iso_code).'"></span>
								</div>';
							}
						}
						echo '</td>
					</tr>
				</table>
				<div class="lz_pro_firewall_save_btn"><input name="lz_pro_save_cb" class="button button-primary action" value="'.esc_attr__('Save Settings', 'loginizer').'" type="submit" /></div>
			</form>
		</div>
	</div>
	<div class="loginizer-htaccess-popup-modal" style="display:none;">
		<form action="" method="post" class="loginizer-htaccess-popup-box">
			<div class="loginizer-htaccess-popup-header">
				<div class="loginizer-popup-heading">'.esc_html__('Setup Firewall Configuration').'</div>
				<span class="dashicons dashicons-no" id="loginizer_htaccess_popup_close"></span>
			</div>

			<div class="loginizer-htaccess-popup-error" style="display:none;"></div>
				<p class="loginizer-htaccess-popup-info">
				'.esc_html__('We are using PHP directive called', 'loginizer').'
				<strong> "auto_prepend_file", </strong>
				'.esc_html__('which executes a file before any PHP script or code executes on the site.', 'loginizer').'
				</p>';

				// The option to overwrite already added prepended file, should only be visible 
				// when enabling server loader, when disabling we wont need it
				if(!empty($auto_prepend_file) && empty($running_through_prepend)){
					echo '<div>
						<span><strong>'.esc_html__('Current file which is included using this setting is shown below', 'loginizer').'</strong></span>
						<div class="loginizer-file-path">'.esc_html($auto_prepend_file).'</div>
					</div>
					<p>'.esc_html__('Choose "Override existing file" if you do not recognize the
						file or if you want to override this file. Select "Include file" if you
						want to include the existing file in loginizer_firewall.php.', 'loginizer').'
					</p>
					<select class="loginizer-htaccess-popup-select" id="loginizer_auto_prepended_file_action">
						<option value="include">'.esc_html__('Include file in Loginizer firewall', 'loginizer').'</option>
						<option value="override">'.esc_html__('Override existing file', 'loginizer').'</option>
					</select>';
				}
			
			echo '<p><strong>'.esc_html__('Please download the backup of the file below before proceeding.', 'loginizer').'</strong></p>
			<div class="loginizer-htaccess-popup-footer">';

			if(!empty($backup_config_files) && is_array($backup_config_files)){
				foreach($backup_config_files as $backup_file){
					echo '<a href="'.wp_nonce_url(admin_url('admin-post.php?action=loginizer_download_htaccess_backup&backup_file='.($backup_file).''), 'loginizer_htaccess_backup').'" download>
						<input type="button" class="button lz-download-backup-btn" value="'.esc_attr(sprintf(__('Download %s backup', 'loginizer'), $backup_file)).'">
					</a>';
				}
			}else{
				echo '<p>'.esc_html__('No files to download for the backup! You can update the setup', 'loginizer').'</p>';
			}

			echo '</div>';

			// Notifying users if the server loader will change take Time
			// As for fast-cgi and cgi process we use .user.ini
			// by default .user.ini ttl is 300s(5 minutes) so any change takes 5 minute to reflect
			if($server_type == 'fast-cgi' || $server_type == 'cgi'){
				echo '<div class="notice notice-warning">'.__('It could take upto 5 minutes for the settings to reflect', 'loginizer').'</div>';
			}
			echo '<div class="loginizer_htaccess_popup_submit_button">
				<input type="button" id="lz_pro_configure_htaccess" class="button button-primary" data-task='.esc_attr($running_through_prepend ? 'remove_script' : 'add_script').' value="'.esc_attr__('Update Configuration', 'loginizer').'" disabled>
			</div>
		</form>
	</div>';
}

function loginizer_pro_cb_error_logs_T(){
	$error_logs = loginizer_pro_cb_get_error_logs();
	$countries = loginizer_pro_cb_get_countries();
	
	echo '<div class="postbox">
		<div class="postbox-header">
			<h2 class="hndle ui-sortable-handle">
				<span>'.esc_html__('Error Logs', 'loginizer').'</span>
			</h2>
		</div>
		<div class="inside">
			<table class="wp-list-table widefat fixed users lz-cb-error-table">
				<tbody>
					<tr>
						<th scope="row" valign="top" style="width:20%">'.esc_html__('Time', 'loginizer').'</th>
						<th scope="row" valign="top" style="width:10%">'.esc_html__('Location', 'loginizer').'</th>
						<th scope="row" valign="top" style="width:10%">'.esc_html__('IP', 'loginizer').'</th>
						<th scope="row" valign="top" style="width:10%">'.esc_html__('Method', 'loginizer').'</th>
						<th scope="row" valign="top" style="width:30%">'.esc_html__('URI', 'loginizer').'</th>
					</tr>';
				
				if(!empty($error_logs)){
					foreach($error_logs as $log){
						$country_name = '';
						if(!empty($log['location']) && $countries[$log['location']]){
							$country_name = $countries[$log['location']];
						}

						echo '<tr>
						<td>'.(isset($log['time']) ? esc_html(wp_date('F jS, Y h:i A', (int)$log['time'])) : '-').'</td>
						<td>'.(!empty($log['location']) ? '<span class="lzflag ff-sm lzflag-'.(esc_attr($log['location'])).'"></span>&nbsp;' : '').(!empty($country_name) ? esc_html($country_name) : esc_html__('Unknown', 'loginizer')).'</td>
						<td><a href="'.esc_url('https://ipinfo.io/'.$log['ip']).'" target="_blank">'.(isset($log['ip']) ? esc_html($log['ip']) : '-').'<span class="dashicons dashicons-external"></span></a></td>
						<td>'.(isset($log['method']) ? esc_html($log['method']) : '-').'</td>
						<td>'.(isset($log['uri']) ? esc_html($log['uri']) : '-').'</td>
						</tr>';
					}
				} else {
					echo '<tr><td colspan="6">'.esc_html__('No logs available. Logs will be visible here', 'loginizer').'</td></tr>';
				}
			echo '</tbody>
			</table>
			</div>';

		echo '</div>
	</div>';
}

function loginizer_pro_cb_get_error_logs(){

	$last_x_lines = [];
	$log_file_pattern = wp_upload_dir()['basedir'] . '/loginizer-config/firewall_logs_*.php';
	$glob = glob($log_file_pattern);
	
	if(!is_array($glob) || empty($glob[0])){
		return $last_x_lines;
	}

	$log_file = $glob[0];
	$logs = file($log_file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

	if(count($logs) > 100){
		$logs = array_reverse(array_slice($logs, -100));
	} else {
		$logs = array_reverse($logs);
	}

	// Parse logs
	foreach($logs as $log){
		preg_match_all('/(\w+)=("([^"]*)"|(\S+))/', $log, $matches);

		$current_line = [];
		$itr = count($matches[0]);
		$keys = $matches[1];
		$values = $matches[2];

		for($i=0; $i < $itr; $i++){
			$current_line[$keys[$i]] = trim($values[$i], '"');
		}

		$last_x_lines[] = $current_line;
	}
	
	$last_x_lines = array_filter($last_x_lines);

	return $last_x_lines;
}

/**
 * Update .user.ini for PHP-FPM/FastCGI support
 * This works on most modern hosting environments
 * 
 * @param bool $is_enabled Whether Firewall is enabled
 * @param string $firewall_path Path to the Firewall loader file
 * @return bool True on success, false on failure
 */
function loginizer_pro_cb_update_user_ini($is_enabled, $firewall_path){
	if(defined('SITEPAD')){
		global $sitepad;
		$user_ini = $sitepad['path'].'/.user.ini';
	} else {
		$user_ini = ABSPATH . '.user.ini';
	}

	$marker_start = '; BEGIN LOGINIZER FIREWALL';
	$marker_end = '; END LOGINIZER FIREWALL';

	// Read existing content if file exists
	$existing_content = '';
	if(file_exists($user_ini)){
		$existing_content = @file_get_contents($user_ini);
		if($existing_content === false){
			$existing_content = '';
		}
	}

	// Remove existing Loginizer section using regex
	$pattern = '/' . preg_quote($marker_start, '/') . '.*?' . preg_quote($marker_end, '/') . '\s*/s';
	$clean_content = preg_replace($pattern, '', $existing_content);
	$clean_content = trim($clean_content);
	
	// Build new content
	if(!empty($is_enabled)){
		$firewall_directive = $marker_start . "\n";
		$firewall_directive .= 'auto_prepend_file = "' . addslashes($firewall_path) . '"' . "\n";
		$firewall_directive .= $marker_end . "\n";
		$new_content = (!empty($clean_content) ? $clean_content . "\n\n" : '') . $firewall_directive;
	} else {
		$new_content = $clean_content;
	}
	
	// Write the file with exclusive lock
	$result = file_put_contents($user_ini, $new_content);
	
	return $result !== false;
}

/**
 * Update htaccess rules for mod_php
 * Only works with Apache mod_php, not PHP-FPM/FastCGI
 * 
 * @param bool $is_enabled Whether Firewall is enabled
 * @param string $firewall_path Path to the Firewall loader file
 * @return bool True on success, false on failure
 */
function loginizer_pro_cb_update_htaccess_rules($is_enabled, $firewall_path){
	if(defined('SITEPAD')){
		global $sitepad;
		$htaccess = $sitepad['path'] . '/.htaccess';
	}else{
		$htaccess = ABSPATH . '.htaccess';
	}

	// Create htaccess if it doesn't exist
	if(!file_exists($htaccess)){
		if(@touch($htaccess) === false){
			return false;
		}
	}
	
	// Check writability
	if(!is_writable($htaccess)){
		return false;
	}

	// Use WordPress function to safely insert/remove markers
	$misc_file_path = ABSPATH;
	if(defined('SITEPAD')){
		$misc_file_path .= 'site-admin/includes/misc.php';
	} else {
		$misc_file_path .= 'wp-admin/includes/misc.php';
	}

	if(!file_exists($misc_file_path)){
		return false;
	}

	include_once($misc_file_path);
	
	// Escape the path for htaccess
	$safe_path = addslashes($firewall_path);
	$filename = basename($firewall_path);

	$rules = [];
	
	// Remove rules if disabled
	if(empty($is_enabled)){
		return insert_with_markers($htaccess, 'LZ FIREWALL AUTO PREPEND', $rules);
	}
	
	$server_type = loginizer_pro_cb_server_type();
	
	if($server_type == 'litespeed'){
		$rules[] = '<IfModule LiteSpeed>';
		$rules[] = 'php_value auto_prepend_file "' . $safe_path . '"';
		$rules[] = '</IfModule>';
		$rules[] = '<IfModule lsapi_module>';
		$rules[] = 'php_value auto_prepend_file "' . $safe_path . '"';
		$rules[] = '</IfModule>';
	} else if($server_type == 'mod_php'){
		// Generic mod_php (covers most cases)
		$rules[] = '<IfModule mod_php.c>';
		$rules[] = 'php_value auto_prepend_file "' . $safe_path . '"';
		$rules[] = '</IfModule>';
		
		// Versioned mod_php modules for older Apache configurations
		foreach(['5', '7', '8'] as $ver) {
			$rules[] = '<IfModule mod_php' . $ver . '.c>';
			$rules[] = 'php_value auto_prepend_file "' . $safe_path . '"';
			$rules[] = '</IfModule>';
		}
	}

	// For FastCGI and CGI, we do NOT use .htaccess rules (php_value) as they typically cause 500 API errors.
	// These environments are handled by loginizer_pro_cb_update_user_ini() using .user.ini
	$escaped_filename = preg_quote($filename, '/');
	if($server_type == 'fast-cgi' || $server_type == 'cgi'){
		$file_match_pattern = '^('.$escaped_filename.'|\.user\.ini)$';
	} else {
		$file_match_pattern = '^('.$escaped_filename.')$';
	}

	// Deny direct access to firewall loader file
	$rules[] = '<FilesMatch "'.$file_match_pattern.'">';
	$rules[] = '<IfModule mod_authz_core.c>';
	$rules[] = 'Require all denied';
	$rules[] = '</IfModule>';
	$rules[] = '<IfModule !mod_authz_core.c>';
	$rules[] = 'Order deny,allow';
	$rules[] = 'Deny from all';
	$rules[] = '</IfModule>';
	$rules[] = '</FilesMatch>';

	return insert_with_markers($htaccess, 'LZ FIREWALL AUTO PREPEND', $rules);
}

// Update MU plugin for WordPress-level firewall loading
function loginizer_pro_cb_update_mu_plugin($is_enabled){
	
	if(!defined('WPMU_PLUGIN_DIR')){
		return false;
	}

	// Create MU plugins directory if needed
	if(!is_dir(WPMU_PLUGIN_DIR)){
		if(!wp_mkdir_p(WPMU_PLUGIN_DIR)){
			return false;
		}
	}
	
	$mu_file_path = WPMU_PLUGIN_DIR . '/loginizer_firewall.php';
	
	// If disabled delete the MU plugin file, it is not dependent on anything.
	if(empty($is_enabled)){
		if(file_exists($mu_file_path)){
			return unlink($mu_file_path);
		}

		return true;
	}

	$content = '<?php
if(!defined("ABSPATH")){
	die("HACKING ATTEMPT");
}

if(defined("LOGINIZER_FIREWALL")){
	return;
}

define("LOGINIZER_FIREWALL", "MU");

$lz_firewall_file = '.(defined('SITEPAD') ? 'SP_PLUGIN_DIR' : 'WP_PLUGIN_DIR').' . "/loginizer-security/main/waf/country-blocking.php";

if(file_exists($lz_firewall_file)){
	include_once($lz_firewall_file);
}';

	return file_put_contents($mu_file_path, $content);
}

// This function is used to setup auto_prepend_file
// Creates the loader file
function loginizer_pro_cb_setup_auto_prepend($is_enabled, $include_existing_file = false){
	global $lz_error;
	
	if(!is_array($lz_error)){
		$lz_error = [];
	}
	
	if(defined('SITEPAD')){
		global $sitepad;
		$firewall_path = $sitepad['path'] .'/loginizer_firewall.php';
	} else {
		$firewall_path = ABSPATH .'loginizer_firewall.php';
	}
	
	// Building paths for loader file
	$rel_base_path = str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path(LOGINIZER_PRO_DIR));
	$loginizer_firewall = wp_normalize_path($firewall_path);
	$target_firewall = LOGINIZER_PRO_DIR . 'main/waf/country-blocking.php';
	
	if(!empty($is_enabled)){
		$loader_added = loginizer_pro_cb_setup_loader($target_firewall, $loginizer_firewall, $include_existing_file);

		if(empty($loader_added)){
			return false;
		}
	}
	//return false;
	$server_type = loginizer_pro_cb_server_type();

	$success_count = 0;

	// Update .htaccess (works with mod_php/litespeed)
	if(loginizer_pro_cb_update_htaccess_rules($is_enabled, $loginizer_firewall)){
		$success_count++;
	} else {
		// Non-fatal for htaccess since .user.ini is primary
		if(defined('WP_DEBUG') && WP_DEBUG){
			error_log('Loginizer Firewall: Failed to update .htaccess');
		}
	}

	// Update .user.ini (works with PHP-FPM/FastCGI/CGI - most common)
	if($server_type == 'fast-cgi' || $server_type == 'cgi'){
		if(loginizer_pro_cb_update_user_ini($is_enabled, $loginizer_firewall)){
			$success_count++;
		} else {
			// Non-fatal, log but continue
			if(defined('WP_DEBUG') && WP_DEBUG){
				error_log('Loginizer Firewall: Failed to update .user.ini');
			}
		}
	}
	
	// In the case where .user.ini is used we can not directly delete the loader file
	// As it takes some time for user to use the changed .user.ini becuase server keeps
	// The file in its cache, which could take upto 5 minutes.
	if(empty($is_enabled)){
		$running_through_prepend = loginizer_pro_cb_through_auto_prepend();
		if(file_exists($loginizer_firewall)){
			// If Firewall is still being loaded through the Server, then we can not delete the loader file
			// In that case we will leave a comment for user to check if needed.
			if(!defined('LOGINIZER_FIREWALL') || (defined('LOGINIZER_FIREWALL') && LOGINIZER_FIREWALL != 'Server')){
				unlink($loginizer_firewall);
			} else {
				file_put_contents($loginizer_firewall, "<?php \n".'// Make sure all the reference to this file(loginizer_firewall.php) has been removed from .htaccess or .user.ini files, before deleting this file');
			}
		}
	}
	
	// Consider success if at least one method worked
	return $success_count > 0;
}

// Setting up loader file used for auto_prepend_file
function loginizer_pro_cb_setup_loader($target_firewall, $loginizer_firewall, $include_existing_file = false){

	// Include the file if the auto prepend exist and user choosed include
	$existing_auto_prepended_file = '';

	if(!empty($include_existing_file)){
		// Function to get the file path of the auto pepended file
		$file = ini_get('auto_prepend_file');
		$existing_auto_prepended_file = '$existing_file = "' . $file . '";';
	}

	$snippet = '<?php
define("LOGINIZER_FIREWALL", "Server");'. "\n";

	if(defined('SITEPAD')){
		global $sitepad;

		$snippet .= 'if(!defined("LZ_FIREWALL_SITEPAD_DOM_PATH")){
	define("LZ_FIREWALL_SITEPAD_DOM_PATH", "'.$sitepad['path'].'/");
}'. "\n";
	}

	// Include file which was already running through the htaccess or the user.ini
	if(!empty($include_existing_file)){
		$snippet .= $existing_auto_prepended_file.'
		if(file_exists($existing_file)){
			include_once($existing_file);
		}'. "\n";
	}

	$snippet .= '$lz_firewall_file = "' . wp_normalize_path($target_firewall) . '";

if(file_exists($lz_firewall_file)){
	include_once($lz_firewall_file);
}';

	$firewall_written = file_put_contents($loginizer_firewall, $snippet);
	if($firewall_written === false){
		$lz_error['firewall_file_write'] = __('Failed to write Firewall loader file. Check file permissions.', 'loginizer');
		return false;
	}
	
	// Safety Check: Ensure the file was actually created and is accessible
	if(!file_exists($loginizer_firewall)){
		$lz_error['firewall_file_missing'] = __('Firewall loader file could not be verified after writing.', 'loginizer');
		return false;
	}
	
	return true;
}


// Update Country blocking config
function loginizer_pro_cb_update_firewall_config($configs = []){

	$json = json_encode($configs);

	$uploads_dir_info = wp_upload_dir();
	$config_dir = $uploads_dir_info['basedir'] . '/loginizer-config';
	$config_file = $config_dir . '/firewall-config.php';

	if(!is_dir($config_dir)){
		wp_mkdir_p($config_dir);
	}
	
	loginizer_pro_cb_config_dir_protection($config_dir);

	$content = "<?php exit;\n" . $json;

	$result = @file_put_contents($config_file, $content, LOCK_EX);

	return $result !== false;
}


// Protect WAF config directory from direct access
// Creates .htaccess (Apache), web.config (IIS), and index files
function loginizer_pro_cb_config_dir_protection($dir){
	if(!file_exists($dir)){
		return false;
	}

	// Create index files to prevent directory listing
	@touch($dir . '/index.html');
	@touch($dir . '/index.php');

	if(!file_exists($dir . '/.htaccess')){
		@file_put_contents($dir . '/.htaccess', 'Deny from all');
	}

	// IIS web.config protection
	if(!file_exists($dir . '/web.config')){
		$webconfig = '<?xml version="1.0" encoding="UTF-8"?>
<configuration>
	<system.webServer>
		<authorization>
			<deny users="*" />
		</authorization>
	</system.webServer>
</configuration>';
		
		@file_put_contents($dir . '/web.config', $webconfig);
	}
	
	return true;
}

function loginizer_pro_cb_get_countries(){
	return [
		'AD' => __('Andorra', 'loginizer'),
		'AF' => __('Afghanistan', 'loginizer'),
		'AG' => __('Antigua and Barbuda', 'loginizer'),
		'AI' => __('Anguilla', 'loginizer'),
		'AL' => __('Albania', 'loginizer'),
		'AM' => __('Armenia', 'loginizer'),
		'AO' => __('Angola', 'loginizer'),
		'AQ' => __('Antarctica', 'loginizer'),
		'AR' => __('Argentina', 'loginizer'),
		'AS' => __('American Samoa', 'loginizer'),
		'AT' => __('Austria', 'loginizer'),
		'AU' => __('Australia', 'loginizer'),
		'AW' => __('Aruba', 'loginizer'),
		'AX' => __('Aland Islands', 'loginizer'),
		'AZ' => __('Azerbaijan', 'loginizer'),
		'BA' => __('Bosnia and Herzegovina', 'loginizer'),
		'BB' => __('Barbados', 'loginizer'),
		'BD' => __('Bangladesh', 'loginizer'),
		'BE' => __('Belgium', 'loginizer'),
		'BF' => __('Burkina Faso', 'loginizer'),
		'BG' => __('Bulgaria', 'loginizer'),
		'BH' => __('Bahrain', 'loginizer'),
		'BI' => __('Burundi', 'loginizer'),
		'BJ' => __('Benin', 'loginizer'),
		'BL' => __('Saint Bartelemey', 'loginizer'),
		'BM' => __('Bermuda', 'loginizer'),
		'BN' => __('Brunei', 'loginizer'),
		'BO' => __('Bolivia', 'loginizer'),
		'BQ' => __('Bonaire, Saint Eustatius and Saba', 'loginizer'),
		'BR' => __('Brazil', 'loginizer'),
		'BS' => __('Bahamas', 'loginizer'),
		'BT' => __('Bhutan', 'loginizer'),
		'BV' => __('Bouvet Island', 'loginizer'),
		'BW' => __('Botswana', 'loginizer'),
		'BY' => __('Belarus', 'loginizer'),
		'BZ' => __('Belize', 'loginizer'),
		'CA' => __('Canada', 'loginizer'),
		'CC' => __('Cocos Islands', 'loginizer'),
		'CD' => __('Democratic Republic of the Congo', 'loginizer'),
		'CF' => __('Central African Republic', 'loginizer'),
		'CG' => __('Republic of the Congo', 'loginizer'),
		'CH' => __('Switzerland', 'loginizer'),
		'CI' => __('Ivory Coast', 'loginizer'),
		'CK' => __('Cook Islands', 'loginizer'),
		'CL' => __('Chile', 'loginizer'),
		'CM' => __('Cameroon', 'loginizer'),
		'CN' => __('China', 'loginizer'),
		'CO' => __('Colombia', 'loginizer'),
		'CR' => __('Costa Rica', 'loginizer'),
		'CU' => __('Cuba', 'loginizer'),
		'CV' => __('Cabo Verde', 'loginizer'),
		'CW' => __('Curacao', 'loginizer'),
		'CX' => __('Christmas Island', 'loginizer'),
		'CY' => __('Cyprus', 'loginizer'),
		'CZ' => __('Czechia', 'loginizer'),
		'DE' => __('Germany', 'loginizer'),
		'DJ' => __('Djibouti', 'loginizer'),
		'DK' => __('Denmark', 'loginizer'),
		'DM' => __('Dominica', 'loginizer'),
		'DO' => __('Dominican Republic', 'loginizer'),
		'DZ' => __('Algeria', 'loginizer'),
		'EC' => __('Ecuador', 'loginizer'),
		'EE' => __('Estonia', 'loginizer'),
		'EG' => __('Egypt', 'loginizer'),
		'EH' => __('Western Sahara', 'loginizer'),
		'ER' => __('Eritrea', 'loginizer'),
		'ES' => __('Spain', 'loginizer'),
		'ET' => __('Ethiopia', 'loginizer'),
		'FI' => __('Finland', 'loginizer'),
		'FJ' => __('Fiji', 'loginizer'),
		'FK' => __('Falkland Islands', 'loginizer'),
		'FM' => __('Micronesia', 'loginizer'),
		'FO' => __('Faroe Islands', 'loginizer'),
		'FR' => __('France', 'loginizer'),
		'GA' => __('Gabon', 'loginizer'),
		'GB' => __('United Kingdom', 'loginizer'),
		'GD' => __('Grenada', 'loginizer'),
		'GE' => __('Georgia', 'loginizer'),
		'GF' => __('French Guiana', 'loginizer'),
		'GG' => __('Guernsey', 'loginizer'),
		'GH' => __('Ghana', 'loginizer'),
		'GI' => __('Gibraltar', 'loginizer'),
		'GL' => __('Greenland', 'loginizer'),
		'GM' => __('Gambia', 'loginizer'),
		'GN' => __('Guinea', 'loginizer'),
		'GP' => __('Guadeloupe', 'loginizer'),
		'GQ' => __('Equatorial Guinea', 'loginizer'),
		'GR' => __('Greece', 'loginizer'),
		'GS' => __('South Georgia and the South Sandwich Islands', 'loginizer'),
		'GT' => __('Guatemala', 'loginizer'),
		'GU' => __('Guam', 'loginizer'),
		'GW' => __('Guinea Bissau', 'loginizer'),
		'GY' => __('Guyana', 'loginizer'),
		'HK' => __('Hong Kong', 'loginizer'),
		'HM' => __('Heard Island and McDonald Islands', 'loginizer'),
		'HN' => __('Honduras', 'loginizer'),
		'HR' => __('Croatia', 'loginizer'),
		'HT' => __('Haiti', 'loginizer'),
		'HU' => __('Hungary', 'loginizer'),
		'ID' => __('Indonesia', 'loginizer'),
		'IE' => __('Ireland', 'loginizer'),
		'IL' => __('Israel', 'loginizer'),
		'IM' => __('Isle of Man', 'loginizer'),
		'IN' => __('India', 'loginizer'),
		'IO' => __('British Indian Ocean Territory', 'loginizer'),
		'IQ' => __('Iraq', 'loginizer'),
		'IR' => __('Iran', 'loginizer'),
		'IS' => __('Iceland', 'loginizer'),
		'IT' => __('Italy', 'loginizer'),
		'JE' => __('Jersey', 'loginizer'),
		'JM' => __('Jamaica', 'loginizer'),
		'JO' => __('Jordan', 'loginizer'),
		'JP' => __('Japan', 'loginizer'),
		'KE' => __('Kenya', 'loginizer'),
		'KG' => __('Kyrgyzstan', 'loginizer'),
		'KH' => __('Cambodia', 'loginizer'),
		'KI' => __('Kiribati', 'loginizer'),
		'KM' => __('Comoros', 'loginizer'),
		'KN' => __('Saint Kitts and Nevis', 'loginizer'),
		'KP' => __('North Korea', 'loginizer'),
		'KR' => __('South Korea', 'loginizer'),
		'KW' => __('Kuwait', 'loginizer'),
		'KY' => __('Cayman Islands', 'loginizer'),
		'KZ' => __('Kazakhstan', 'loginizer'),
		'LA' => __('Laos', 'loginizer'),
		'LB' => __('Lebanon', 'loginizer'),
		'LC' => __('Saint Lucia', 'loginizer'),
		'LI' => __('Liechtenstein', 'loginizer'),
		'LK' => __('Sri Lanka', 'loginizer'),
		'LR' => __('Liberia', 'loginizer'),
		'LS' => __('Lesotho', 'loginizer'),
		'LT' => __('Lithuania', 'loginizer'),
		'LU' => __('Luxembourg', 'loginizer'),
		'LV' => __('Latvia', 'loginizer'),
		'LY' => __('Libya', 'loginizer'),
		'MA' => __('Morocco', 'loginizer'),
		'MC' => __('Monaco', 'loginizer'),
		'MD' => __('Moldova', 'loginizer'),
		'ME' => __('Montenegro', 'loginizer'),
		'MF' => __('Saint Martin', 'loginizer'),
		'MG' => __('Madagascar', 'loginizer'),
		'MH' => __('Marshall Islands', 'loginizer'),
		'MK' => __('North Macedonia', 'loginizer'),
		'ML' => __('Mali', 'loginizer'),
		'MM' => __('Myanmar', 'loginizer'),
		'MN' => __('Mongolia', 'loginizer'),
		'MO' => __('Macao', 'loginizer'),
		'MP' => __('Northern Mariana Islands', 'loginizer'),
		'MQ' => __('Martinique', 'loginizer'),
		'MR' => __('Mauritania', 'loginizer'),
		'MS' => __('Montserrat', 'loginizer'),
		'MT' => __('Malta', 'loginizer'),
		'MU' => __('Mauritius', 'loginizer'),
		'MV' => __('Maldives', 'loginizer'),
		'MW' => __('Malawi', 'loginizer'),
		'MX' => __('Mexico', 'loginizer'),
		'MY' => __('Malaysia', 'loginizer'),
		'MZ' => __('Mozambique', 'loginizer'),
		'NA' => __('Namibia', 'loginizer'),
		'NC' => __('New Caledonia', 'loginizer'),
		'NE' => __('Niger', 'loginizer'),
		'NF' => __('Norfolk Island', 'loginizer'),
		'NG' => __('Nigeria', 'loginizer'),
		'NI' => __('Nicaragua', 'loginizer'),
		'NL' => __('The Netherlands', 'loginizer'),
		'NO' => __('Norway', 'loginizer'),
		'NP' => __('Nepal', 'loginizer'),
		'NR' => __('Nauru', 'loginizer'),
		'NU' => __('Niue', 'loginizer'),
		'NZ' => __('New Zealand', 'loginizer'),
		'OM' => __('Oman', 'loginizer'),
		'PA' => __('Panama', 'loginizer'),
		'PE' => __('Peru', 'loginizer'),
		'PF' => __('French Polynesia', 'loginizer'),
		'PG' => __('Papua New Guinea', 'loginizer'),
		'PH' => __('Philippines', 'loginizer'),
		'PK' => __('Pakistan', 'loginizer'),
		'PL' => __('Poland', 'loginizer'),
		'PM' => __('Saint Pierre and Miquelon', 'loginizer'),
		'PN' => __('Pitcairn', 'loginizer'),
		'PR' => __('Puerto Rico', 'loginizer'),
		'PS' => __('Palestinian Territory', 'loginizer'),
		'PT' => __('Portugal', 'loginizer'),
		'PW' => __('Palau', 'loginizer'),
		'PY' => __('Paraguay', 'loginizer'),
		'QA' => __('Qatar', 'loginizer'),
		'RE' => __('Reunion', 'loginizer'),
		'RO' => __('Romania', 'loginizer'),
		'RS' => __('Serbia', 'loginizer'),
		'RU' => __('Russia', 'loginizer'),
		'RW' => __('Rwanda', 'loginizer'),
		'SA' => __('Saudi Arabia', 'loginizer'),
		'SB' => __('Solomon Islands', 'loginizer'),
		'SC' => __('Seychelles', 'loginizer'),
		'SD' => __('Sudan', 'loginizer'),
		'SE' => __('Sweden', 'loginizer'),
		'SG' => __('Singapore', 'loginizer'),
		'SH' => __('Saint Helena', 'loginizer'),
		'SI' => __('Slovenia', 'loginizer'),
		'SJ' => __('Svalbard and Jan Mayen', 'loginizer'),
		'SK' => __('Slovakia', 'loginizer'),
		'SL' => __('Sierra Leone', 'loginizer'),
		'SM' => __('San Marino', 'loginizer'),
		'SN' => __('Senegal', 'loginizer'),
		'SO' => __('Somalia', 'loginizer'),
		'SR' => __('Suriname', 'loginizer'),
		'SS' => __('South Sudan', 'loginizer'),
		'ST' => __('Sao Tome and Principe', 'loginizer'),
		'SV' => __('El Salvador', 'loginizer'),
		'SX' => __('Sint Maarten', 'loginizer'),
		'SY' => __('Syria', 'loginizer'),
		'SZ' => __('Eswatini', 'loginizer'),
		'TC' => __('Turks and Caicos Islands', 'loginizer'),
		'TD' => __('Chad', 'loginizer'),
		'TF' => __('French Southern Territories', 'loginizer'),
		'TG' => __('Togo', 'loginizer'),
		'TH' => __('Thailand', 'loginizer'),
		'TJ' => __('Tajikistan', 'loginizer'),
		'TK' => __('Tokelau', 'loginizer'),
		'TL' => __('Timor Leste', 'loginizer'),
		'TM' => __('Turkmenistan', 'loginizer'),
		'TN' => __('Tunisia', 'loginizer'),
		'TO' => __('Tonga', 'loginizer'),
		'TR' => __('Turkey', 'loginizer'),
		'TT' => __('Trinidad and Tobago', 'loginizer'),
		'TV' => __('Tuvalu', 'loginizer'),
		'TW' => __('Taiwan', 'loginizer'),
		'TZ' => __('Tanzania', 'loginizer'),
		'AE' => __('United Arab Emirates', 'loginizer'),
		'UA' => __('Ukraine', 'loginizer'),
		'UG' => __('Uganda', 'loginizer'),
		'UM' => __('United States Minor Outlying Islands', 'loginizer'),
		'US' => __('United States', 'loginizer'),
		'UY' => __('Uruguay', 'loginizer'),
		'UZ' => __('Uzbekistan', 'loginizer'),
		'VA' => __('Vatican City', 'loginizer'),
		'VC' => __('Saint Vincent and the Grenadines', 'loginizer'),
		'VE' => __('Venezuela', 'loginizer'),
		'VG' => __('British Virgin Islands', 'loginizer'),
		'VI' => __('U.S. Virgin Islands', 'loginizer'),
		'VN' => __('Vietnam', 'loginizer'),
		'VU' => __('Vanuatu', 'loginizer'),
		'WF' => __('Wallis and Futuna', 'loginizer'),
		'WS' => __('Samoa', 'loginizer'),
		'XK' => __('Kosovo', 'loginizer'),
		'YE' => __('Yemen', 'loginizer'),
		'YT' => __('Mayotte', 'loginizer'),
		'ZA' => __('South Africa', 'loginizer'),
		'ZM' => __('Zambia', 'loginizer'),
		'ZW' => __('Zimbabwe', 'loginizer')
	];
}

function loginizer_pro_cb_download_db(){
	global $loginizer;

	if(empty($loginizer['license']) || empty($loginizer['license']['license']) || empty($loginizer['license']['active'])){
		set_transient('loginizer_cdb_error_log', __('The license has expired', 'loginizer'), 1800);
		return;
	}

	if(function_exists('set_time_limit')){
		@set_time_limit(0); 
	}
	
	// Just a flag to show the download has started in the UI
	set_transient('loginizer_cdb_is_downloading', true, 180);

	$country_blocking_data = get_option('lz_pro_country_block', [
		'enabled' => '',
		'countries' => []
	]);

	$countries = [];
	if(empty($country_blocking_data['countries'])){
		$error = __('There is no country in the list, please go to Country block settings then select and save some countries', 'loginizer');
		set_transient('loginizer_cdb_error_log', $error, 1800);
		return;
	}

	$countries = $country_blocking_data['countries'];
	$countries = array_map('strtolower', $countries);
	$countries = array_map('sanitize_text_field', $countries);
	$countries = implode(',', $countries);

	$fastest_api_endpoint = loginizer_pro_cb_get_fast_endpoint();

	$countries_params = http_build_query([
		'license' => $loginizer['license']['license'],
		'countries' => $countries,
		'url' => site_url()
	]);

	$api_endpoint = $fastest_api_endpoint . 'country_db/index.php?'.$countries_params;

	$wp_upload_dir_info = wp_upload_dir();
	$upload_dir = $wp_upload_dir_info['basedir'] . '/loginizer-config';

	if(!is_dir($upload_dir)){
		wp_mkdir_p($upload_dir);
	}

	$destination_path = wp_normalize_path($upload_dir . '/lz-db-country.mmdb');
	$response = wp_remote_get($api_endpoint, ['timeout' => 60]);
	
	delete_transient('loginizer_cdb_is_downloading'); // Deleting the is_downloading flag.

	// Network or WP error
	if(is_wp_error($response)){
		set_transient('loginizer_cdb_error_log', 'Download failed: ' . $response->get_error_message(), 1800);
		return;
	}

	// Get HTTP status code, body and content type to detect if it's valid response or error
	$status_code = wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);

	// If HTTP status is NOT 200, likely JSON error
	if($status_code !== 200){
		$json = json_decode($body, true);

		if(!empty($json['error'])){
			set_transient('loginizer_cdb_error_log', sanitize_text_field(wp_unslash($json['error'])), 1800);
			return;
		}

		$error = __('There was an issue, request responsed with error code', 'loginizer').' '. sanitize_text_field(wp_unslash($status_code));
		set_transient('loginizer_cdb_error_log', $error, 1800);
		return;
	}

	if(empty($body)){
		set_transient('loginizer_cdb_error_log', 'MMDB creation error : Body came empty.', 1800);
		return;
	}
	
	$file_downloaded = file_put_contents($destination_path, $body);
	if(empty($file_downloaded)){
		set_transient('loginizer_cdb_error_log', 'MMDB creation error : Failed to save downloaded file.', 1800);
		return;
	}
	
	// Checking if the downloaded file was a valid mmdb file
	if(!class_exists('\LoginizerMaxMind\Db\Reader')){
		include_once LOGINIZER_PRO_DIR . '/lib/MaxMind/autoloader.php';
	}
	
	try {
		$reader = new \LoginizerMaxMind\Db\Reader($destination_path);
		$metadata = $reader->metadata();
	} catch(\Exception $e){
		set_transient('loginizer_cdb_error_log', sanitize_text_field(wp_unslash($e->getMessage())), 1800);
		return;
	}
	
	delete_transient('loginizer_cdb_error_log');
	update_option('loginizer_country_block_db_download', time());
}

function loginizer_pro_cb_server_type(){
	$sapi_type = php_sapi_name();
	
	// Check for LiteSpeed (SAPI is usually 'litespeed', but check server software to be sure)
	if($sapi_type === 'litespeed' || (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false)){
		return 'litespeed';
	}

	// Check for FastCGI (FPM or generic FastCGI)
	// Covers 'fpm-fcgi' and 'cgi-fcgi'
	if(strpos($sapi_type, 'fcgi') !== false || strpos($sapi_type, 'fpm') !== false){
		return 'fast-cgi';
	}

	// Check for mod_php (Apache Handler)
	// SAPI is typically 'apache2handler' or 'apache'
	if(strpos($sapi_type, 'apache') !== false){
		return 'mod_php';
	}

	// Check for CGI (Standard CGI or suPHP)
	// suPHP runs as a CGI binary, so it reports as 'cgi'
	if($sapi_type === 'cgi'){
		return 'cgi';
	}

	return 'unknown';	
}

// Temp function to check if the firewall is running through server(htaccess or user.ini)
// We will use option table later in the WAF feature
function loginizer_pro_cb_through_auto_prepend(){
	
	if(defined('SITEPAD')){
		global $sitepad;
		$htaccess_file = $sitepad['path'] . '/.htaccess';
		$user_ini_file = $sitepad['path'] . '/.user.ini';
		$loader_path = $sitepad['path'].'/';
	} else {
		$htaccess_file = ABSPATH . '.htaccess';
		$user_ini_file = ABSPATH . '.user.ini';
		$loader_path = ABSPATH;
	}

	if(is_readable($htaccess_file)){
		$needle = 'auto_prepend_file "' . wp_normalize_path($loader_path) . 'loginizer_firewall.php"';
		$content = file_get_contents($htaccess_file);

		if(strpos($content, $needle) !== false){
			return true;
		}
	}
	
	if(is_readable($user_ini_file)){
		$needle = 'auto_prepend_file = "' . wp_normalize_path($loader_path) . 'loginizer_firewall.php"';
		$content = file_get_contents($user_ini_file);
		if(strpos($content, $needle) !== false){
			return true;
		}
    }

	return false;
}

function loginizer_pro_cb_get_fast_endpoint(){
	global $loginizer;
	
	$endpoints = get_transient('loginizer_fastest_endpoint');

	$mirror = 'https://s4.softaculous.com/a/loginizer/';

	if(empty($endpoints)){
		$res = wp_remote_get(LOGINIZER_API.'license.php?license='.$loginizer['license']['license'].'&url='.rawurlencode(site_url()));

		// Did we get a response ?
		if(!is_array($res)){
			return $mirror;
		}

		if(empty($res['body'])){
			return $mirror;
		}

		$body = json_decode($res['body'], true);

		if(empty($body['fast_mirrors'])){
			return $mirror;
		}
		
		$endpoints = $body['fast_mirrors'];
		
		if(empty($endpoints) || !is_array($endpoints)){
			return $mirror;
		}
	}
	
	$index = floor(rand(0, count($endpoints) - 1));

	if(empty($endpoints[$index])){
		return $mirror;
	}

	set_transient('loginizer_fastest_endpoint', $endpoints, 1800);

	$mirror = str_replace('a/softaculous', 'a/loginizer/', $endpoints[$index]);
	
	return $mirror;
	
}
