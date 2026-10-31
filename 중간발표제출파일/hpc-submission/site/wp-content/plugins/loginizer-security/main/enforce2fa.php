<?php

namespace LoginizerPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class Enforce2FA {

	public static function init(){
		global $loginizer;

		if(empty($loginizer['2fa_enforce']) || empty($loginizer['2fa_enforce']['type']) || ($loginizer['2fa_enforce']['type'] === 'none')){
			return;
		}

		add_action('login_form_loginizer_2fa_notice', [__CLASS__, 'notice_page']);

		add_filter('login_redirect', [__CLASS__, 'login_redirect_wp_login'], 10003, 3);

		if(class_exists('WooCommerce')){
			add_filter('woocommerce_registration_redirect', [__CLASS__, 'redirect_to_wc_security_endpoint'], 10003, 1);
			add_filter('woocommerce_login_redirect', [__CLASS__, 'login_redirect_wc_login'], 10003, 2);
		}
		if(class_exists('UM') && !empty($loginizer['ultimate-member-active'])){
			add_action('um_registration_after_auto_login', [__CLASS__, 'redirect_to_um_security_tab'], 10003, 1);
			add_action('um_on_login_before_redirect', [__CLASS__, 'login_redirect_um_login'], 10003, 1);
		}

		add_action('admin_init', [__CLASS__, 'redirect_setup_page']);
		add_action('template_redirect', [__CLASS__, 'redirect_setup_page']);
	}

	public static function login_redirect_wp_login($redirect_to, $requsted_redirect, $user){
		$show_notice_form = self::notice_form($user);
		if($show_notice_form){
			return wp_login_url(). '?action=loginizer_2fa_notice';
		}

		return $redirect_to;
	}

	public static function login_redirect_wc_login($redirect_to, $user){
		$show_notice_form = self::notice_form($user);
		if($show_notice_form){
			$account_page = wc_get_account_endpoint_url('dashboard');
			set_transient('loginizer_2fa_redirect_url_'.$user->ID, ['login' => 'wc', 'url' => $account_page], 600);
			return wp_login_url(). '?action=loginizer_2fa_notice';
		}

		return $redirect_to;
	}

	public static function login_redirect_um_login($user_id){
		$user = get_user_by('ID', $user_id);
		$redirect_to = um_get_core_page('account');

		$show_notice_form = self::notice_form($user);
		if($show_notice_form){
			set_transient('loginizer_2fa_redirect_url_'.$user->ID, ['login' => 'um', 'url' => $redirect_to], 600);

			$redirect_to = sanitize_url(wp_login_url(). '?action=loginizer_2fa_notice');
			wp_safe_redirect($redirect_to);
			exit;
		}
	}

	public static function notice_form($user){
		global $loginizer;

		if(($user instanceof \WP_Error) || !loginizer_is_2fa_applicable($user)){
			return false;
		}

		$is_2fa_pending = get_user_meta($user->ID, 'loginizer_user_settings', true);
		if(!empty($is_2fa_pending)){
			return false;
		}
		
		if(($loginizer['2fa_enforce']['type'] == 'grace_enforce')){
			$user_grace_time = (int) get_user_meta($user->ID, 'loginizer_grace_time_expiry', true);
			if(
				empty($user_grace_time) || 
				(time() < $user_grace_time) || 
				($loginizer['2fa_enforce']['action'] != 'mandatory_setup')
			){
				return true;
			}
		}

		return false;
	}

	public static function notice_page(){
		global $loginizer;

		$user_id = get_current_user_id();
		$user_grace_time = (int) get_user_meta($user_id, 'loginizer_grace_time_expiry', true);

		// If user logged in first time, set grace time for the user
		if(empty($user_grace_time)){
			$user_grace_time = self::grace_time_in_seconds();
			update_user_meta($user_id, 'loginizer_grace_time_expiry', $user_grace_time);
		}
		
		$permalink_structure = get_option('permalink_structure');

		// If skips setup, update user meta with the grace time and redirect to home page
		if(isset($_POST['lz_skip_2fa'])){
			if(!wp_verify_nonce($_REQUEST['_wpnonce'], 'loginizer-2fa-setup')){
				wp_die(__('Security check failed', 'loginizer'));
			}

			$redirect_to = get_transient('loginizer_2fa_redirect_url_'.$user_id);
			delete_transient('loginizer_2fa_redirect_url_'.$user_id);

			if(empty($redirect_to['url'])){
				wp_safe_redirect(admin_url());
			}else{
				wp_safe_redirect(sanitize_url($redirect_to['url']));
			}
			exit;
		}

		// If user wants to complete setup immediatly, redirect to setup page 
		if(isset($_POST['lz_setup_2fa'])){
			if(!wp_verify_nonce($_REQUEST['_wpnonce'], 'loginizer-2fa-setup')){
				wp_die(__('Security check failed', 'loginizer'));
			}

			$redirect_to = get_transient('loginizer_2fa_redirect_url_'.$user_id);
			delete_transient('loginizer_2fa_redirect_url_'.$user_id);
			
			if(empty($redirect_to['url'])){
				wp_safe_redirect(admin_url('admin.php?page=loginizer_user'));
			}else{
				if(!empty($permalink_structure)){
					wp_safe_redirect(sanitize_url($redirect_to['url'] . 'loginizer-security/'));
				}else{
					$endpoint = ($redirect_to['login'] === 'wc') ?  '&loginizer-security' : '&um_tab=loginizer-security';
					wp_safe_redirect(sanitize_url($redirect_to['url'] . $endpoint));
				}
			}
			exit;
		}

		login_header(__('Two Factor Authentication', 'loginizer'));

		echo '<form action="" method="post">';
		wp_nonce_field('loginizer-2fa-setup');

		echo '<p>'.sprintf(esc_html__('Enforced 2FA is enabled on this site which should be configured before %s, failing which will apply mandatory setup for your account and you will not be able to access dashboard without 2FA setup.', 'loginizer'), esc_html(wp_date('j F Y, g:i:s A', $user_grace_time))).'</p><br />';

		echo '<p>'.esc_html__('For more information about 2FA Setup, please contact to the site admin.', 'loginizer').'</p><br />';

		echo '<div style="display:flex;flex-direction:column;gap:5px;">
			<button type="submit" name="lz_skip_2fa" class="button">'.esc_html__('I will do it later', 'loginizer').'</button>
			<button type="submit" name="lz_setup_2fa" class="button button-primary">'.esc_html__('Start 2FA Setup', 'loginizer').'</button>
		</div></form>';

		login_footer();
		exit;
	}

	public static function redirect_to_wc_security_endpoint($redirect_to){
		global $loginizer;

		$current_user = wp_get_current_user();
		if(empty($current_user) || !loginizer_is_2fa_applicable($current_user)){
			return $redirect_to;
		}

		if(isset($_POST['register']) && isset($_POST['email']) && !empty($_POST['email'])){
			// If strict setup enbaled, redirect to WC security endpoint
			if($loginizer['2fa_enforce']['type'] == 'strict_enforce'){
				$redirect_to = wc_get_account_endpoint_url('loginizer-security');
			}else{
				$account_page = wc_get_account_endpoint_url('dashboard');
				set_transient('loginizer_2fa_redirect_url_'.$current_user->ID, ['login' => 'wc', 'url' => $account_page], 600);
				// Redirect to the setup notice page
				$redirect_to = wp_login_url() . '?action=loginizer_2fa_notice';
			}
		}
		return $redirect_to;
	}

	public static function redirect_to_um_security_tab($user_id){
		global $loginizer;

		$current_user = get_user_by('ID', $user_id);
		if(empty($current_user) || !loginizer_is_2fa_applicable($current_user)){
			return;
		}

		$account_page = um_get_core_page('account');
		if($loginizer['2fa_enforce']['type'] == 'strict_enforce'){
			// If strict setup enbaled, redirect to UM security tab
			if(empty($account_page)) return;
			
			$setup_page = $account_page;
			wp_safe_redirect($setup_page);
		}else{
			set_transient('loginizer_2fa_redirect_url_'.$current_user->ID, ['login' => 'um', 'url' => $account_page], 600);
			// Redirect to the setup notice page
			wp_safe_redirect(wp_login_url() . '?action=loginizer_2fa_notice');
		}

		exit;
	}

	public static function redirect_setup_page(){
		global $loginizer, $pagenow;

		if(
			!is_user_logged_in() || 
			wp_doing_ajax() || 
			wp_doing_cron() || 
			(!empty($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] == 'POST')) || 
			(defined('REST_REQUEST') && REST_REQUEST) || 
			(isset($_GET['action']) && ($_GET['action'] === 'logout'))
		){
			return;
		}

		$current_user = wp_get_current_user();
		if(empty($current_user) || !loginizer_is_2fa_applicable($current_user)){
			return;
		}
		
		// Check if user has already completed 2FA setup
		$is_2fa_pending = get_user_meta($current_user->ID, 'loginizer_user_settings', true);
		if(!empty($is_2fa_pending)){
			return;
		}

		// Check if admin has given grace time and it isn't expired yet
		if(!empty($loginizer['2fa_enforce']['type']) && ($loginizer['2fa_enforce']['type'] == 'grace_enforce')){

			$user_grace_time = (int) get_user_meta($current_user->ID, 'loginizer_grace_time_expiry', true);
			if(
				$user_grace_time <= 0 || 
				($user_grace_time > time()) || 
				($loginizer['2fa_enforce']['action'] != 'mandatory_setup')
			){
				// Return if user logins first time, grace time not expired, mandatory setup isn't enabled after grace time expiry
				return;
			}
		}

		// Redirect to the setup page from the admin area
		if(is_admin()){
			if(!(($pagenow === 'admin.php') && isset($_GET['page']) && ($_GET['page'] === 'loginizer_user'))){
				wp_safe_redirect(admin_url('admin.php') . '?page=loginizer_user');
				exit;
			}
			return;
		}

		if(
			(function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('customer-logout')) || 
			(function_exists('um_is_core_page') && um_is_core_page('logout'))
		){
			return;// Return if user logs out
		}

		// Redircet to Security endpoint if strict setup is applied
		if(class_exists('WooCommerce') && function_exists('wc_get_account_endpoint_url')){

			$security_endpoint = self::wc_security_endpoint();
			$endpoint_url = wc_get_account_endpoint_url('loginizer-security');

			// Redirect to security endpoint if on another page
			if(!empty($endpoint_url) && !$security_endpoint){
				wp_safe_redirect($endpoint_url);
				exit;
			}
		}else if(class_exists('UM') && function_exists('um_is_core_page')){
			// Redirect to account page if strict enforce is applied
			if(self::um_security_tab() && function_exists('um_get_core_page')){

				$account_page = um_get_core_page('account');
				if(!empty($account_page)){
					if(!um_is_core_page('account')){

						$redirect_to = $account_page;
						wp_safe_redirect($redirect_to);
						exit;
					}
				}
			}
		}
	}

	// Checks if the Security tab exist for UM Plugin
	public static function um_security_tab(){
		$all_tabs = apply_filters('um_account_page_default_tabs_hook', []);
		if(!empty($all_tabs) && is_array($all_tabs)){
			foreach($all_tabs as $tab){
				return array_key_exists('loginizer-security', $tab);
			}
		}

		return false;
	}

	// Checks if the Security endpoint is exists for WooCommerce
	public static function wc_security_endpoint(){
		global $wp;
		return isset($wp->query_vars['loginizer-security']);
	}

	// Function to convert the grace time into seconds
	public static function grace_time_in_seconds(){
		global $loginizer;

		if(empty($loginizer['2fa_enforce']['time']) || empty($loginizer['2fa_enforce']['time_unit'])){
			return 0;
		}

		if($loginizer['2fa_enforce']['time_unit'] == 'days'){
			return time() + ((int) $loginizer['2fa_enforce']['time']*DAY_IN_SECONDS);
		}

		return time() + ((int) $loginizer['2fa_enforce']['time']*HOUR_IN_SECONDS);
	}

	// 2FA Enforcement for the UM Plugin security tab
	public static function switch_um_security_tab($user){
		global $loginizer;

		if(!($user instanceof \WP_User)){
			return '';
		}

		$enforce_script = '
jQuery(document).ready(function($){
	(function($){
		var switch_tab = setInterval(function(){
			var tab = $(".um-account-link[data-tab=\"loginizer-security\"]");
			var content = $(".um-account-tab[data-tab=\"loginizer-security\"]");

			if(tab.length && content.length){
				$(".um-account-link").removeClass("current");
				$(".um-account-tab").removeClass("current").hide();

				tab.addClass("current").trigger("click");
				content.addClass("current").show();

				clearInterval(switch_tab);
			}
		}, 100);

		$(".um-account-side ul li a").on("click", function(e){
			var current_tab = $(this).attr("data-tab");
			var target_tab = "loginizer-security";

			if((current_tab != target_tab)){
				e.preventDefault();
				e.stopImmediatePropagation();
				alert("'.esc_html__('Enforced Two Factor Authentication is enabled on this site. Please setup Two Factor Authentication first.', 'loginizer').'");
			}
		});
	})(jQuery);
});
';

		$add_script = false;

		if(!empty($loginizer['2fa_enforce']['type']) && ($loginizer['2fa_enforce']['type'] == 'strict_enforce')){
			$add_script = true;
		}

		if(!empty($loginizer['2fa_enforce']['action']) && ($loginizer['2fa_enforce']['action'] == 'mandatory_setup')){
			// Check if admin has given grace time but it is not expired yet for the current user
			// if expired. check strict enforce is enbaled?, if yes then return script to enforce
			$user_grace_time = (int) get_user_meta($user->ID, 'loginizer_grace_time_expiry', true);
			
			if(($user_grace_time > 0) && (time() > $user_grace_time)){
				$add_script = true;
			}			
		}

		if(!empty($add_script)){
			wp_register_script('loginizer-pro-um-tab-script', '', array('jquery'), LOGINIZER_PRO_VERSION, true);
			wp_enqueue_script('loginizer-pro-um-tab-script');
			wp_add_inline_script('loginizer-pro-um-tab-script', $enforce_script);
		}
	}
}