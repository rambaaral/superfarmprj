<?php

/*
* LOGINIZER SECURITY
* https://loginizer.com/docs/
* (c) LOGINIZER Team
*/
if(!defined('LOGINIZER_FIREWALL')){
	die('HACKING ATTEMPT');
}

if(defined('LZ_FIREWALL_SITEPAD_DOM_PATH')){
	define('LOGINIZER_CB_CONFIG_PATH', LZ_FIREWALL_SITEPAD_DOM_PATH .'/sitepad-data/uploads/loginizer-config/');
} else if(defined('WP_CONTENT_DIR')){
	define('LOGINIZER_CB_CONFIG_PATH', WP_CONTENT_DIR .'/uploads/loginizer-config/');
}else {
	define('LOGINIZER_CB_CONFIG_PATH', dirname(__DIR__, 4) .'/uploads/loginizer-config/');
}

define('LOGINIZER_CB_PRO_PATH', dirname(__DIR__, 2) .'/');

loginizer_pro_cb_init();

function loginizer_pro_cb_init(){
	$config_path = LOGINIZER_CB_CONFIG_PATH.'firewall-config.php';

	if(!is_readable($config_path)){
		return false;
	}

	$file_content = file_get_contents($config_path);
	
	// If config can not be loaded, we will exit
	if($file_content === false){
		return false;
	}
	
	$config = [];

	// Remove the first line (<?php exit;)
	$json_data = substr($file_content, strpos($file_content, "\n") + 1);
	$config = json_decode($json_data, true);

	if((json_last_error() !== JSON_ERROR_NONE) || empty($config) || !is_array($config)){
		return false;
	}
	
	$cb_settings = $config['country_blocking'];

	if(empty($cb_settings) || empty($cb_settings['countries']) || empty($cb_settings['enabled'])){
		return;
	}

	$common_file = dirname(__DIR__, 3) .'/loginizer/common.php';
	$ipv6_file = dirname(__DIR__, 3).'/loginizer/lib/IPv6/IPv6.php';
	if(!file_exists($common_file) || !file_exists($ipv6_file)){
		return;
	}

	include_once($ipv6_file); // Required by whitelist
	include_once($common_file);
	global $loginizer;
	
	if(empty($loginizer)){
		$loginizer = [];
	}

	// We need to use $loginizer and globalize because we are using functions from util
	// and those functions are used in the free version and are dependent on $loginizer.
	$loginizer['ip_method'] = $config['ip_method'];
	$loginizer['custom_ip_method'] = $config['custom_ip_method'];

	$loginizer['current_ip'] = lz_getip();
	
	$loginizer['whitelist'] = [];
	if(!empty($config['whitelist'])){
		$loginizer['whitelist'] = $config['whitelist'];
	}

	if(loginizer_is_whitelisted()){
		return;
	}

	// Allow server IP (Loopback)
	if(!empty($_SERVER['SERVER_ADDR']) && $loginizer['current_ip'] == $_SERVER['SERVER_ADDR']){
		return;
	}

	// Allowed valid bots
	if(loginizer_pro_cb_is_legit_bot()){
		return;
	}

	loginizer_pro_cb_is_verify_access($cb_settings);
}

function loginizer_pro_cb_is_verify_access($config){
	global $loginizer;

	$country = loginizer_pro_cb_ip2country($loginizer['current_ip']);
	
	// False means something went wrong, some part of the conversion did not work.
	if($country === FALSE){
		return;
	}

	if(!empty($config['action']) && $config['action'] == 'allow_selected'){
		// As we only download IP of selected countries, not finding any country in this case means, it is a blocked country.
		if(empty($country) || empty($country['country']) || empty($country['country']['iso_code'])){
			loginizer_pro_cb_block_request($config, '');
			return;
		}

		if(!in_array($country['country']['iso_code'], $config['countries'])){
			loginizer_pro_cb_block_request($config, $country['country']['iso_code']);
		}
	}else if($config['action'] == 'block_selected'){
		// If we did not find the country that means the country is not from a blocked country.
		if(empty($country) || empty($country['country']) || empty($country['country']['iso_code'])){
			return;
		}
		
		if(in_array($country['country']['iso_code'], $config['countries'])){
			loginizer_pro_cb_block_request($config, $country['country']['iso_code']);
			return;
		}
	}
}

function loginizer_pro_cb_ip2country($ip){

	// Include the autoloader for the MaxMind Database Reader
	$maxmind_autoloader = LOGINIZER_CB_PRO_PATH . '/lib/MaxMind/autoloader.php';
	if(!file_exists($maxmind_autoloader)){
		return false;
	}

	include_once($maxmind_autoloader);

	$database_file = LOGINIZER_CB_CONFIG_PATH . 'lz-db-country.mmdb';
	if(!file_exists($database_file)){
		return false;
	}

	$reader = null;
	$country = [];

	try {
		if(!class_exists('\LoginizerMaxMind\Db\Reader')){
			return false;
		}

		$reader = new \LoginizerMaxMind\Db\Reader($database_file);
		$country = $reader->get($ip);

	} catch (\LoginizerMaxMind\Db\Reader\InvalidArgumentException $e){
		if(defined('WP_DEBUG') && WP_DEBUG){
			error_log('[Country Block] : ' . $e->getMessage());
		}
		return false;

	} catch (\LoginizerMaxMind\Db\Reader\InvalidDatabaseException $e){
		if(defined('WP_DEBUG') && WP_DEBUG){
			error_log('[Country Block] : ' . $e->getMessage());
		}
		return false;

	} catch (\LoginizerMaxMind\Db\Reader\AddressNotFoundException $e){
		return $country;

	} catch(\Exception $e){
		if(defined('WP_DEBUG') && WP_DEBUG){
			error_log('[Country Block] : ' . $e->getMessage());
		}
		return false;
	} finally {
		if($reader instanceof \LoginizerMaxMind\Db\Reader){
			$reader->close();
		}
	}

	return $country;
}

function loginizer_pro_cb_block_request($config, $location){
	global $loginizer;

	// Logging the request
	if(!empty($config['logging_enabled'])){

		// Cleaning required $_SERVER data
		$request_uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
		$request_uri = str_replace("\0", '', $request_uri);

		// Remove control characters for logging
		$request_uri = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $request_uri);
		
		$allowed_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

		$request_method = 'GET';
		if(!empty($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], $allowed_methods, true)){
			$request_method = $_SERVER['REQUEST_METHOD'];
		}

		$log = sprintf(
			'time="%s" ip="%s" method="%s" uri="%s" location="%s"' . "\n",
			time(),
			$loginizer['current_ip'],
			$request_method,
			$request_uri,
			$location
		);

		$log_file = LOGINIZER_CB_CONFIG_PATH.'firewall_logs_*.php';
		$log_file_globs = glob($log_file);
		
		if(empty($log_file_globs) || empty($log_file_globs[0])){
			$random = bin2hex(random_bytes(10));
			$log_file = LOGINIZER_CB_CONFIG_PATH.'firewall_logs_'.$random.'.php';
			touch($log_file);
			
			$log = "<?php exit(); \n". $log; // For the first log we will add the exit
		} else {
			$log_file = $log_file_globs[0];
		}

		// Size-based rotation (100KB limit)
		// We will keep only 30 KB if the limit has reached.
		clearstatcache(true, $log_file); // making sure we get the real size, not the cached one.

		$max_size = 102400; // 100KB
		
		$log_file_size = filesize($log_file);
		if(is_writable($log_file) && $log_file_size > $max_size){
			// NOTE:: Could use fseek and just read the last 30 KB and overwrite the file.
			$log_contents = file_get_contents($log_file);

			if(!empty($log_contents)){
				$extract_size = 1024*30; // will extract only recent 30 KBs
			
				$recent_logs = substr($log_contents, -$extract_size);
				
				$closest_newline = strpos($recent_logs, "\n");
				if($closest_newline !== false){
					$recent_logs = substr($recent_logs, $closest_newline+1);
				}
				
				$recent_logs = "<?php exit(); \n". ltrim($recent_logs);
				
				file_put_contents($log_file, $recent_logs, LOCK_EX);				
			}
		}

		// Append new log with file locking
		file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
	}

	// Adding required headers to show HTTP error and prevent cache
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	
	// Some cache plugins checks this constant before storing page cache
	if(!defined('DONOTCACHEPAGE')){
		define('DONOTCACHEPAGE', true);
	}

	http_response_code(403);

	include_once __DIR__ .'/blocked.php';
	die();
}

// Check if the request is from a legitimate bot
function loginizer_pro_cb_is_legit_bot(){
	global $loginizer;

	if(empty($_SERVER['HTTP_USER_AGENT'])){
		return false;
	}

	$ua = $_SERVER['HTTP_USER_AGENT'];

	// Identify Bot by User-Agent
	$bot_signatures = [
		// Search Engines
		'Googlebot',
		'Bingbot',
		'Slurp', // Yahoo
		'DuckDuckBot',
		'Yandex',
		'Baiduspider',
		
		// Google Services & AI
		'AdsBot-Google',
		'Mediapartners-Google', // AdSense
		'FeedFetcher-Google',
		'Google-Extended', // Gemini / Vertex AI
		'Storebot-Google',
		'Google-InspectionTool',

		// Apple
		'Applebot',

		// AI Bots (OpenAI)
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',

		// Social Media Previews
		'Twitterbot',
		'facebookexternalhit', // Facebook, WhatsApp, Instagram
		'LinkedInBot',
		'Pinterestbot',
		'Slackbot',
		'Discordbot',
	];

	foreach($bot_signatures as $name){
		if(stripos($ua, $name) !== false){
			// Verify if it is really a Google Bot
			if(stripos($name, 'Google') !== false){
				if(loginizer_pro_cb_verify_google_bot($loginizer['current_ip'])){
					return true;
				}
				continue;
			}
			
			return true;
		}
	}

	return false;
}

// Verify Google Bot
function loginizer_pro_cb_verify_google_bot($ip){
	
	if(empty($ip)){
		return true;
	}
	
	// If these functions are not available we need to assume that the bot is a google Bot
	// So that we dont end up blocking the real Google Bot, as we can not verify it as of now.
	if(!function_exists('gethostbyaddr') || !function_exists('gethostbyname')){
		return true;
	}
	
	// Reverse DNS to get the host
	$host = gethostbyaddr($ip);

	if($host == $ip || empty($host)){
		return false;
	}

	if(preg_match('/\.googlebot\.com$/i', $host) || preg_match('/\.google\.com$/i', $host) || preg_match('/\.googleusercontent\.com$/i', $host)){
		// Forward DNS look up
		$forward_ip = gethostbyname($host);
		if(!empty($forward_ip) && $ip == $forward_ip){
			return true;
		}
	}

	return false;
}