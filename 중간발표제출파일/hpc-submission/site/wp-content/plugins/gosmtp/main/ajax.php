<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('GOSMTP_VERSION')){
	die('Hacking Attempt!');
}

add_action('wp_ajax_gosmtp_test_mail', 'gosmtp_test_mail');
add_action('wp_ajax_gosmtp_install_mcp_adapter', 'gosmtp_install_mcp_adapter');
add_action('wp_ajax_gosmtp_generate_app_password', 'gosmtp_generate_app_password');
add_action('wp_ajax_gosmtp_test_mcp_connection', 'gosmtp_test_mcp_connection');
add_action('wp_ajax_gosmtp_save_test_status', 'gosmtp_save_test_status');
add_action('wp_ajax_gosmtp_close_update_notice', 'gosmtp_close_update_notice');
add_action('wp_ajax_gosmtp_save_ai_abilities', 'gosmtp_save_ai_abilities');

// Save Ai abilities settings.
function gosmtp_save_ai_abilities(){

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have permission to do that.', 'gosmtp'));
	}

	check_ajax_referer('gosmtp_ajax', 'nonce');

	$options = get_option('gosmtp_options', []);
	$options['ai_abilities']['enabled'] = !empty($_POST['enabled']) ? 1 : 0;

	update_option('gosmtp_options', $options);

	wp_send_json_success([
		'message' => __('Settings Updated Successfully', 'gosmtp')
	]);
}

function gosmtp_test_mail(){
	
	global $phpmailer;

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have required access to do this action', 'gosmtp'));
	}

	// Check nonce
	check_admin_referer( 'gosmtp_ajax' , 'gosmtp_nonce' );

	$to = gosmtp_optpost('reciever_test_email');
	$subject = gosmtp_optpost('smtp_test_subject');
	$body = gosmtp_optpost('smtp_test_message');
	$use_template = gosmtp_optpost('use_html_template');
	
	// TODO: send debug param
	if(isset($_GET['debug'])){
		// show wp_mail() errors
		add_action( 'wp_mail_failed', function( $wp_error ){
			echo "<pre>";
			print_r($wp_error);
			echo "</pre>";
		}, 10, 1 );
	}
	
	$msg = array();
	$headers = [];
	
	// TODO check for mailer
	if(!get_option('gosmtp_options')){
		$msg['error'] = __('You have not configured SMTP settings yet !', 'gosmtp');
	}else{
		
		if(!empty($use_template) && function_exists('gosmtp_pro_test_html_template')){
			$body = gosmtp_pro_test_html_template();
			$headers = ['Content-Type: text/html; charset=UTF-8'];
		}

		$result = wp_mail($to, $subject, $body, $headers);

		if(!$result){
			$msg['error'] = __('Unable to send mail !', 'gosmtp').(empty($phpmailer->ErrorInfo) ? '' : ' '.__('Error : ', 'gosmtp').$phpmailer->ErrorInfo);
		}else{
			$msg['response'] = __('Message sent successfully !', 'gosmtp');
		}
	}
	
	gosmtp_json_output($msg);
}

function gosmtp_close_update_notice(){

	if(!wp_verify_nonce($_GET['security'], 'gosmtp_promo_nonce')){
		wp_send_json_error('Security Check failed!');
	}

	if(!current_user_can('manage_options')){
		wp_send_json_error('You don\'t have privilege to close this notice!');
	}

	$plugin_update_notice = get_option('softaculous_plugin_update_notice', []);
	$available_update_list = get_site_transient('update_plugins');
	$to_update_plugins = apply_filters('softaculous_plugin_update_notice', []);

	if(empty($available_update_list) || empty($available_update_list->response)){
		return;
	}

	foreach($to_update_plugins as $plugin_path => $plugin_name){
		if(isset($available_update_list->response[$plugin_path])){
			$plugin_update_notice[$plugin_path] = $available_update_list->response[$plugin_path]->new_version;
		}
	}

	update_option('softaculous_plugin_update_notice', $plugin_update_notice);
}

// =========================================================================
// === Abilities / MCP =====================================================
// =========================================================================

// Install (or activate) the official WordPress MCP Adapter plugin with one click.
function gosmtp_install_mcp_adapter(){

	if(!current_user_can('install_plugins')){
		wp_send_json_error(__('You do not have permission to do that.', 'gosmtp'));
	}

	check_admin_referer('gosmtp_ajax', 'gosmtp_nonce');

	$options = get_option('gosmtp_options', []);

	if(empty($options['ai_abilities']['enabled'])){
		wp_send_json_error(__( 'The MCP Adapter can not be installed because the **Enable AI Abilities** checkbox in the right sidebar is turned off. Please check it and try again.', 'gosmtp' ));
	}

	$requested_action = !empty($_POST['adapter_action']) ? sanitize_key(wp_unslash($_POST['adapter_action'])) : 'install';

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$installed_file = \GOSMTP\Abilities::get_installed_mcp_adapter_file();

	// Installed but inactive -> just activate.
	if(!empty($installed_file)){

		$activated = activate_plugin($installed_file);

		if(is_wp_error($activated)){
			wp_send_json_error($activated->get_error_message());
		}

		wp_send_json_success([
			'message' => __('MCP Adapter activated.', 'gosmtp'),
			'state'   => 'active',
		]);
	}

	// Nothing installed yet -> install from WordPress.org.
	if($requested_action !== 'install'){
		wp_send_json_error(__('Invalid adapter action.', 'gosmtp'));
	}

	$api = plugins_api('plugin_information', [
		'slug'   => \GOSMTP\Abilities::$MCP_ADAPTER_SLUG,
		'fields' => ['sections' => false],
	]);

	if(is_wp_error($api)){
		wp_send_json_error($api->get_error_message());
	}

	$skin = new \WP_Ajax_Upgrader_Skin();
	$upgrader = new \Plugin_Upgrader($skin);
	$result = $upgrader->install($api->download_link);

	if(is_wp_error($result)){
		wp_send_json_error($result->get_error_message());
	}

	if(empty($result) || $skin->get_errors()->has_errors()){
		$message = $skin->get_errors()->get_error_message();
		wp_send_json_error($message ? $message : __('The MCP Adapter could not be installed.', 'gosmtp'));
	}

	$activated = activate_plugin($upgrader->plugin_info());

	if(is_wp_error($activated)){
		wp_send_json_error($activated->get_error_message());
	}

	$version = \GOSMTP\Abilities::get_installed_mcp_adapter_version();

	wp_send_json_success([
		'message' => sprintf(__('MCP Adapter %s installed and activated.', 'gosmtp'), $version),
		'state'   => 'active',
		'version' => $version,
	]);
}

// Generate a WordPress Application Password for the current user.
function gosmtp_generate_app_password(){

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have permission to generate an Application Password.', 'gosmtp'));
	}

	check_admin_referer('gosmtp_ajax', 'gosmtp_nonce');

	$options = get_option('gosmtp_options', []);

	if(empty($options['ai_abilities']['enabled'])){
		wp_send_json_error(__('Can not generate AI Application Password because the Abilities feature is disabled. Please enable it first.', 'gosmtp'));
	}

	if(!class_exists('\WP_Application_Passwords')){
		wp_send_json_error(__('Application Passwords are not available on this site.', 'gosmtp'));
	}

	$user_id = get_current_user_id();
	if(!$user_id){
		wp_send_json_error(__('You must be logged in to generate an Application Password.', 'gosmtp'));
	}

	if(!wp_is_application_passwords_available_for_user($user_id)){
		wp_send_json_error(__('Application Passwords are not available for your account. Please contact a site administrator.', 'gosmtp'));
	}

	$created = \WP_Application_Passwords::create_new_application_password($user_id, [
		'name'   => \GOSMTP\Abilities::$APP_PASSWORD_NAME,
		'app_id' => \GOSMTP\Abilities::$APP_PASSWORD_APP_ID,
	]);

	if(is_wp_error($created)){
		wp_send_json_error($created->get_error_message());
	}

	$user = wp_get_current_user();

	wp_send_json_success([
		'username' => $user ? $user->user_login : '',
		'password' => isset($created[0]) ? (string)$created[0] : '',
		'message'  => __('Application Password generated. Copy it now — it will not be shown again.', 'gosmtp'),
	]);
}

// Server-side test of the MCP endpoint (fallback when client-side fetch is
// blocked by CORS or unavailable).
function gosmtp_test_mcp_connection(){

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have permission to test the connection.', 'gosmtp'));
	}

	check_admin_referer('gosmtp_ajax', 'gosmtp_nonce');

	$start  = microtime(true);
	$url    = trailingslashit(home_url()) . ltrim(\GOSMTP\Abilities::$ABILITIES_ENDPOINT, '/');

	$username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
	$password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';

	$response = wp_remote_get($url, [
		'headers' => [
			'Accept' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
		],
	]);

	$elapsed = round((microtime(true) - $start) * 1000);

	if(is_wp_error($response)){
		$message = sprintf(__('Could not reach the abilities endpoint (%s).', 'gosmtp'), $response->get_error_message());
		\GOSMTP\Abilities::save_test_connection_status(false, $message);
		wp_send_json_error(['message' => $message]);
	}

	$code = wp_remote_retrieve_response_code($response);
	$body = json_decode(wp_remote_retrieve_body($response), true);

	if($code >= 200 && $code < 300 && is_array($body)){
		$gosmtp_abilities = 0;
		foreach($body as $ability){
			$name = isset($ability['name']) ? $ability['name'] : (isset($ability['id']) ? $ability['id'] : '');
			if(is_string($name) && strpos($name, 'gosmtp-') === 0){
				$gosmtp_abilities++;
			}
		}

		$message = sprintf(__('Authenticated with your Application Password and discovered %1$d GoSMTP abilities in %2$dms. Your site is ready to connect an AI client below.', 'gosmtp'), $gosmtp_abilities, $elapsed);

		\GOSMTP\Abilities::save_test_connection_status(true, $message);

		wp_send_json_success([
			'message'    => $message,
			'abilities'  => $gosmtp_abilities,
			'elapsed_ms' => $elapsed,
		]);
	}

	if($code === 401 || $code === 403){
		$message = __('Your Application Password was rejected — it may have been revoked. Generate a new one and test again.', 'gosmtp');
		\GOSMTP\Abilities::save_test_connection_status(false, $message);
		wp_send_json_error(['message' => $message]);
	}

	$message = sprintf(__('The abilities endpoint responded with status %1$d. Check the MCP Adapter is active and try again.', 'gosmtp'), $code);
	\GOSMTP\Abilities::save_test_connection_status(false, $message);
	wp_send_json_error(['message' => $message]);
}

// Persist the "Test connection" result produced by the client-side fetch
// (which owns the plaintext Application Password) so the pill keeps its
// state across page reloads.
function gosmtp_save_test_status(){

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have permission to do that.', 'gosmtp'));
	}

	check_admin_referer('gosmtp_ajax', 'gosmtp_nonce');

	$ok      = !empty($_POST['ok']);
	$message = !empty($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';

	\GOSMTP\Abilities::save_test_connection_status($ok, $message);

	wp_send_json_success();
}
