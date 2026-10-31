<?php
/*
* SiteSEO PRO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

// Are we being accessed directly ?
if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

/**
 * Registers SiteSEO Pro abilities with the WordPress 6.9+ Abilities API.
 */
class AbilitiesRegister{

	/**
	 * Register the SiteSEO Pro ability categories.
	 *
	 * @return void
	 */
	static function register_categories(){
		$categories = [
			'siteseo-robots' => __('SiteSEO — Robots.txt', 'siteseo-pro'),
		];

		foreach($categories as $slug => $label){
			wp_register_ability_category($slug, [
				'label'       => $label,
				'description' => __('SEO management abilities provided by SiteSEO.', 'siteseo-pro'),
			]);
		}
	}

	/**
	 * Register all SiteSEO Pro abilities.
	 *
	 * @return void
	 */
	static function register_abilities(){
		self::register_robots_abilities();
	}

	/**
	 * Shared meta block for read-only abilities.
	 *
	 * @return array
	 */
	protected static function readonly_meta(){
		return [
			'annotations'  => ['readonly' => true],
			'show_in_rest' => true,
			'mcp'          => ['public' => true],
		];
	}

	/**
	 * Input schema for abilities that take no input.
	 *
	 * @return array
	 */
	protected static function no_input_schema(){
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'default'              => [],
		];
	}

	// =========================================================================
	// Permission callbacks
	// =========================================================================

	/**
	 * @return bool
	 */
	public static function can_manage_options(){
		return current_user_can('manage_options');
	}

	// =========================================================================
	// Robots abilities
	// =========================================================================

	protected static function robots_rule_schema(){
		return [
			'type'       => 'object',
			'properties' => [
				'user_agent'  => ['type' => 'string'],
				'directive'   => [
					'type' => 'string',
					'enum' => ['allow', 'disallow'],
				],
				'field_value' => ['type' => 'string'],
			],
		];
	}

	protected static function register_robots_abilities(){
		// siteseo-robots/output-get
		wp_register_ability('siteseo-robots/output-get', [
			'label'               => __('Get Robots.txt Output', 'siteseo-pro'),
			'description'         => __('Returns the active robots.txt content that SiteSEO is serving for this site, including custom rules and WordPress defaults.', 'siteseo-pro'),
			'category'            => 'siteseo-robots',
			'input_schema'        => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => ['content' => ['type' => 'string']],
			],
			'execute_callback'    => '\SiteSEOPro\AbilitiesRegister::get_robots_output',
			'permission_callback' => '\SiteSEOPro\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);

		// siteseo-robots/rules-list
		wp_register_ability('siteseo-robots/rules-list', [
			'label'               => __('List Robots.txt Rules', 'siteseo-pro'),
			'description'         => __('Lists the parsed custom robots.txt rules (User-agent / Directive / Path) from the physical robots.txt file.', 'siteseo-pro'),
			'category'            => 'siteseo-robots',
			'input_schema'        => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'rules' => [
						'type'  => 'array',
						'items' => self::robots_rule_schema(),
					],
				],
			],
			'execute_callback'    => '\SiteSEOPro\AbilitiesRegister::list_robots_rules',
			'permission_callback' => '\SiteSEOPro\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);

		// siteseo-robots/rules-add
		wp_register_ability('siteseo-robots/rules-add', [
			'label'               => __('Add Robots.txt Rule', 'siteseo-pro'),
			'description'         => __('Adds a new custom robots.txt rule for a given user agent. Directive must be "allow" or "disallow".', 'siteseo-pro'),
			'category'            => 'siteseo-robots',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'user_agent'  => [
						'type'        => 'string',
						'description' => __('User-agent the rule applies to (e.g. "*", "Googlebot").', 'siteseo-pro'),
					],
					'directive'   => [
						'type' => 'string',
						'enum' => ['allow', 'disallow'],
					],
					'field_value' => [
						'type'        => 'string',
						'description' => __('Path or pattern the directive applies to (e.g. "/private/").', 'siteseo-pro'),
					],
				],
				'required'             => ['user_agent', 'directive', 'field_value'],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => ['rule' => self::robots_rule_schema()],
			],
			'execute_callback'    => '\SiteSEOPro\AbilitiesRegister::add_robots_rule',
			'permission_callback' => '\SiteSEOPro\AbilitiesRegister::can_manage_options',
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => ['public' => true],
			],
		]);

		// siteseo-robots/rules-delete
		wp_register_ability('siteseo-robots/rules-delete', [
			'label'               => __('Delete Robots.txt Rule', 'siteseo-pro'),
			'description'         => __('Deletes a custom robots.txt rule (a User-agent / Directive / Path line) from the physical robots.txt file.', 'siteseo-pro'),
			'category'            => 'siteseo-robots',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'user_agent'  => ['type' => 'string'],
					'directive'   => [
						'type' => 'string',
						'enum' => ['allow', 'disallow'],
					],
					'field_value' => ['type' => 'string'],
				],
				'required'             => ['user_agent', 'directive', 'field_value'],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => ['deleted' => ['type' => 'boolean']],
			],
			'execute_callback'    => '\SiteSEOPro\AbilitiesRegister::delete_robots_rule',
			'permission_callback' => '\SiteSEOPro\AbilitiesRegister::can_manage_options',
			'meta'                => [
				'annotations'  => ['destructive' => true],
				'show_in_rest' => true,
				'mcp'          => ['public' => true],
			],
		]);
	}

	// =========================================================================
	// Execute callbacks — Robots
	// =========================================================================

	/**
	 * Get the contents of the physical robots.txt file (falls back to WP's
	 * virtual output when the file is absent).
	 *
	 * @return array
	 */
	protected static function get_robots_file_content(){
		$path = ABSPATH . 'robots.txt';

		if(file_exists($path) && is_readable($path)){
			$content = file_get_contents($path);
			if(false !== $content){
				return $content;
			}
		}

		// Virtual fallback (same mechanism SiteSEO uses internally).
		if(function_exists('do_robots')){
			ob_start();
			do_robots();
			return (string)ob_get_clean();
		}

		return '';
	}

	/**
	 * Parse the robots.txt content into structured rules.
	 *
	 * @param string $content
	 * @return array
	 */
	protected static function parse_robots_rules($content){
		$rules  = [];
		$agents = ['*'];

		$lines = preg_split('/\r\n|\r|\n/', (string)$content);
		foreach($lines as $line){
			$line = trim($line);
			if('' === $line || '#' === substr($line, 0, 1)){
				continue;
			}

			if(preg_match('/^User-agent:\s*(.+)$/i', $line, $m)){
				$agents = [trim($m[1])];
				continue;
			}

			if(preg_match('/^(Allow|Disallow):\s*(.*)$/i', $line, $m)){
				$directive = strtolower($m[1]);
				$path      = trim($m[2]);
				foreach($agents as $agent){
					$rules[] = [
						'user_agent'  => $agent,
						'directive'   => $directive,
						'field_value' => $path,
					];
				}
			}
		}

		return $rules;
	}

	/**
	 * Execute callback for siteseo-robots/output-get.
	 *
	 * @return array
	 */
	public static function get_robots_output(){
		return ['content' => self::get_robots_file_content()];
	}

	/**
	 * Execute callback for siteseo-robots/rules-list.
	 *
	 * @return array
	 */
	public static function list_robots_rules(){
		return ['rules' => self::parse_robots_rules(self::get_robots_file_content())];
	}

	/**
	 * Execute callback for siteseo-robots/rules-add.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function add_robots_rule($input){
		$input = is_array($input) ? $input : [];

		$user_agent  = isset($input['user_agent']) ? sanitize_text_field($input['user_agent']) : '';
		$directive   = isset($input['directive']) ? strtolower(sanitize_text_field($input['directive'])) : '';
		$field_value = isset($input['field_value']) ? sanitize_text_field($input['field_value']) : '';

		if('' === $user_agent || !in_array($directive, ['allow', 'disallow'], true) || '' === $field_value){
			return new \WP_Error('invalid_input', __('user_agent, directive (allow|disallow) and field_value are required.', 'siteseo-pro'));
		}

		$path  = ABSPATH . 'robots.txt';
		$line  = ucfirst($directive) . ': ' . $field_value . "\n";

		if(file_exists($path) && is_writable($path)){
			$existing = file_get_contents($path);
			if(false !== $existing && stripos($existing, 'User-agent: ' . $user_agent) !== false){
				// Insert after the matching User-agent block.
				$existing = preg_replace_callback(
					'/(User-agent:\s*' . preg_quote($user_agent, '/') . '[^\n]*\n)((?:[^\n]*\n)*?)(?=(?:User-agent:|$))/i',
					function($m) use ($line){
						return $m[1] . $m[2] . $line;
					},
					$existing,
					1
				);
				file_put_contents($path, $existing);
			}else{
				file_put_contents($path, "\nUser-agent: " . $user_agent . "\n" . $line, FILE_APPEND);
			}
		}else{
			// Create the file with the new rule.
			file_put_contents($path, "User-agent: " . $user_agent . "\n" . $line);
		}

		return [
			'rule' => [
				'user_agent'  => $user_agent,
				'directive'   => $directive,
				'field_value' => $field_value,
			],
		];
	}

	/**
	 * Execute callback for siteseo-robots/rules-delete.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function delete_robots_rule($input){
		$input = is_array($input) ? $input : [];

		$user_agent  = isset($input['user_agent']) ? sanitize_text_field($input['user_agent']) : '';
		$directive   = isset($input['directive']) ? strtolower(sanitize_text_field($input['directive'])) : '';
		$field_value = isset($input['field_value']) ? sanitize_text_field($input['field_value']) : '';

		if('' === $user_agent || !in_array($directive, ['allow', 'disallow'], true) || '' === $field_value){
			return new \WP_Error('invalid_input', __('user_agent, directive (allow|disallow) and field_value are required.', 'siteseo-pro'));
		}

		$path = ABSPATH . 'robots.txt';
		if(!file_exists($path) || !is_writable($path)){
			return new \WP_Error('no_file', __('robots.txt file does not exist or is not writable.', 'siteseo-pro'));
		}

		$existing = file_get_contents($path);
		$target    = ucfirst($directive) . ': ' . $field_value;
		$lines     = preg_split('/\r\n|\r|\n/', $existing);
		$deleted   = false;
		$agent_seen = false;

		foreach($lines as $i => $line){
			$trimmed = trim($line);
			if(preg_match('/^User-agent:\s*' . preg_quote($user_agent, '/') . '/i', $trimmed)){
				$agent_seen = true;
				continue;
			}
			if($agent_seen && 0 === strcasecmp($trimmed, $target)){
				unset($lines[$i]);
				$deleted = true;
				break;
			}
			if($agent_seen && preg_match('/^User-agent:\s*/i', $trimmed)){
				$agent_seen = false;
			}
		}

		if($deleted){
			file_put_contents($path, implode("\n", $lines));
		}

		return ['deleted' => $deleted];
	}
}
