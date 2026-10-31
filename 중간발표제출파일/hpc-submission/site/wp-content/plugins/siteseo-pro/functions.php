<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

// Are we being accessed directly ?
if(!defined('ABSPATH')){
	exit('Hacking Attempt !');
}

// Add our license key if ANY
function siteseo_pro_updater_filter_args($queryArgs){
	
	global $siteseo;
	
	if (!empty($siteseo->license['license'])){
		$queryArgs['license'] = $siteseo->license['license'];
	}
	
	$queryArgs['url'] = rawurlencode(site_url());
	
	return $queryArgs;
}

// Handle the Check for update link and ask to install license key
function siteseo_pro_updater_check_link($final_link){
	
	global $siteseo;
	
	if(empty($siteseo->license['license'])){
		return '<a href="'.admin_url('admin.php?page=siteseo-license').'">Install SiteSEO Pro License Key</a>';
	}
	
	return $final_link;
}

// Prevent update of Siteseo free
function siteseo_pro_file_get_free_version_num(){
		
	if(defined('SITESEO_VERSION')){
		return SITESEO_VERSION;
	}
	
	// In case of Siteseo deactive
	return siteseo_pro_file_get_version_num('siteseo/siteseo.php');
}

// Prevent update of Siteseo free
function siteseo_pro_file_get_version_num($plugin){
	
	// In case of Siteseo deactive
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/'.$plugin);

	if(empty($plugin_data)){
		return false;
	}

	return $plugin_data['Version'];

}

// Keep the free and Pro plugins on the same version: only show an update
// when both plugins end up on that same version. This also keeps a Pro
// update from being offered ahead of the free one, which is the source of
// truth on wp.org and subject to a 24-hour review delay.
// https://wordpress.org/news/2026/06/pts/
// Intentionally this also holds the free plugin back when the Pro license is
// inactive: the Pro server then offers no update, and letting the free plugin
// move on alone would break compatibility between the two.
function siteseo_pro_disable_manual_update_for_plugin($transient){
	$free = 'siteseo/siteseo.php';
	$pro = 'siteseo-pro/siteseo-pro.php';

	if(!is_object($transient) || (empty($transient->response[$free]) && empty($transient->response[$pro]))){
		return $transient;
	}

	// While the post-Pro-upgrade handler runs, SITESEO_PRO_VERSION is the pre-upgrade value.
	$upgrading = !empty($GLOBALS['siteseo_pro_is_upgraded']);
	$pro_version = $upgrading ? siteseo_pro_file_get_version_num($pro) : SITESEO_PRO_VERSION;
	$free_version = siteseo_pro_file_get_free_version_num();

	// Where each plugin lands: its offered update, or its installed version if none.
	$free_target = !empty($transient->response[$free]->new_version) ? $transient->response[$free]->new_version : $free_version;
	$pro_target = !empty($transient->response[$pro]->new_version) ? $transient->response[$pro]->new_version : $pro_version;

	// A version we cannot read means we cannot keep the two in step. If the free
	// plugin is the missing one, updating Pro is pointless anyway: it cannot run
	// without the free plugin.
	if(empty($free_target) || empty($pro_target)){
		unset($transient->response[$free], $transient->response[$pro]);
		return $transient;
	}

	// An update is allowed only if it lands the plugin where the other one is now,
	// or where the other one is heading. That lets a plugin left behind catch up
	// to the other, and blocks anything that would push one ahead of the other.
	if(version_compare($free_target, $pro_target, '!=') && version_compare($free_target, $pro_version, '!=')){
		unset($transient->response[$free]);
	}

	if(version_compare($pro_target, $free_target, '!=') && version_compare($pro_target, $free_version, '!=')){
		unset($transient->response[$pro]);
	}

	// Both have an update: show only Pro, it updates the free plugin once done.
	if(isset($transient->response[$free], $transient->response[$pro])){
		unset($transient->response[$upgrading ? $pro : $free]);
	}

	if(isset($transient->response[$free])){
		$transient->response[$free]->new_version = $pro_version;
		$transient->response[$free]->package = 'https://downloads.wordpress.org/plugin/siteseo.'.$pro_version.'.zip';
	}

	return $transient;
}

// Auto update free version after update pro version
function siteseo_pro_update_free_after_pro($upgrader_object, $options){
	
	// Check if the action is an update for the plugins
	if($options['action'] != 'update' || $options['type'] != 'plugin'){
		return;
	}
		
	// Define the slugs for the free and pro plugins
	$free_slug = 'siteseo/siteseo.php'; 
	$pro_slug = 'siteseo-pro/siteseo-pro.php';

	// Check if the pro plugin is in the list of updated plugins
	if( 
		(isset($options['plugins']) && in_array($pro_slug, $options['plugins']) && !in_array($free_slug, $options['plugins'])) ||
		(isset($options['plugin']) && $pro_slug == $options['plugin'])
	){
	
		// Trigger the update for the free plugin
		$current_version = siteseo_pro_file_get_free_version_num();
		
		if(empty($current_version)){
			return;
		}
		
		$GLOBALS['siteseo_pro_is_upgraded'] = true;
		
		// This will set the 'update_plugins' transient again
		wp_update_plugins();

		// Check for updates for the free plugin
		$update_plugins = get_site_transient('update_plugins');
		
		if(empty($update_plugins) || !isset($update_plugins->response[$free_slug]) || version_compare($update_plugins->response[$free_slug]->new_version, $current_version, '<=')){
			return;
		}
		
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
		
		$skin = wp_doing_ajax()? new WP_Ajax_Upgrader_Skin() : null;
		
		$upgrader = new Plugin_Upgrader($skin);
		$upgraded = $upgrader->upgrade($free_slug);
		
		if(!is_wp_error($upgraded) && $upgraded){
			// Re-active free plugins
			if( file_exists( WP_PLUGIN_DIR . '/'.  $free_slug ) && is_plugin_inactive($free_slug) ){
				activate_plugin($free_slug); // TODO for network
			}
			
			// Re-active pro plugins
			if( file_exists( WP_PLUGIN_DIR . '/'.  $pro_slug ) && is_plugin_inactive($pro_slug) ){
				activate_plugin($pro_slug); // TODO for network
			}
		}
	}
}

// Load license data
function siteseo_pro_load_license($parent = 0){
	
	global $siteseo, $lic_resp, $sitepad;
	
	$license_field = 'siteseo_license';
	$license_api_url = SITESEO_API;
	
	// Save license
	if(!empty($parent) && is_string($parent) && strlen($parent) > 5){		
		$lic['license'] = $parent;
		delete_transient('siteseo_license_check_retry');
	
	// Load license of Soft Pro
	}elseif(!empty($parent)){
		$license_field = 'softaculous_pro_license';
		$lic = get_option('softaculous_pro_license', []);
	
	// My license
	}else{
		$lic = get_option($license_field, []);
	}
	
	// Loaded license is a Soft Pro
	if(!empty($lic['license']) && preg_match('/^softwp/is', $lic['license'])){
		$license_field = 'softaculous_pro_license';
		$license_api_url = 'https://a.softaculous.com/softwp/';
		$prods = apply_filters('softaculous_pro_products', []);
	}else{
		$prods = [];
	}

	if(empty($lic['last_update'])){
		$lic['last_update'] = time() - 86600;
	}

	// transient for ratelimit.
	$retry = (int) get_transient('siteseo_license_check_retry');
	
	// Update license details as well
	if(!empty($lic) && !empty($lic['license']) && (time() - @$lic['last_update']) >= 86400 && $retry < 3){
		
		$url = $license_api_url.'/license.php?license='.$lic['license'].'&prods='.implode(',', $prods).'&url='.rawurlencode(site_url());
		$resp = wp_remote_get($url);
		
		$retry++; // updating the retry
		set_transient('siteseo_license_check_retry', $retry, 80000);

		$lic_resp = $resp;

		//Did we get a response ?
		if(is_array($resp)){
			
			$tosave = json_decode($resp['body'], true);
			
			//Is it the license ?
			if(!empty($tosave['license'])){
				$tosave['last_update'] = time();
				update_option($license_field, $tosave);
				$lic = $tosave;
				delete_transient('siteseo_license_check_retry');
			}
		}
	}
	
	// If the license is Free or Expired check for Softaculous Pro license
	if(empty($lic) || empty($lic['active'])){
		
		if(function_exists('softaculous_pro_load_license')){
			$softaculous_license = softaculous_pro_load_license();
			if(!empty($softaculous_license['license']) && 
				(!empty($softaculous_license['active']) || empty($lic['license']))
			){
				$lic = $softaculous_license;
			}
		}elseif(empty($parent)){
			$lic = get_option('softaculous_pro_license', []);
			
			if(!empty($lic)){
				return siteseo_pro_load_license(1);
			}
		}
	}
	
	if(!empty($lic['license'])){
		$siteseo->license = $lic;
	}

	if(defined('SITEPAD') && empty($siteseo->license)){
		$license = (!empty($sitepad['license']) ? $sitepad['license']: (isset($sitepad['server_license']) ? $sitepad['server_license'] : []));
		$license['active'] = isset($license['active']) ? $license['active'] : (isset($license['status']) ? $license['status'] : ''); 
		$siteseo->license = $license;
	}

}

add_filter('softaculous_pro_products', 'siteseo_softaculous_pro_products', 10, 1);
function siteseo_softaculous_pro_products($r = []){
	$r['siteseo'] = 'siteseo';
	return $r;
}

function siteseo_pro_api_url($main_server = 0, $suffix = 'siteseo'){
	global $siteseo;
	
	$r = array(
		'https://s0.softaculous.com/a/softwp/',
		'https://s1.softaculous.com/a/softwp/',
		'https://s2.softaculous.com/a/softwp/',
		'https://s3.softaculous.com/a/softwp/',
		'https://s4.softaculous.com/a/softwp/',
		'https://s5.softaculous.com/a/softwp/',
		'https://s7.softaculous.com/a/softwp/',
		'https://s8.softaculous.com/a/softwp/'
	);

	$mirror = $r[array_rand($r)];

	// If the license is newly issued, we need to fetch from API only
	if(!empty($main_server) || empty($siteseo->license['last_edit']) || 
		(!empty($siteseo->license['last_edit']) && (time() - 3600) < $siteseo->license['last_edit'])
	){
		$mirror = SITESEO_API;
	}

	if(!empty($suffix)){
		$mirror = str_replace('/softwp', '/'.$suffix, $mirror);
	}

	return $mirror;
	
}

function siteseo_pro_plugin_update_notice_filter($plugins = []){
	$plugins['siteseo-pro/siteseo-pro.php'] = 'SiteSEO Pro';
	return $plugins;
}