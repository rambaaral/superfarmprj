<?php

namespace LoginizerPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

// Login page branding, hooked on login_init so it only ever loads on wp-login.php
class LoginBranding {

	// Read once in init(), the login hooks all use this
	private static $opts = [];

	public static function defaults(){
		return [
			'enabled' => false,
			'layout' => 'center',
			'panel_width' => '',
			'logo_id' => 0,
			'logo_width' => 84,
			'logo_url' => '',
			'logo_text' => '',
			'bg_color' => '',
			'bg_image_id' => 0,
			'form_bg' => '',
			'form_text' => '',
			'form_radius' => '',
			'btn_color' => '',
			'btn_text' => '',
			'link_color' => '',
			'message' => '',
			'title' => '',
			'hide_backtoblog' => false,
			'hide_language' => false,
			'custom_css' => '',
		];
	}

	public static function layouts(){
		return [
			'center' => __('Centered', 'loginizer'),
			'left' => __('Left panel', 'loginizer'),
			'right' => __('Right panel', 'loginizer'),
		];
	}

	public static function opts(){
		$opts = get_option('loginizer_login_branding', []);
		return wp_parse_args(is_array($opts) ? $opts : [], self::defaults());
	}

	public static function init(){
		$opts = self::$opts = self::opts();

		add_action('login_enqueue_scripts', [__CLASS__, 'css']);
		add_filter('login_headerurl', [__CLASS__, 'header_url']);
		add_filter('login_headertext', [__CLASS__, 'header_text']);
		add_filter('login_body_class', [__CLASS__, 'body_class']);

		if(!empty($opts['message'])){
			add_filter('login_message', [__CLASS__, 'message']);
		}

		if(!empty($opts['title'])){
			add_filter('login_title', [__CLASS__, 'title'], 10, 2);
		}

		if(!empty($opts['hide_language'])){
			add_filter('login_display_language_dropdown', '__return_false');
		}
	}

	// The re-auth popup inside wp-admin keeps the default compact centered look
	public static function layout(){
		$layout = self::$opts['layout'];

		if(isset($_REQUEST['interim-login']) || !array_key_exists($layout, self::layouts())){
			return 'center';
		}

		return $layout;
	}

	public static function body_class($classes){
		$layout = self::layout();
		$classes[] = 'lz-layout-'.$layout;

		// Shared class for the rules all panel layouts have in common
		if($layout != 'center'){
			$classes[] = 'lz-panel';
		}

		return $classes;
	}

	public static function header_url($url){
		$opts = self::$opts;
		return !empty($opts['logo_url']) ? $opts['logo_url'] : home_url('/');
	}

	public static function header_text($text){
		$opts = self::$opts;
		return !empty($opts['logo_text']) ? esc_html($opts['logo_text']) : esc_html(get_bloginfo('name'));
	}

	public static function message($message){
		return '<p class="message lz-login-message">'.wp_kses_post(self::$opts['message']).'</p>'.$message;
	}

	// "Log In ‹ Site — WordPress" becomes "Log In ‹ {custom title}"
	public static function title($login_title, $title){
		return $title.' &lsaquo; '.esc_html(self::$opts['title']);
	}

	public static function css(){
		$opts = self::$opts;
		$layout = self::layout();
		$css = '';
		$handle = 'login';

		// Our inline CSS is attached to the last stylesheet, so custom CSS always wins
		if($layout != 'center'){
			$handle = 'loginizer-login-layouts';
			wp_enqueue_style($handle, LOGINIZER_PRO_DIR_URL.'assets/css/login-layouts.css', ['login'], LOGINIZER_PRO_VERSION);
		}

		// Same selector, !important and breakpoint as the layout file, this rule loads after it so it wins
		if($layout != 'center' && $opts['panel_width'] !== ''){
			$css .= '@media screen and (min-width:783px){.lz-panel #login,.lz-panel .language-switcher{width:'.min(max(absint($opts['panel_width']), 25), 75).'% !important;}}';
		}

		if(!empty($opts['logo_id']) && ($img = wp_get_attachment_image_src($opts['logo_id'], 'full'))){
			$width = min(max(absint($opts['logo_width']), 20), 320);
			$height = round($img[2] * $width / max($img[1], 1));
			$css .= '.login h1 a{background-image:url("'.esc_url($img[0]).'");background-size:contain;background-position:center;width:'.$width.'px;height:'.$height.'px;max-width:100%;}';
		}

		if($color = sanitize_hex_color($opts['bg_color'])){
			$css .= 'body.login{background-color:'.$color.';}';
		}

		if(!empty($opts['bg_image_id']) && ($img = wp_get_attachment_image_src($opts['bg_image_id'], 'full'))){
			$css .= 'body.login{background-image:url("'.esc_url($img[0]).'");background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;}';
		}

		if($color = sanitize_hex_color($opts['form_bg'])){
			// The panel layouts read the variable, the centered one styles the form box
			// Notices sit next to the form, core paints them white which clashes with a custom form color
			$css .= 'body.login{--lz-panel-bg:'.$color.';}.login form,.login .message,.login .notice,.login .success{background:'.$color.';}';
		}

		if($color = sanitize_hex_color($opts['form_text'])){
			$css .= 'body.login{--lz-panel-text:'.$color.';}.login form,.login form label,.login form .forgetmenot label,.login .message,.login .notice,.login .success{color:'.$color.';}';
		}

		if($opts['form_radius'] !== ''){
			$css .= '.login form{border-radius:'.absint($opts['form_radius']).'px;}';
		}

		if($color = sanitize_hex_color($opts['btn_color'])){
			// The variables theme focus rings and inputs on WP 7+, the explicit rules cover older versions
			$css .= 'body.login{--wp-admin-theme-color:'.$color.';--wp-admin-theme-color-darker-10:color-mix(in srgb,'.$color.' 90%,#000);--wp-admin-theme-color-darker-20:color-mix(in srgb,'.$color.' 80%,#000);}';
			$css .= '.login .button-primary,.login .button-primary:hover,.login .button-primary:focus{background:'.$color.';border-color:'.$color.';}.login .button-primary:hover{filter:brightness(.92);}';
			$css .= '.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus{border-color:'.$color.';box-shadow:0 0 0 1px '.$color.';}';
		}

		if($color = sanitize_hex_color($opts['btn_text'])){
			$css .= '.login .button-primary,.login .button-primary:hover,.login .button-primary:focus{color:'.$color.';}';
		}

		if($color = sanitize_hex_color($opts['link_color'])){
			$css .= '.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a,.login .button.wp-hide-pw{color:'.$color.';}';
		}

		if(!empty($opts['hide_backtoblog'])){
			$css .= '.login #backtoblog{display:none;}';
		}

		if(!empty($opts['custom_css'])){
			$css .= wp_strip_all_tags($opts['custom_css']);
		}

		if($css !== ''){
			wp_add_inline_style($handle, $css);
		}
	}
}
