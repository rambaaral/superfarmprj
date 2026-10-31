<?php

namespace LoginizerPro\Settings;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class LoginBranding {

	public static function page(){
		global $lz_error;

		if(!isset($_POST['lz_pro_save_branding']) && !isset($_POST['lz_pro_reset_branding'])){
			return self::render();
		}

		if(!current_user_can('manage_options')){
			$lz_error['invalid_capability'] = __('Sorry, but you do not have permission to access this page.', 'loginizer');
			return self::render();
		}

		if(!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'loginizer-pro-options')){
			$lz_error['invalid_nonce'] = __('Empty or invalid nonce field.', 'loginizer');
			return self::render();
		}

		if(isset($_POST['lz_pro_reset_branding'])){
			delete_option('loginizer_login_branding');
			$GLOBALS['lz_saved'] = __('The login page has been reset to the WordPress default', 'loginizer');
			return self::render();
		}

		$post = wp_parse_args(wp_unslash($_POST), \LoginizerPro\LoginBranding::defaults());

		// Every field is scalar, a tampered array would fatal trim() and friends on PHP 8
		$post = array_map(function($value){ return is_scalar($value) ? (string) $value : ''; }, $post);
		$old = \LoginizerPro\LoginBranding::opts();
		$opts = [];

		$opts['enabled'] = !empty($post['enabled']);
		$opts['layout'] = (array_key_exists($post['layout'], \LoginizerPro\LoginBranding::layouts())) ? $post['layout'] : 'center';
		$opts['panel_width'] = (trim($post['panel_width']) !== '') ? min(max(absint($post['panel_width']), 25), 75) : '';
		$opts['logo_id'] = absint($post['logo_id']);
		$opts['logo_width'] = min(max(absint($post['logo_width']), 20), 320);
		$opts['logo_url'] = esc_url_raw(trim($post['logo_url']));
		$opts['logo_text'] = sanitize_text_field($post['logo_text']);
		$opts['bg_image_id'] = absint($post['bg_image_id']);
		$opts['form_radius'] = (trim($post['form_radius']) !== '') ? min(absint($post['form_radius']), 50) : '';
		$opts['message'] = wp_kses_post($post['message']);
		$opts['title'] = sanitize_text_field($post['title']);
		$opts['hide_backtoblog'] = !empty($post['hide_backtoblog']);
		$opts['hide_language'] = !empty($post['hide_language']);

		foreach(['bg_color', 'form_bg', 'form_text', 'btn_color', 'btn_text', 'link_color'] as $key){
			$opts[$key] = (string) sanitize_hex_color($post[$key]);
		}

		// Raw CSS is as powerful as HTML, so it follows the same rule WordPress uses for Additional CSS
		$opts['custom_css'] = current_user_can('unfiltered_html') ? wp_strip_all_tags($post['custom_css']) : $old['custom_css'];

		update_option('loginizer_login_branding', $opts);

		$GLOBALS['lz_saved'] = true;
		self::render();
	}

	// Image picker: hidden attachment ID + preview + select/remove buttons (wired in assets/js/admin.js)
	private static function media_field($name, $id){
		$src = !empty($id) ? wp_get_attachment_image_url($id, 'medium') : '';

		echo '<div class="lz-media-field">
			<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($src ? $id : '').'" />
			<img src="'.esc_url($src).'" style="max-width:200px; max-height:100px; display:'.($src ? 'block' : 'none').'; margin-bottom:8px;" alt="" />
			<button type="button" class="button lz-media-select">'.esc_html__('Select Image', 'loginizer').'</button>
			<button type="button" class="button-link lz-media-remove" style="margin-left:8px; display:'.($src ? 'inline' : 'none').';">'.esc_html__('Remove', 'loginizer').'</button>
		</div>';
	}

	private static function color_field($name, $value){
		echo '<input type="text" class="lz-color-picker" name="'.esc_attr($name).'" value="'.esc_attr($value).'" />';
	}

	private static function render(){
		global $lz_error;

		$opts = \LoginizerPro\LoginBranding::opts();

		loginizer_page_header(__('Login Page Branding', 'loginizer'));

		if(!empty($GLOBALS['lz_saved'])){
			echo '<div id="message" class="updated"><p>'. (is_string($GLOBALS['lz_saved']) ? esc_html($GLOBALS['lz_saved']) : __('The settings were saved successfully', 'loginizer')). '</p></div><br />';
		}

		if(!empty($lz_error)){
			lz_report_error($lz_error);
			echo '<br />';
		}

		$rows = [
			'layout' => [__('Layout', 'loginizer'), __('Panel layouts show the background on the other side. Small screens always use the centered layout.', 'loginizer')],
			'panel_width' => [__('Panel Width', 'loginizer'), __('Panel layouts only. Percentage of the screen, 25 to 75. Leave blank for a 420px panel.', 'loginizer')],
			'logo_id' => [__('Logo', 'loginizer'), __('Replaces the WordPress logo.', 'loginizer')],
			'logo_width' => [__('Logo Width', 'loginizer'), __('In pixels, 20 to 320. The height follows the image proportions.', 'loginizer')],
			'logo_url' => [__('Logo Link', 'loginizer'), __('Where the logo links to. Default: your homepage.', 'loginizer')],
			'logo_text' => [__('Logo Text', 'loginizer'), __('Read by screen readers. Default: your site title.', 'loginizer')],
			'bg_color' => [__('Background Color', 'loginizer'), ''],
			'bg_image_id' => [__('Background Image', 'loginizer'), __('Covers the whole page.', 'loginizer')],
			'form_bg' => [__('Form Background', 'loginizer'), __('In panel layouts this colors the whole panel.', 'loginizer')],
			'form_text' => [__('Form Text Color', 'loginizer'), ''],
			'form_radius' => [__('Form Corner Radius', 'loginizer'), __('In pixels. Leave blank for the default.', 'loginizer')],
			'btn_color' => [__('Button Color', 'loginizer'), __('Also used for the input focus outline.', 'loginizer')],
			'btn_text' => [__('Button Text Color', 'loginizer'), ''],
			'link_color' => [__('Link Color', 'loginizer'), __('Lost password, back to site and privacy links.', 'loginizer')],
			'message' => [__('Message Above Form', 'loginizer'), __('Basic HTML is allowed.', 'loginizer')],
			'title' => [__('Browser Tab Title', 'loginizer'), __('Replaces "Site Name — WordPress" in the tab title.', 'loginizer')],
			'hide_backtoblog' => [__('Hide "Go to site" Link', 'loginizer'), ''],
			'hide_language' => [__('Hide Language Switcher', 'loginizer'), ''],
			'custom_css' => [__('Custom CSS', 'loginizer'), __('For anything the options above do not cover. The body has the class lz-layout-{layout} and WordPress adds one per screen, e.g. login-action-lostpassword.', 'loginizer')],
		];

		echo '<style>
	.lz-layout-choice{display:inline-block; margin-right:16px; text-align:center; cursor:pointer;}
	.lz-layout-thumb{display:flex; width:96px; height:60px; border:1px solid #c3c4c7; background:#dcdcde; margin-bottom:4px;}
	.lz-layout-thumb span{display:block; background:#fff;}
	.lz-layout-thumb.center{align-items:center; justify-content:center;}
	.lz-layout-thumb.center span{width:30px; height:36px;}
	.lz-layout-thumb.right{justify-content:flex-end;}
	.lz-layout-thumb.left span, .lz-layout-thumb.right span{width:36px; height:100%;}
	.lz-layout-choice input:checked + .lz-layout-thumb{outline:2px solid #2271b1;}
	</style>

		<form action="" method="post">
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><span>'.esc_html__('Login Page Branding', 'loginizer').'</span></h2>
			</div>
			<div class="inside">';

		wp_nonce_field('loginizer-pro-options');

		echo '<table class="form-table">
				<tr>
					<td colspan="2"><i>'.esc_html__('Make the WordPress login page match your brand. This also applies to the Two Factor, PasswordLess, lost password and register screens.', 'loginizer').'</i>
					<a href="'.esc_url(wp_login_url()).'" target="_blank" style="margin-left:8px;">'.esc_html__('Preview login page', 'loginizer').' &rarr;</a></td>
				</tr>
				<tr>
					<td style="width:40%;"><label><b>'.esc_html__('Enable', 'loginizer').'</b></label></td>
					<td><input type="checkbox" name="enabled" value="1" '.checked($opts['enabled'], true, false).' /></td>
				</tr>';

		// Rows that only apply with another setting, toggled live in assets/js/admin.js
		$hidden = [
			'panel_width' => $opts['layout'] == 'center',
			'logo_width' => empty($opts['logo_id']),
		];

		foreach($rows as $key => $row){
			echo '<tr id="lz-row-'.esc_attr($key).'"'.(!empty($hidden[$key]) ? ' style="display:none;"' : '').'>
				<td style="width:40%;"><label><b>'.esc_html($row[0]).'</b></label>'.(!empty($row[1]) ? '<br><span class="description">'.esc_html($row[1]).'</span>' : '').'</td>
				<td>';

			switch($key){
				case 'layout':
					foreach(\LoginizerPro\LoginBranding::layouts() as $layout => $label){
						echo '<label class="lz-layout-choice">
							<input type="radio" name="layout" value="'.esc_attr($layout).'" class="screen-reader-text" '.checked($opts['layout'], $layout, false).' />
							<span class="lz-layout-thumb '.esc_attr($layout).'"><span></span></span>'.esc_html($label).'
						</label>';
					}
					break;

				case 'logo_id':
				case 'bg_image_id':
					self::media_field($key, $opts[$key]);
					break;

				case 'bg_color':
				case 'form_bg':
				case 'form_text':
				case 'btn_color':
				case 'btn_text':
				case 'link_color':
					self::color_field($key, $opts[$key]);
					break;

				case 'panel_width':
					echo '<input type="number" min="25" max="75" name="panel_width" value="'.esc_attr($opts['panel_width']).'" placeholder="420px" style="width:100px;" /> %';
					break;

				case 'logo_width':
				case 'form_radius':
					echo '<input type="number" min="0" max="'.($key == 'logo_width' ? 320 : 50).'" name="'.esc_attr($key).'" value="'.esc_attr($opts[$key]).'" style="width:100px;" /> px';
					break;

				case 'hide_backtoblog':
				case 'hide_language':
					echo '<input type="checkbox" name="'.esc_attr($key).'" value="1" '.checked($opts[$key], true, false).' />';
					break;

				case 'message':
					echo '<textarea name="message" rows="3" style="width:70%;">'.esc_textarea($opts['message']).'</textarea>';
					break;

				case 'custom_css':
					if(current_user_can('unfiltered_html')){
						echo '<textarea name="custom_css" rows="8" style="width:70%; font-family:monospace;">'.esc_textarea($opts['custom_css']).'</textarea>';
					}else{
						echo '<i>'.esc_html__('Only users allowed to add unfiltered HTML can edit custom CSS.', 'loginizer').'</i>';
					}
					break;

				default:
					echo '<input type="'.($key == 'logo_url' ? 'url' : 'text').'" name="'.esc_attr($key).'" value="'.esc_attr($opts[$key]).'" style="width:70%;" />';
			}

			echo '</td></tr>';
		}

		echo '</table><br />
			<center>
				<input name="lz_pro_save_branding" class="button button-primary action" value="'.esc_attr__('Save Settings', 'loginizer').'" type="submit" />
				<input name="lz_pro_reset_branding" class="button action" value="'.esc_attr__('Reset to Default', 'loginizer').'" type="submit" onclick="return confirm(\''.esc_js(__('This will remove all login page branding. Continue?', 'loginizer')).'\');" />
			</center>
			</div>
		</div>
		</form>';

		loginizer_page_footer();
	}
}
