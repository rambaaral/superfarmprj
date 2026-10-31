<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEO;

// Are we being accessed directly ?
if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

/**
 * Registers SiteSEO abilities with the WordPress 6.9+ Abilities API.
 *
 * Abilities are exposed at /wp-json/wp-abilities/v1/ when the Abilities API is
 * present, and are surfaced as MCP tools when a compatible MCP adapter
 * (e.g. wordpress/mcp-adapter) is installed.
 *
 * Each execute_callback reads or writes real SiteSEO data (post meta, options,
 * robots.txt, sitemap state) following SiteSEO's own storage conventions.
 */
class AbilitiesRegister{

	/**
	 * Register the SiteSEO ability categories.
	 *
	 * @return void
	 */
	static function register_categories(){
		$categories = [
			'siteseo-posts'    => __('SiteSEO — Posts', 'siteseo'),
			'siteseo-settings' => __('SiteSEO — Settings', 'siteseo'),
			'siteseo-audit'    => __('SiteSEO — Audit', 'siteseo'),
			'siteseo-sitemap'  => __('SiteSEO — Sitemap', 'siteseo'),
		];

		foreach($categories as $slug => $label){
			wp_register_ability_category($slug, [
				'label'       => $label,
				'description' => __('SEO management abilities provided by SiteSEO.', 'siteseo'),
			]);
		}
	}

	/**
	 * Register all SiteSEO abilities.
	 *
	 * @return void
	 */
	static function register_abilities(){
		self::register_post_abilities();
		self::register_settings_ability();
		self::register_audit_abilities();
		self::register_sitemap_abilities();
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

	// Whether the XML sitemap module is enabled.
	protected static function is_sitemap_enabled(){
		global $siteseo;

		$toggles = isset($siteseo->setting_enabled) && is_array($siteseo->setting_enabled) ? $siteseo->setting_enabled : get_option('siteseo_toggle', []);

		$sitemap_settings = isset($siteseo->sitemap_settings) && is_array($siteseo->sitemap_settings) ? $siteseo->sitemap_settings : get_option('siteseo_xml_sitemap_option_name', []);
		
		return !empty($toggles['toggle-xml-sitemap']) && !empty($sitemap_settings['xml_sitemap_general_enable']);
	}

	// Read the physical robots.txt file from the site root.
	protected static function get_robots_file_content(){
		if(!defined('ABSPATH')){
			return '';
		}

		$file = ABSPATH . 'robots.txt';

		if(!file_exists($file) || !is_readable($file)){
			return '';
		}

		$content = file_get_contents($file);

		return false === $content ? '' : $content;
	}

	// Parse robots.txt content into a flat list of custom rules.
	protected static function parse_robots_rules($content){
		$rules = [];

		if(!is_string($content) || '' === trim($content)){
			return $rules;
		}

		$lines = preg_split('/\r\n|\r|\n/', $content);
		$user_agents = [];
		$last_was_ua = false;

		foreach($lines as $line){
			$line = trim($line);

			if('' === $line || '#' === $line[0]){
				continue;
			}

			// Strip trailing comments.
			$hash = strpos($line, '#');
			if(false !== $hash){
				$line = trim(substr($line, 0, $hash));
				if('' === $line){
					continue;
				}
			}

			$parts = explode(':', $line, 2);
			if(count($parts) < 2){
				continue;
			}

			$directive = strtolower(trim($parts[0]));
			$value = trim($parts[1]);

			if('user-agent' === $directive){
				// Consecutive user-agent lines share one group; a
				// user-agent line after rules starts a new group.
				if(!$last_was_ua){
					$user_agents = [];
				}

				if('' !== $value && !in_array($value, $user_agents, true)){
					$user_agents[] = $value;
				}

				$last_was_ua = true;
				continue;
			}

			$last_was_ua = false;

			if('' === $value || !in_array($directive, ['allow', 'disallow', 'crawl-delay'], true)){
				continue;
			}

			foreach($user_agents as $agent){
				$rules[] = [
					'user_agent' => $agent,
					'type' => $directive,
					'value' => $value,
				];
			}
		}

		return $rules;
	}

	// =========================================================================
	// Permission callbacks
	// =========================================================================

	/**
	 * @return bool
	 */
	public static function can_edit_posts(){
		return current_user_can('edit_posts');
	}

	/**
	 * @return bool
	 */
	public static function can_manage_options(){
		return current_user_can('manage_options');
	}

	// =========================================================================
	// Posts abilities
	// =========================================================================

	protected static function register_post_abilities(){
		$post_snapshot_schema = [
			'type'       => 'object',
			'properties' => [
				'title'           => ['type' => ['string', 'null']],
				'description'     => ['type' => ['string', 'null']],
				'canonical_url'   => ['type' => ['string', 'null']],
				'focus_keyphrase' => ['type' => ['string', 'null']],
				'seo_score'        => ['type' => ['string', 'null']],
				'robots'          => [
					'type'       => 'object',
					'properties' => [
						'noindex'      => ['type' => 'boolean'],
						'nofollow'     => ['type' => 'boolean'],
						'noimageindex' => ['type' => 'boolean'],
						'noarchive'    => ['type' => 'boolean'],
						'nosnippet'    => ['type' => 'boolean'],
					],
				],
				'social'          => [
					'type'       => 'object',
					'properties' => [
						'og_title'            => ['type' => ['string', 'null']],
						'og_description'      => ['type' => ['string', 'null']],
						'og_image'            => ['type' => ['string', 'null']],
						'twitter_title'       => ['type' => ['string', 'null']],
						'twitter_description' => ['type' => ['string', 'null']],
						'twitter_image'       => ['type' => ['string', 'null']],
					],
				],
			],
		];

		// siteseo-posts/seo-data-get
		wp_register_ability('siteseo-posts/seo-data-get', [
			'label'               => __('Get Post SEO Data', 'siteseo'),
			'description'         => __('Returns the SEO snapshot for a post: title, meta description, focus keyphrase, robots flags, canonical URL and social meta.', 'siteseo'),
			'category'            => 'siteseo-posts',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'postId' => [
						'type'        => 'integer',
						'description' => __('The post ID.', 'siteseo'),
					],
				],
				'required'             => ['postId'],
				'additionalProperties' => false,
			],
			'output_schema'       => $post_snapshot_schema,
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::get_post_seo_data',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_edit_posts',
			'meta'                => self::readonly_meta(),
		]);

		// siteseo-posts/seo-data-update
		wp_register_ability('siteseo-posts/seo-data-update', [
			'label'               => __('Update Post SEO Data', 'siteseo'),
			'description'         => __('Updates SEO fields for a post: title, description, focus keyphrase, robots flags, canonical URL and social meta. Only the fields provided in input are changed; others are preserved.', 'siteseo'),
			'category'            => 'siteseo-posts',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'postId'        => [
						'type'        => 'integer',
						'description' => __('The post ID.', 'siteseo'),
					],
					'title'         => ['type' => ['string', 'null']],
					'description'   => ['type' => ['string', 'null']],
					'canonical_url' => ['type' => ['string', 'null']],
					'focus_keyphrase' => ['type' => ['string', 'null']],
					'robots'        => [
						'type'                 => 'object',
						'properties'           => [
							'noindex'      => ['type' => 'boolean'],
							'nofollow'     => ['type' => 'boolean'],
							'noimageindex' => ['type' => 'boolean'],
							'noarchive'    => ['type' => 'boolean'],
							'nosnippet'    => ['type' => 'boolean'],
						],
						'additionalProperties' => false,
					],
					'social'        => [
						'type'                 => 'object',
						'properties'           => [
							'og_title'            => ['type' => ['string', 'null']],
							'og_description'      => ['type' => ['string', 'null']],
							'og_image'            => ['type' => ['string', 'null']],
							'twitter_title'       => ['type' => ['string', 'null']],
							'twitter_description' => ['type' => ['string', 'null']],
							'twitter_image'       => ['type' => ['string', 'null']],
						],
						'additionalProperties' => false,
					],
				],
				'required'             => ['postId'],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'updated' => ['type' => 'boolean'],
					'post'    => $post_snapshot_schema,
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::update_post_seo_data',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_edit_posts',
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => ['public' => true],
			],
		]);

		// siteseo-posts/list-missing-seo
		wp_register_ability('siteseo-posts/list-missing-seo', [
			'label'               => __('List Posts Missing SEO Data', 'siteseo'),
			'description'         => __('Returns posts where one or more SEO fields are unset (title, description, or focus keyphrase). Useful for "which posts need SEO attention?" prompts.', 'siteseo'),
			'category'            => 'siteseo-posts',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'missing_fields' => [
						'type'    => 'array',
						'items'   => [
							'type' => 'string',
							'enum' => ['title', 'description', 'focus_keyphrase'],
						],
						'default' => ['focus_keyphrase'],
					],
					'post_type'      => [
						'type'  => 'array',
						'items' => ['type' => 'string'],
					],
					'status'         => [
						'type'    => 'array',
						'items'   => ['type' => 'string'],
						'default' => ['publish'],
					],
					'limit'          => [
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					],
					'offset'         => [
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					],
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'posts' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'            => ['type' => 'integer'],
								'post_title'    => ['type' => 'string'],
								'post_type'     => ['type' => 'string'],
								'status'        => ['type' => 'string'],
								'permalink'     => ['type' => ['string', 'null']],
								'missing_fields' => [
									'type'  => 'array',
									'items' => ['type' => 'string'],
								],
							],
						],
					],
					'total' => ['type' => 'integer'],
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::list_posts_missing_seo',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_edit_posts',
			'meta'                => self::readonly_meta(),
		]);

		// siteseo-posts/list-score
		wp_register_ability('siteseo-posts/list-score', [
			'label'               => __('List Posts by SEO Score', 'siteseo'),
			'description'         => __('Returns posts with their cached SEO content-analysis score. Useful for finding the worst (or best) performing content.', 'siteseo'),
			'category'            => 'siteseo-posts',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'post_type' => [
						'type'  => 'array',
						'items' => ['type' => 'string'],
					],
					'status'    => [
						'type'    => 'array',
						'items'   => ['type' => 'string'],
						'default' => ['publish'],
					],
					'limit'     => [
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					],
					'offset'    => [
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					],
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'posts' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'         => ['type' => 'integer'],
								'post_title' => ['type' => 'string'],
								'post_type'  => ['type' => 'string'],
								'status'     => ['type' => 'string'],
								'permalink'  => ['type' => ['string', 'null']],
								'seo_score'  => ['type' => ['string', 'null']],
							],
						],
					],
					'total' => ['type' => 'integer'],
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::list_posts_by_score',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_edit_posts',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Settings ability
	// =========================================================================

	protected static function register_settings_ability(){
		wp_register_ability('siteseo-settings/get', [
			'label'               => __('Get SiteSEO Settings', 'siteseo'),
			'description'         => __('Returns the SiteSEO settings tree (titles, social, advanced, sitemap, analytics). Read-only: settings cannot be modified via abilities — use the SiteSEO admin UI.', 'siteseo'),
			'category'            => 'siteseo-settings',
			'input_schema'        => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'settings' => [
						'type'        => 'object',
						'description' => __('The SiteSEO settings tree. Shape is internal; treat as opaque structured data.', 'siteseo'),
					],
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::get_settings',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}



	// =========================================================================
	// Audit abilities
	// =========================================================================

	protected static function register_audit_abilities(){
		// siteseo-audit/homepage-get
		wp_register_ability('siteseo-audit/homepage-get', [
			'label'               => __('Get Homepage SEO Audit', 'siteseo'),
			'description'         => __('Returns an SEO snapshot of the homepage: title, meta description, robots flags, social meta and a content-analysis score (when cached).', 'siteseo'),
			'category'            => 'siteseo-audit',
			'input_schema'        => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'url'         => ['type' => 'string'],
					'title'       => ['type' => ['string', 'null']],
					'description' => ['type' => ['string', 'null']],
					'seo_score'   => ['type' => ['string', 'null']],
					'robots'      => ['type' => 'string'],
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::get_homepage_audit',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);

		// siteseo-audit/site-get
		wp_register_ability('siteseo-audit/site-get', [
			'label'               => __('Get Site SEO Audit', 'siteseo'),
			'description'         => __('Returns a site-wide SEO health snapshot: homepage score, sitemap on/off, sitemap index URL, custom robots rule count and public post type list.', 'siteseo'),
			'category'            => 'siteseo-audit',
			'input_schema'        => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'sitemap_enabled'           => ['type' => 'boolean'],
					'sitemap_index_url'         => ['type' => ['string', 'null']],
					'custom_robots_rules_count' => ['type' => 'integer'],
					'public_post_types'         => [
						'type'  => 'array',
						'items' => ['type' => 'string'],
					],
					'homepage_url'              => ['type' => 'string'],
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::get_site_audit',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Sitemap abilities
	// =========================================================================

	protected static function register_sitemap_abilities(){
		// siteseo-sitemap/status-get
		wp_register_ability('siteseo-sitemap/status-get', [
			'label'               => __('Get Sitemap Status', 'siteseo'),
			'description'         => __('Returns the SiteSEO sitemap state: whether the XML sitemap is enabled, and the sitemap index URL.', 'siteseo'),
			'category'            => 'siteseo-sitemap',
			'input_schema'        => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'enabled'  => ['type' => 'boolean'],
					'index_url' => ['type' => ['string', 'null']],
				],
			],
			'execute_callback'    => '\SiteSEO\AbilitiesRegister::get_sitemap_status',
			'permission_callback' => '\SiteSEO\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Execute callbacks — Posts
	// =========================================================================

	/**
	 * Build a post SEO snapshot from post meta.
	 *
	 * @param int $post_id
	 * @return array
	 */
	protected static function post_snapshot($post_id){
		$post_id = (int)$post_id;

		return [
			'title'           => get_post_meta($post_id, '_siteseo_titles_title', true) ?: null,
			'description'     => get_post_meta($post_id, '_siteseo_titles_desc', true) ?: null,
			'canonical_url'   => get_post_meta($post_id, '_siteseo_robots_canonical', true) ?: null,
			'focus_keyphrase' => get_post_meta($post_id, '_siteseo_analysis_target_kw', true) ?: null,
			'seo_score'       => self::post_score($post_id),
			'robots'          => [
				'noindex'      => 'yes' === get_post_meta($post_id, '_siteseo_robots_index', true),
				'nofollow'     => 'yes' === get_post_meta($post_id, '_siteseo_robots_follow', true),
				'noimageindex' => 'yes' === get_post_meta($post_id, '_siteseo_robots_imageindex', true),
				'noarchive'    => 'yes' === get_post_meta($post_id, '_siteseo_robots_archive', true),
				'nosnippet'    => 'yes' === get_post_meta($post_id, '_siteseo_robots_snippet', true),
			],
			'social'          => [
				'og_title'            => get_post_meta($post_id, '_siteseo_social_fb_title', true) ?: null,
				'og_description'      => get_post_meta($post_id, '_siteseo_social_fb_desc', true) ?: null,
				'og_image'            => get_post_meta($post_id, '_siteseo_social_fb_img', true) ?: null,
				'twitter_title'       => get_post_meta($post_id, '_siteseo_social_twitter_title', true) ?: null,
				'twitter_description' => get_post_meta($post_id, '_siteseo_social_twitter_desc', true) ?: null,
				'twitter_image'       => get_post_meta($post_id, '_siteseo_social_twitter_img', true) ?: null,
			],
		];
	}

	/**
	 * Read the cached SEO content-analysis score for a post.
	 *
	 * @param int $post_id
	 * @return string|null
	 */
	protected static function post_score($post_id){
		$api = get_post_meta($post_id, '_siteseo_content_analysis_api', true);
		if(is_array($api) && isset($api['score'])){
			return (string)$api['score'];
		}

		$data = get_post_meta($post_id, '_siteseo_analysis_data', true);
		if(is_array($data) && isset($data[0]['score'])){
			return $data[0]['score'] === 1 ? 'good' : 'bad';
		}

		return null;
	}

	/**
	 * Execute callback for siteseo-posts/seo-data-get.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function get_post_seo_data($input){
		$post_id = isset($input['postId']) ? (int)$input['postId'] : 0;

		if(!$post_id || !get_post($post_id)){
			return new \WP_Error('invalid_post', __('Post not found.', 'siteseo'));
		}

		return self::post_snapshot($post_id);
	}

	/**
	 * Execute callback for siteseo-posts/seo-data-update.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function update_post_seo_data($input){
		$input  = is_array($input) ? $input : [];
		$post_id = isset($input['postId']) ? (int)$input['postId'] : 0;

		if(!$post_id || !get_post($post_id)){
			return new \WP_Error('invalid_post', __('Post not found.', 'siteseo'));
		}

		if(!current_user_can('edit_post', $post_id)){
			return new \WP_Error('forbidden', __('You cannot edit this post.', 'siteseo'));
		}

		$meta_map = [
			'title'           => '_siteseo_titles_title',
			'description'     => '_siteseo_titles_desc',
			'canonical_url'   => '_siteseo_robots_canonical',
			'focus_keyphrase' => '_siteseo_analysis_target_kw',
		];

		foreach($meta_map as $field => $meta_key){
			if(array_key_exists($field, $input)){
				$value = $input[$field];
				if(null === $value){
					delete_post_meta($post_id, $meta_key);
				}else{
					update_post_meta($post_id, $meta_key, sanitize_text_field($value));
				}
			}
		}

		$robots_map = [
			'noindex'      => '_siteseo_robots_index',
			'nofollow'     => '_siteseo_robots_follow',
			'noimageindex' => '_siteseo_robots_imageindex',
			'noarchive'    => '_siteseo_robots_archive',
			'nosnippet'    => '_siteseo_robots_snippet',
		];

		if(isset($input['robots']) && is_array($input['robots'])){
			foreach($robots_map as $flag => $meta_key){
				if(array_key_exists($flag, $input['robots'])){
					if($input['robots'][$flag]){
						update_post_meta($post_id, $meta_key, 'yes');
					}else{
						delete_post_meta($post_id, $meta_key);
					}
				}
			}
		}

		$social_map = [
			'og_title'            => '_siteseo_social_fb_title',
			'og_description'      => '_siteseo_social_fb_desc',
			'og_image'            => '_siteseo_social_fb_img',
			'twitter_title'       => '_siteseo_social_twitter_title',
			'twitter_description' => '_siteseo_social_twitter_desc',
			'twitter_image'       => '_siteseo_social_twitter_img',
		];

		if(isset($input['social']) && is_array($input['social'])){
			foreach($social_map as $field => $meta_key){
				if(array_key_exists($field, $input['social'])){
					$value = $input['social'][$field];
					if(null === $value){
						delete_post_meta($post_id, $meta_key);
					}else{
						update_post_meta($post_id, $meta_key, sanitize_text_field($value));
					}
				}
			}
		}

		return [
			'updated' => true,
			'post'    => self::post_snapshot($post_id),
		];
	}

	/**
	 * Execute callback for siteseo-posts/list-missing-seo.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function list_posts_missing_seo($input){
		$input = is_array($input) ? $input : [];

		$missing_fields = isset($input['missing_fields']) && is_array($input['missing_fields']) ? $input['missing_fields'] : ['focus_keyphrase'];
		$post_types     = isset($input['post_type']) && is_array($input['post_type']) ? $input['post_type'] : array_values(get_post_types(['public' => true]));
		$statuses       = isset($input['status']) && is_array($input['status']) ? $input['status'] : ['publish'];
		$limit           = isset($input['limit']) ? max(1, min(100, (int)$input['limit'])) : 20;
		$offset          = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;

		$meta_map = [
			'title'           => '_siteseo_titles_title',
			'description'     => '_siteseo_titles_desc',
			'focus_keyphrase' => '_siteseo_analysis_target_kw',
		];

		$query = new \WP_Query([
			'post_type'      => $post_types,
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		]);

		$posts = [];
		$total = $query->found_posts;

		foreach($query->posts as $post_id){
			$post_missing = [];
			foreach($missing_fields as $field){
				if(!isset($meta_map[$field])){
					continue;
				}
				$value = get_post_meta($post_id, $meta_map[$field], true);
				if('' === $value || null === $value){
					$post_missing[] = $field;
				}
			}

			if(!empty($post_missing)){
				$post = get_post($post_id);
				$posts[] = [
					'id'             => $post_id,
					'post_title'     => $post ? $post->post_title : '',
					'post_type'      => $post ? $post->post_type : '',
					'status'         => $post ? $post->post_status : '',
					'permalink'      => get_permalink($post_id) ?: null,
					'missing_fields' => $post_missing,
				];
			}
		}

		return [
			'posts' => $posts,
			'total' => $total,
		];
	}

	/**
	 * Execute callback for siteseo-posts/list-score.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function list_posts_by_score($input){
		$input = is_array($input) ? $input : [];

		$post_types = isset($input['post_type']) && is_array($input['post_type']) ? $input['post_type'] : array_values(get_post_types(['public' => true]));
		$statuses   = isset($input['status']) && is_array($input['status']) ? $input['status'] : ['publish'];
		$limit       = isset($input['limit']) ? max(1, min(100, (int)$input['limit'])) : 20;
		$offset      = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;

		$query = new \WP_Query([
			'post_type'      => $post_types,
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		]);

		$posts = [];
		foreach($query->posts as $post_id){
			$post = get_post($post_id);
			$posts[] = [
				'id'         => $post_id,
				'post_title' => $post ? $post->post_title : '',
				'post_type'  => $post ? $post->post_type : '',
				'status'     => $post ? $post->post_status : '',
				'permalink'  => get_permalink($post_id) ?: null,
				'seo_score'  => self::post_score($post_id),
			];
		}

		return [
			'posts' => $posts,
			'total' => $query->found_posts,
		];
	}

	// =========================================================================
	// Execute callbacks — Settings
	// =========================================================================

	/**
	 * Execute callback for siteseo-settings/get.
	 *
	 * @return array
	 */
	public static function get_settings(){
		return [
			'settings' => [
				'titles'    => get_option('siteseo_titles_option_name', []),
				'social'    => get_option('siteseo_social_option_name', []),
				'advanced'  => get_option('siteseo_advanced_option_name', []),
				'sitemap'   => get_option('siteseo_xml_sitemap_option_name', []),
				'analytics' => get_option('siteseo_google_analytics_option_name', []),
				'toggles'   => get_option('siteseo_toggle', []),
			],
		];
	}



	// =========================================================================
	// Execute callbacks — Audit
	// =========================================================================

	/**
	 * Execute callback for siteseo-audit/homepage-get.
	 *
	 * @return array
	 */
	public static function get_homepage_audit(){
		$home_id = (int)get_option('page_on_front');

		$snapshot = [
			'url'         => home_url('/'),
			'title'       => null,
			'description' => null,
			'seo_score'   => null,
			'robots'      => '',
		];

		if($home_id){
			$snapshot['title']       = get_post_meta($home_id, '_siteseo_titles_title', true) ?: null;
			$snapshot['description'] = get_post_meta($home_id, '_siteseo_titles_desc', true) ?: null;
			$snapshot['seo_score']   = self::post_score($home_id);

			$robots = [];
			if('yes' === get_post_meta($home_id, '_siteseo_robots_index', true)){
				$robots[] = 'noindex';
			}
			if('yes' === get_post_meta($home_id, '_siteseo_robots_follow', true)){
				$robots[] = 'nofollow';
			}
			$snapshot['robots'] = implode(', ', $robots);
		}

		return $snapshot;
	}

	/**
	 * Execute callback for siteseo-audit/site-get.
	 *
	 * @return array
	 */
	public static function get_site_audit(){

		$sitemap_enabled = self::is_sitemap_enabled();

		$sitemap_index_url = $sitemap_enabled ? home_url('/sitemaps.xml') : null;

		$rules = self::parse_robots_rules(self::get_robots_file_content());

		$post_types = array_values(get_post_types(['public' => true]));

		return [
			'sitemap_enabled'           => $sitemap_enabled,
			'sitemap_index_url'         => $sitemap_index_url,
			'custom_robots_rules_count' => count($rules),
			'public_post_types'         => $post_types,
			'homepage_url'              => home_url('/'),
		];
	}

	// =========================================================================
	// Execute callbacks — Sitemap
	// =========================================================================

	/**
	 * Execute callback for siteseo-sitemap/status-get.
	 *
	 * @return array
	 */
	public static function get_sitemap_status(){

		$enabled = self::is_sitemap_enabled();

		$index_url = $enabled ? home_url('/sitemaps.xml') : null;

		return [
			'enabled'   => $enabled,
			'index_url' => $index_url,
		];
	}
}
