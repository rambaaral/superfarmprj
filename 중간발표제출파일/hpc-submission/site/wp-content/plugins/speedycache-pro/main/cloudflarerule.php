<?php

namespace SpeedyCache;

if (!defined('ABSPATH')) {
	exit;
}

class CloudflareRule {
    
	// Excluded
	static $rule_parts = [];
	static $excludes = [];
	static $cached_ruleset = [];
	static $ruleset_id;
	static $rule_id;
	static $options = [];
	static $last_error_code = 0;
	static $rule_created = false;
    
	static $rule_description = '[DO NOT EDIT] SpeedyCache Plugin cache rules';

	static function init_data(){
		
		// Currently not tested with Sitepad so we will skip it.
		if(defined('SITEPAD')){
			return;
		}

		self::$excludes = get_option('speedycache_exclude', []);
		self::$options = get_option('speedycache_cdn', []);

		if(!is_array(self::$options)){
			self::$options = [];
		}

		self::$ruleset_id = !empty( self::$options['cloudflare_ruleset_id'] ) ? self::$options['cloudflare_ruleset_id'] : '';
		self::$rule_id = !empty( self::$options['cloudflare_rule_id'] ) ? self::$options['cloudflare_rule_id'] : '';
	}

	// The Args to be forwarded for rule creation/update are being fetched from here.
	static function get_rule_args(){
		return [
			'action' => 'set_cache_settings',
			'action_parameters' => [
				'cache' => true,
				'edge_ttl' => [
					'mode' => 'override_origin',
					'default' => 86400,
				],
				'browser_ttl' => array(
					'mode' => 'respect_origin',
				),
			],
			'description' => self::build_rule_description(),
			'enabled' => true,
			'expression' => self::get_rule_expression(),
		];
	}

	// It combines all parts of the rules to prepare the final Cloudflare expression.
	static function get_rule_expression(){

		self::$rule_parts = [];

		self::$rule_parts[] = 'http.request.method eq "GET"';
		self::add_host_wildcard();
		self::process_cookies();
		self::process_paths();
		self::process_static_defaults();

		$expression = implode(' and ', self::$rule_parts);
		$expression = str_replace(["\n", "\r", "\t"], '', $expression);
		$expression = preg_replace('/\s+/', ' ', $expression);

		return '(' . trim($expression) . ')';
	}

	static function add_host_wildcard() {
		$home_url = home_url();
		$domain = preg_replace('#^(https?://)?(www\.)?#', '', $home_url);
		$domain = rtrim($domain, '/');
        
		self::$rule_parts[] = sprintf('http.host wildcard "%s*"', $domain);
	}

	// It checks the default and user-defined cookies and adds them to the cache bypass (not contains) rules.
	static function process_cookies(){

		$default_cookies = ['wordpress_logged_in_'];
		$user_cookies = [];

		foreach(self::$excludes as $rule){

			if(!isset($rule['type']) || $rule['type'] !== 'cookie'){
				continue;
			}

			$content = isset($rule['content']) ? trim($rule['content']) : '';
			$prefix = isset($rule['prefix']) ? trim($rule['prefix']) : '';

			if(!empty($content)){
				$user_cookies[] = trim($content, '^');
			}elseif (!empty($prefix)) {
				$user_cookies[] = trim($prefix, '^');
			}
		}

		$all_cookies = array_unique(array_merge($default_cookies, $user_cookies));

		foreach ($all_cookies as $cookie) {
			self::$rule_parts[] = sprintf('not http.cookie contains "%s"', $cookie);
		}
	}

	// It adds the default paths and the URLs of the user-excluded pages, posts, and categories to the cache bypass rules.
	static function process_paths(){

		$default_paths = ['/wp-admin', '/wp-login', '/wp-json', '/site-admin'];

		// Got the login slug from Loginizer and added it to the default paths for exclusion.
		$loginizer_option = get_option('loginizer_security', []);
		$login_slug = !empty($loginizer_option['login_slug']) ? trim($loginizer_option['login_slug']) : '';

		if(!empty($login_slug)){
			$login_slug = '/' . trim($login_slug, '/');
			$default_paths[] = $login_slug;
		}
		
		// WooCommerce paths to be excluded
		if(class_exists('woocommerce')){
			$default_paths[] = '/cart';
			$default_paths[] = '/checkout';

			$myaccount_page_id = get_option('woocommerce_myaccount_page_id');
			if(!empty($myaccount_page_id)){
				$myaccount_path = wp_parse_url(get_permalink($myaccount_page_id), PHP_URL_PATH);
				if(!empty($myaccount_path) && $myaccount_path !== '/'){
					$default_paths[] = '/' . trim($myaccount_path, '/');
				}
			}
		}

		$exclude_homepage = false;
		$exclude_categories = false;

		foreach (self::$excludes as $rule){

			if(!isset($rule['type']) || $rule['type'] !== 'page'){
				continue;
			}

			$prefix = isset($rule['prefix']) ? trim($rule['prefix']) : '';

			switch ($prefix) {
				case 'homepage':
					$exclude_homepage = true;
					break;
				
				case 'contain':
					if(!empty($rule['content'])){
						self::$rule_parts[] = 'not http.request.full_uri contains "'.$rule['content'].'"';
					}
					break;
					
				case 'category':
					$exclude_categories = true;
					break;
				}
		}

		// Default exclusions
		foreach ($default_paths as $path) {
			self::$rule_parts[] = sprintf('not http.request.uri.path contains "%s"', $path);
		}

		// Homepage
		if($exclude_homepage){
			self::$rule_parts[] = 'not http.request.uri.path eq "/"';
		}
		
		if($exclude_categories){
			self::$rule_parts[] = 'not http.request.full_uri contains "category"';
		}
	}

	// It adds the default rules to bypass cache and exclude XML and XSL PHP static files.
	static function process_static_defaults(){
		self::$rule_parts[] = 'not http.request.uri.path contains ".xml"';
		self::$rule_parts[] = 'not http.request.uri.path contains ".xsl"';
		self::$rule_parts[] = 'not http.request.uri.path contains ".php"';
	}

	// Create a new ruleset ID for the current zone
	static function create_ruleset(&$error = ''){

		$zone_id = self::get_zone_id();
		if(empty($zone_id)){
			return '';
		}

		// It creates a new ruleset for the zone by sending a POST request to the Cloudflare API.
		$url = sprintf( 'https://api.cloudflare.com/client/v4/zones/%s/rulesets', $zone_id);
		$args = self::get_api_auth_args();
		$args['body'] = wp_json_encode(self::get_ruleset_args());
		$response = wp_remote_post($url, $args);

		if(!self::is_success_api_response($response, $error)){
			return '';
		}

		$response_body = json_decode(wp_remote_retrieve_body($response), true);

		if(!is_array($response_body) || !isset($response_body['result']['id'])){
			return '';
		}

		self::$cached_ruleset = $response_body['result'];
		self::$ruleset_id = $response_body['result']['id'];

		// Save in database
		self::$options['cloudflare_ruleset_id'] = self::$ruleset_id;
		update_option('speedycache_cdn', self::$options);

		return self::$ruleset_id;
	}

	// It returns the necessary arguments and configurations to create a new cache ruleset on Cloudflare.
	static function get_ruleset_args(){
		return [
			'name' => 'Speedycache Plugin',
			'description' => 'Ruleset made with Speedycache Plugin',
			'kind' => 'zone',
			'phase' => 'http_request_cache_settings',
			'rules' => [],
		];
	}

	static function get_ruleset(&$error = ''){

		$zone_id = self::get_zone_id();

		if(empty($zone_id)){
			return [];
		}

		if(!empty(self::$cached_ruleset)){
			return self::$cached_ruleset;
		}

		$url = sprintf( 'https://api.cloudflare.com/client/v4/zones/%s/rulesets/phases/http_request_cache_settings/entrypoint', $zone_id);
		$args = self::get_api_auth_args();
		$response = wp_safe_remote_get( $url, $args );

		if(!self::is_success_api_response($response, $error)){
			return [];
		}

		$response_body = json_decode(wp_remote_retrieve_body($response), true);
		self::$cached_ruleset = $response_body['result'];

		return !empty($response_body['result']) ? $response_body['result'] : [];
	}

	static function get_ruleset_id(&$error = ''){

		$zone_id = self::get_zone_id();

		if(empty($zone_id)) return '';

		if(!empty(self::$ruleset_id)){
			return self::$ruleset_id;
		}

		$ruleset = self::get_ruleset($error);

		if(!empty($ruleset['id'])){
			self::$ruleset_id = $ruleset['id'];
			$options = get_option('speedycache_cdn', []);

			if (empty($options['cloudflare_ruleset_id']) || $options['cloudflare_ruleset_id'] !== self::$ruleset_id) {
				$options['cloudflare_ruleset_id'] = self::$ruleset_id;
				update_option('speedycache_cdn', $options);
			}

			return self::$ruleset_id;
		}

		return '';
	}

	// Get the rule.
	static function get_rule(){

		$zone_id = self::get_zone_id();

		if(empty($zone_id)){
			return [];
		}

		$ruleset = self::get_ruleset();

		if(empty($ruleset['rules'])){
			return [];
		}

		// Defined scope context signature to stop undefined dynamic evaluation 
		$current_description = self::build_rule_description();

		foreach($ruleset['rules'] as $rule){

			if(self::is_sp_rule($rule) && isset($rule['description']) && $rule['description'] === $current_description){
				return $rule;
			}
		}
		return [];
	}

	static function get_rule_id(){

		$zone_id = self::get_zone_id();

		if(empty($zone_id)){
			return '';
		}

		if(!empty(self::$rule_id)){
			return self::$rule_id;
		}

		$rule = self::get_rule();

		if(!isset($rule['id'])){
			return '';
		}

		self::$rule_id = $rule['id'];
		return $rule['id'];
	}

	// It updates an existing cache rule by sending a PATCH request to the Cloudflare API with the new arguments.
	static function update_rule(&$error = ''){

		$zone_id = self::get_zone_id();
		$ruleset_id = self::get_ruleset_id();
		$rule_id = self::get_rule_id();

		if(empty($zone_id) || empty($ruleset_id) || empty($rule_id)){
			return '';
		}

		$url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/rulesets/%s/rules/%s', $zone_id, $ruleset_id, $rule_id);
		$request_args = self::get_api_auth_args();
		$request_args['method'] = 'PATCH';
		$request_args['body'] = wp_json_encode( self::get_rule_args());
		$response = wp_remote_request( $url, $request_args );

		$response_code = wp_remote_retrieve_response_code($response);
        
		if ($response_code === 404) {
			self::clean_local_rule_data();
			return '';
		}

		if(!self::is_success_api_response($response, $error)){
			return '';
		}

		$response_body = json_decode(wp_remote_retrieve_body( $response ), true);

		if(!is_array($response_body)){
			return '';
		}
        
		self::$cached_ruleset = [];
		return isset( $response_body['result'], $response_body['result']['id'] ) ? $response_body['result']['id'] : '';
	}

	// Create a cache rule in the Cloudflare API.
	static function create_rule(&$error) {

		$zone_id = self::get_zone_id();

		if(empty($zone_id)){
			return '';
		}

		self::$cached_ruleset = [];

		$ruleset = self::get_ruleset($error);
		$current_payload = self::get_rule_args();
		$rule_description = self::build_rule_description();

		if(!empty($ruleset['rules'])){
			foreach ($ruleset['rules'] as $existing_rule){
				if(self::is_sp_rule($existing_rule) && isset($existing_rule['description']) && $existing_rule['description'] === $rule_description){
                    
					self::$rule_id = $existing_rule['id'];
					$options = get_option('speedycache_cdn', []);
					$options['cloudflare_rule_id'] = self::$rule_id;

					update_option('speedycache_cdn', $options);
					self::$options['cloudflare_rule_id'] = self::$rule_id;

					return self::$rule_id;
				}
			}
		}

		$url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/rulesets/%s/rules', $zone_id, self::get_ruleset_id());
		$request_args = self::get_api_auth_args();
		$request_args['body'] = wp_json_encode($current_payload);
		$response = wp_remote_post($url, $request_args);
        
		if(!self::is_success_api_response($response, $error)){
			return '';
		}
            
		$response_body = json_decode(wp_remote_retrieve_body($response), true);

		if(!is_array($response_body) || !isset($response_body['result']['rules']) || !is_array($response_body['result']['rules'])){
			return '';
		}
        
		foreach($response_body['result']['rules'] as $rule){
			if(isset($rule['description']) && $rule['description'] === $rule_description){
				self::$rule_id = $rule['id'];
                
				$options = get_option('speedycache_cdn', []);
				$options['cloudflare_rule_id'] = self::$rule_id;
				update_option('speedycache_cdn', $options);
				self::$options['cloudflare_rule_id'] = self::$rule_id;

				break;
			}
		}

		self::$cached_ruleset = [];
		return self::$rule_created = true;
	}

	static function rule_was_created(){
		return self::$rule_created;
	}

	// It removes the existing cache rule by sending a DELETE request to the Cloudflare API and cleans up the data.
	static function delete_rule(&$error = ''){

		$zone_id = self::get_zone_id();
		$ruleset_id = self::get_ruleset_id();
		$rule_id = self::get_rule_id();

		if(empty($rule_id) || empty($ruleset_id) || empty($zone_id)){
			return false;
		}

		$url = sprintf( 'https://api.cloudflare.com/client/v4/zones/%s/rulesets/%s/rules/%s', $zone_id, $ruleset_id, $rule_id);
		$args = self::get_api_auth_args();
		$args['method'] = 'DELETE';
		$response = wp_remote_request($url, $args);

		if(!self::is_success_api_response($response, $error)){
			if(wp_remote_retrieve_response_code($response) ===  404){
				self::clean_local_rule_data();
				return true;
			}
			return '';
		}

		$status = wp_remote_retrieve_response_code( $response ) == 200;
		self::clean_local_rule_data();

		return $status;
	}

	// It clears/resets all saved Cloudflare rule IDs from local memory variables and the WordPress database options.
	static function clean_local_rule_data(){
		self::$rule_id = '';
		self::$cached_ruleset = [];
		self::$options['cloudflare_rule_id'] = '';
		update_option('speedycache_cdn', self::$options);
	}
  
	// Required Authorization Bearer token and headers for Cloudflare API requests
	static function get_api_auth_args(){

		$options = get_option('speedycache_cdn', []);
		return [
			'headers' => [
				'Authorization' => 'Bearer ' . (isset($options['cdn_key']) ? $options['cdn_key'] : ''),
				'Content-Type'  => 'application/json',
			],
		];
	}

	// Checks the Cloudflare API response and formats the error messages if any errors occur, then returns them.
	static function is_success_api_response($response, &$error = '') {

		if (is_wp_error($response)) {
			$error = $response->get_error_message();
			return false;
		}

		$body_raw = wp_remote_retrieve_body($response);
		$body = json_decode($body_raw, true);

		if (!empty($body['success'])) {
			return true;
		}

		if (empty($body['errors']) || !is_array($body['errors'])) {
			return false;
		}

		$messages = [];

		foreach($body['errors'] as $single_error){
            
			if(empty($single_error['message'])){
				continue;
			}

			if($single_error['code'] == 10000){
				self::$last_error_code = 10000;
				$error = 'Please check your token permissions.';
				return false;
			}

			$msg = $single_error['message'];

			if(!empty($single_error['code'])){
				$msg .= ' (' . $single_error['code'] . ')';
			}

			$messages[] = $msg;
		}

		if(!empty($messages)){
			$error = implode(', ', $messages);
		}
		return false;
	}

	static function get_last_error_code(){
		return self::$last_error_code;
	}
	
	// Fetches and returns the saved Cloudflare Zone ID from the WordPress database
	static function get_zone_id(){

		$options = get_option('speedycache_cdn', []);
		if(!empty($options['cloudflare_zone_id'])){
			return $options['cloudflare_zone_id'];
		}
		return;
	}

	// Checks the rule description and verifies whether it belongs to the SpeedyCache plugin or not.
	static function is_sp_rule($rule){
		if(!isset($rule['description'])){
			return false;
		}
		return strpos($rule['description'], self::$rule_description) !== false;
	}

	// Creates a dynamic rule description by combining the plugin's default description with the website's home URL.
	static function build_rule_description(){
		return self::$rule_description . ' ' . home_url();
	}

	// Compares the existing rule with the new settings to check whether any modifications have been made to the rule or not.
	static function is_rule_modified($existing_rule){

		if(empty($existing_rule)){
			return true;
		}

		$new_rule = self::get_rule_args();
		$fields = ['expression', 'description', 'action', 'enabled'];

		foreach($fields as $field){
			$existing = isset($existing_rule[$field]) ? $existing_rule[$field] : '';
			$new = isset($new_rule[$field]) ? $new_rule[$field] : '';

			if($existing !== $new){
				return true;
			}
		}

		$existing_action = isset($existing_rule['action_parameters']) ? $existing_rule['action_parameters'] : [];
		$new_action = isset($new_rule['action_parameters']) ? $new_rule['action_parameters'] : [];

		if($existing_action != $new_action){
			return true;
		}

		return false;
	}

	// Manages the main workflow for setting up, validating, creating, or updating the Cloudflare cache rule according to the new settings.
	static function setup_cache_rule(&$error){

		self::init_data();
        
		if(empty(self::$options['enabled_cloudflare'])){
			return false; 
		}

		$zone_id = self::get_zone_id();
		if(empty($zone_id)){
			return false;
		}

		$ruleset_id = self::get_ruleset_id($error);
		if(empty($ruleset_id)){

			$ruleset_id = self::create_ruleset($error);
			if(empty($ruleset_id)){
				return false;
			}
		}

		$rule_id = self::get_rule_id();

		if(!empty($rule_id)){
			$existing_rule = self::get_rule();
           
			if(empty($existing_rule)){
				self::clean_local_rule_data();
				$created = self::create_rule($error);
				return empty($created) ? false : $created;
			}

			if (isset($existing_rule['id']) && $existing_rule['id'] !== self::$rule_id) {

				self::$rule_id = $existing_rule['id'];

				$options = get_option('speedycache_cdn', []);
				$options['cloudflare_rule_id'] = self::$rule_id;

				update_option('speedycache_cdn', $options);
				self::$options['cloudflare_rule_id'] = self::$rule_id;
			}

			$is_modified = self::is_rule_modified($existing_rule);

			if(!$is_modified){
				return self::$rule_id;
			}

			$updated = self::update_rule($error);
			return empty($updated) ? false : $updated;
		}

		$created = self::create_rule($error);
		return empty($created) ? false : $created;
	}

	// If the CDN or Cloudflare settings are disabled, this function checks and deletes the existing cache rule.
	static function maybe_rule_delete($cdn_enabled, $cloudflare_enabled){

		self::init_data();

		if(empty(self::$rule_id)){
			return false;
		}

		if(!$cdn_enabled || !$cloudflare_enabled){
			$deleted = self::delete_rule();
			if(!empty($deleted)){
				return true;
			}
		}

		return false;
	}
}
