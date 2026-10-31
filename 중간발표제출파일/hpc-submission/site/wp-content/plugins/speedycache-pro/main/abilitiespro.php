<?php
/*
* SpeedyCache Pro
* https://speedycache.com
* (c) Softaculous Team
*/

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

class AbilitiesPro{

	/**
	 * Register the SpeedyCache Pro ability categories.
	 *
	 * @return void
	 */
	static function register_categories(){
		$categories = [
			'speedycache-db' => __('SpeedyCache — Database', 'speedycache'),
			'speedycache-logs' => __('SpeedyCache — Cache Logs', 'speedycache'),
			'speedycache-stats' => __('SpeedyCache — Statistics', 'speedycache'),
			'speedycache-image' => __('SpeedyCache — Image Optimization', 'speedycache'),
			'speedycache-object' => __('SpeedyCache — Object Cache', 'speedycache'),
			'speedycache-bloat' => __('SpeedyCache — Bloat', 'speedycache'),
			'speedycache-license' => __('SpeedyCache — License', 'speedycache'),
		];

		foreach($categories as $slug => $label){
			wp_register_ability_category($slug, [
				'label' => $label,
				'description' => __('Pro cache and performance optimization abilities provided by SpeedyCache Pro.', 'speedycache'),
			]);
		}
	}

	/**
	 * Register all Pro SpeedyCache abilities.
	 *
	 * @return void
	 */
	static function register_abilities(){
		self::register_db_abilities();
		self::register_logs_abilities();
		self::register_stats_abilities();
		self::register_image_abilities();
		self::register_object_abilities();
		self::register_bloat_abilities();
		self::register_license_abilities();
	}

	// =========================================================================
	// Shared helpers
	// =========================================================================

	/**
	 * Shared meta block for read-only abilities.
	 *
	 * @return array
	 */
	protected static function readonly_meta(){
		return [
			'annotations' => ['readonly' => true],
			'show_in_rest' => true,
			'mcp' => ['public' => true],
		];
	}

	/**
	 * Input schema for abilities that take no input.
	 *
	 * @return array
	 */
	protected static function no_input_schema(){
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'default' => [],
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
	// Database optimization abilities
	// =========================================================================

	protected static function register_db_abilities(){
		// speedycache-db/optimize
		wp_register_ability('speedycache-db/optimize', [
			'label' => __('Optimize the Database', 'speedycache'),
			'description' => __('Runs a SpeedyCache Pro database cleanup of the given type: post_revisions, trashed_contents, trashed_spam_comments, trackback_pingback, transient_options, expired_transient or all_warnings. Useful for "clean up my database" prompts.', 'speedycache'),
			'category' => 'speedycache-db',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'type' => [
						'type' => 'string',
						'description' => __('The database cleanup type.', 'speedycache'),
						'enum' => [
							'post_revisions',
							'trashed_contents',
							'trashed_spam_comments',
							'trackback_pingback',
							'transient_options',
							'expired_transient',
							'all_warnings',
						],
					],
				],
				'required' => ['type'],
				'additionalProperties' => false,
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'optimized' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::optimize_db',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);

		// speedycache-db/get-settings
		wp_register_ability('speedycache-db/get-settings', [
			'label' => __('Get Database Optimization Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro automatic database optimization settings: which cleanup types run on cron, the cron schedule and whether auto-optimization is enabled. Read-only.', 'speedycache'),
			'category' => 'speedycache-db',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'db_post_revisions' => ['type' => 'boolean'],
					'db_trashed_contents' => ['type' => 'boolean'],
					'db_trashed_spam_comments' => ['type' => 'boolean'],
					'db_trackbacks_pingback' => ['type' => 'boolean'],
					'db_transient_options' => ['type' => 'boolean'],
					'db_expired_transient' => ['type' => 'boolean'],
					'db_auto_optm_interval' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_db_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Cache Logs abilities
	// =========================================================================

	protected static function register_logs_abilities(){
		// speedycache-logs/list
		wp_register_ability('speedycache-logs/list', [
			'label' => __('List Cache Delete Logs', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro cache deletion log entries: date and the action that triggered the deletion. Useful for "when was the cache last cleared?" prompts. Read-only.', 'speedycache'),
			'category' => 'speedycache-logs',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'limit' => [
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 25,
					],
				],
				'additionalProperties' => false,
				'default' => [],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'logs' => [
						'type' => 'array',
						'items' => [
							'type' => 'object',
							'properties' => [
								'date' => ['type' => ['string', 'null']],
								'via' => ['type' => ['string', 'null']],
							],
						],
					],
					'total' => ['type' => 'integer'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::list_logs',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Statistics abilities
	// =========================================================================

	protected static function register_stats_abilities(){
		// speedycache-stats/get
		wp_register_ability('speedycache-stats/get', [
			'label' => __('Get Cache Statistics', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro detailed cache statistics: desktop, mobile, minified CSS and minified JS — file count and size for each. Read-only.', 'speedycache'),
			'category' => 'speedycache-stats',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'desktop' => [
						'type' => 'object',
						'properties' => [
							'size' => ['type' => 'number'],
							'files' => ['type' => 'integer'],
						],
					],
					'mobile' => [
						'type' => 'object',
						'properties' => [
							'size' => ['type' => 'number'],
							'files' => ['type' => 'integer'],
						],
					],
					'css' => [
						'type' => 'object',
						'properties' => [
							'size' => ['type' => 'number'],
							'files' => ['type' => 'integer'],
						],
					],
					'js' => [
						'type' => 'object',
						'properties' => [
							'size' => ['type' => 'number'],
							'files' => ['type' => 'integer'],
						],
					],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_stats',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Image Optimization abilities
	// =========================================================================

	protected static function register_image_abilities(){
		// speedycache-image/get-stats
		wp_register_ability('speedycache-image/get-stats', [
			'label' => __('Get Image Optimization Stats', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro image optimization statistics: total images, optimized count, error count, uncompressed count and total size reduction. Read-only.', 'speedycache'),
			'category' => 'speedycache-image',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'total_images' => ['type' => 'integer'],
					'optimized' => ['type' => 'integer'],
					'errors' => ['type' => 'integer'],
					'uncompressed' => ['type' => 'integer'],
					'total_reduction_kb' => ['type' => 'number'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_image_stats',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);

		// speedycache-image/get-settings
		wp_register_ability('speedycache-image/get-settings', [
			'label' => __('Get Image Optimization Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro image optimization settings: compression quality, WebP conversion, auto-optimization and lazy load toggles. Read-only.', 'speedycache'),
			'category' => 'speedycache-image',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'quality' => ['type' => ['integer', 'null']],
					'convert_webp' => ['type' => 'boolean'],
					'automatic_optm' => ['type' => 'boolean'],
					'lazy_load' => ['type' => 'boolean'],
					'resize' => ['type' => 'boolean'],
					'url_rewrite' => ['type' => 'boolean'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_image_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);

		// speedycache-image/revert-all
		wp_register_ability('speedycache-image/revert-all', [
			'label' => __('Revert All Image Optimizations', 'speedycache'),
			'description' => __('Reverts all SpeedyCache Pro image optimizations, restoring the original images from the backup folder. Useful for "undo image optimization" prompts.', 'speedycache'),
			'category' => 'speedycache-image',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'reverted' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::revert_all_images',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);
	}

	// =========================================================================
	// Object Cache abilities
	// =========================================================================

	protected static function register_object_abilities(){
		// speedycache-object/get-settings
		wp_register_ability('speedycache-object/get-settings', [
			'label' => __('Get Object Cache Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro object cache settings: enable flag, host, port, TTL, persistence, serialization, compression and admin caching. Connection credentials are not exposed. Read-only.', 'speedycache'),
			'category' => 'speedycache-object',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'enabled' => ['type' => 'boolean'],
					'host' => ['type' => ['string', 'null']],
					'port' => ['type' => ['integer', 'null']],
					'ttl' => ['type' => ['integer', 'null']],
					'persistent' => ['type' => 'boolean'],
					'async_flush' => ['type' => 'boolean'],
					'serialization' => ['type' => ['string', 'null']],
					'compress' => ['type' => ['string', 'null']],
					'admin' => ['type' => 'boolean'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_object_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);

		// speedycache-object/flush
		wp_register_ability('speedycache-object/flush', [
			'label' => __('Flush the Object Cache', 'speedycache'),
			'description' => __('Flushes the SpeedyCache Pro Redis object cache. Useful for "clear my object cache" prompts.', 'speedycache'),
			'category' => 'speedycache-object',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'flushed' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::flush_object_cache',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);

		// speedycache-object/status
		wp_register_ability('speedycache-object/status', [
			'label' => __('Get Object Cache Status', 'speedycache'),
			'description' => __('Returns whether the SpeedyCache Pro object cache is enabled, whether Redis is reachable and the Redis memory usage. Read-only.', 'speedycache'),
			'category' => 'speedycache-object',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'enabled' => ['type' => 'boolean'],
					'connected' => ['type' => 'boolean'],
					'memory' => ['type' => ['string', 'null']],
					'error' => ['type' => ['string', 'null']],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::object_cache_status',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Bloat abilities (read-only)
	// =========================================================================

	protected static function register_bloat_abilities(){
		// speedycache-bloat/get
		wp_register_ability('speedycache-bloat/get', [
			'label' => __('Get Bloat Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro bloat reduction settings: disable XML-RPC, disable dashicons, remove jQuery Migrate, disable oEmbeds, disable block editor CSS, limit post revisions, heartbeat control, disable cart fragments, disable WooCommerce assets, disable WP feeds and disable Gutenberg. Read-only.', 'speedycache'),
			'category' => 'speedycache-bloat',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'disable_xmlrpc' => ['type' => 'boolean'],
					'remove_gfonts' => ['type' => 'boolean'],
					'disable_dashicons' => ['type' => 'boolean'],
					'remove_jquery_migrate' => ['type' => 'boolean'],
					'disable_oembeds' => ['type' => 'boolean'],
					'disable_block_editor_css' => ['type' => 'boolean'],
					'limit_post_revisions' => ['type' => ['integer', 'boolean']],
					'disable_heartbeat' => ['type' => ['string', 'boolean']],
					'disable_cart_fragment' => ['type' => 'boolean'],
					'disable_woocommerce_assets' => ['type' => 'boolean'],
					'disable_wp_feeds' => ['type' => 'boolean'],
					'disable_gutenberg' => ['type' => 'boolean'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_bloat_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// License abilities (read-only)
	// =========================================================================

	protected static function register_license_abilities(){
		// speedycache-license/get
		wp_register_ability('speedycache-license/get', [
			'label' => __('Get License Status', 'speedycache'),
			'description' => __('Returns the SpeedyCache Pro license status: whether the license is active, the license key (truncated), the expiry date and the Pro version. Read-only.', 'speedycache'),
			'category' => 'speedycache-license',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'active' => ['type' => 'boolean'],
					'license' => ['type' => ['string', 'null']],
					'expires' => ['type' => ['string', 'null']],
					'version' => ['type' => ['string', 'null']],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesPro::get_license',
			'permission_callback' => '\SpeedyCache\AbilitiesPro::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Execute callbacks — Database
	// =========================================================================

	/**
	 * Execute callback for speedycache-db/optimize.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function optimize_db($input){
		$input = is_array($input) ? $input : [];
		$type = isset($input['type']) ? sanitize_text_field($input['type']) : '';

		if(empty($type)){
			return new \WP_Error('invalid_type', __('A valid database cleanup type is required.', 'speedycache'));
		}

		if(!class_exists('\SpeedyCache\DB')){
			return new \WP_Error('db_unavailable', __('Database optimization is not available. SpeedyCache Pro is not active.', 'speedycache'));
		}

		$result = DB::optimize_db($type);

		if($result){
			return [
				'optimized' => true,
				'message' => sprintf(__('Database cleanup "%s" completed.', 'speedycache'), $type),
			];
		}

		return [
			'optimized' => false,
			'message' => sprintf(__('Database cleanup "%s" did not run or had nothing to clean.', 'speedycache'), $type),
		];
	}

	/**
	 * Execute callback for speedycache-db/get-settings.
	 *
	 * @return array
	 */
	public static function get_db_settings(){
		$options = get_option('speedycache_options', []);

		return [
			'db_post_revisions' => !empty($options['db_post_revisions']),
			'db_trashed_contents' => !empty($options['db_trashed_contents']),
			'db_trashed_spam_comments' => !empty($options['db_trashed_spam_comments']),
			'db_trackbacks_pingback' => !empty($options['db_trackbacks_pingback']),
			'db_transient_options' => !empty($options['db_transient_options']),
			'db_expired_transient' => !empty($options['db_expired_transient']),
			'db_auto_optm_interval' => isset($options['db_auto_optm_interval']) ? $options['db_auto_optm_interval'] : '',
		];
	}

	/**
	 * Execute callback for speedycache-logs/list.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function list_logs($input){
		$input = is_array($input) ? $input : [];
		$limit = isset($input['limit']) ? max(1, min(100, (int)$input['limit'])) : 25;

		if(!class_exists('\SpeedyCache\Logs')){
			return new \WP_Error('logs_unavailable', __('Cache logs are not available. SpeedyCache Pro is not active.', 'speedycache'));
		}

		$logs = get_option('speedycache_delete_cache_logs', []);

		if(!empty($logs) && is_array($logs)){
			$logs = array_slice($logs, 0, $limit);
		}

		$list = [];
		if(!empty($logs) && is_array($logs)){
			foreach($logs as $log){
				$via = '';
				if(!empty($log['via']) && is_array($log['via'])){
					$via = Logs::decode_via($log['via']);
				}

				$list[] = [
					'date' => isset($log['date']) ? $log['date'] : null,
					'via' => $via,
				];
			}
		}

		return [
			'logs' => $list,
			'total' => count($list),
		];
	}

	/**
	 * Execute callback for speedycache-stats/get.
	 *
	 * @return array
	 */
	public static function get_stats(){
		if(class_exists('\SpeedyCache\Statistics')){
			$stats = Statistics::get();
		}else{
			$stats = [
				'desktop' => ['size' => 0, 'file' => 0],
				'mobile' => ['size' => 0, 'file' => 0],
				'css' => ['size' => 0, 'file' => 0],
				'js' => ['size' => 0, 'file' => 0],
			];
		}

		return [
			'desktop' => [
				'size' => isset($stats['desktop']['size']) ? (float)$stats['desktop']['size'] : 0,
				'files' => isset($stats['desktop']['file']) ? (int)$stats['desktop']['file'] : 0,
			],
			'mobile' => [
				'size' => isset($stats['mobile']['size']) ? (float)$stats['mobile']['size'] : 0,
				'files' => isset($stats['mobile']['file']) ? (int)$stats['mobile']['file'] : 0,
			],
			'css' => [
				'size' => isset($stats['css']['size']) ? (float)$stats['css']['size'] : 0,
				'files' => isset($stats['css']['file']) ? (int)$stats['css']['file'] : 0,
			],
			'js' => [
				'size' => isset($stats['js']['size']) ? (float)$stats['js']['size'] : 0,
				'files' => isset($stats['js']['file']) ? (int)$stats['js']['file'] : 0,
			],
		];
	}

	/**
	 * Execute callback for speedycache-image/get-stats.
	 *
	 * @return array
	 */
	public static function get_image_stats(){
		if(!class_exists('\SpeedyCache\Image')){
			return [
				'total_images' => 0,
				'optimized' => 0,
				'errors' => 0,
				'uncompressed' => 0,
				'total_reduction_kb' => 0,
			];
		}

		$total_reduction = Image::total_reduction();
		$optimized = Image::optimized_file_count();
		$errors = Image::error_count();
		$uncompressed = Image::uncompressed_count();

		return [
			'total_images' => ($optimized + $uncompressed + $errors),
			'optimized' => (int)$optimized,
			'errors' => (int)$errors,
			'uncompressed' => (int)$uncompressed,
			'total_reduction_kb' => round((float)$total_reduction / 1000, 2),
		];
	}

	/**
	 * Execute callback for speedycache-image/get-settings.
	 *
	 * @return array
	 */
	public static function get_image_settings(){
		$settings = get_option('speedycache_img', []);

		return [
			'quality' => isset($settings['quality']) ? (int)$settings['quality'] : null,
			'convert_webp' => !empty($settings['convert_webp']),
			'automatic_optm' => !empty($settings['automatic_optm']),
			'lazy_load' => !empty($settings['lazy_load']),
			'resize' => !empty($settings['resize']),
			'url_rewrite' => !empty($settings['url_rewrite']),
		];
	}

	/**
	 * Execute callback for speedycache-image/revert-all.
	 *
	 * @return array
	 */
	public static function revert_all_images(){
		if(!class_exists('\SpeedyCache\Image')){
			return [
				'reverted' => false,
				'message' => __('Image optimization is not available. SpeedyCache Pro is not active.', 'speedycache'),
			];
		}

		Image::revert_all();

		return [
			'reverted' => true,
			'message' => __('All image optimizations have been reverted to the originals.', 'speedycache'),
		];
	}

	/**
	 * Execute callback for speedycache-object/get-settings.
	 *
	 * @return array
	 */
	public static function get_object_settings(){
		$object = get_option('speedycache_object_cache', []);

		return [
			'enabled' => !empty($object['enable']),
			'host' => isset($object['host']) ? $object['host'] : null,
			'port' => isset($object['port']) ? (int)$object['port'] : null,
			'ttl' => isset($object['ttl']) ? (int)$object['ttl'] : null,
			'persistent' => !empty($object['persistent']),
			'async_flush' => !empty($object['async_flush']),
			'serialization' => isset($object['serialization']) ? $object['serialization'] : null,
			'compress' => isset($object['compress']) ? $object['compress'] : null,
			'admin' => !empty($object['admin']),
		];
	}

	/**
	 * Execute callback for speedycache-object/flush.
	 *
	 * @return array|\WP_Error
	 */
	public static function flush_object_cache(){
		if(!class_exists('\SpeedyCache\ObjectCache')){
			return new \WP_Error('object_unavailable', __('Object cache is not available. SpeedyCache Pro is not active.', 'speedycache'));
		}

		global $speedycache;

		if(empty($speedycache->object['enable'])){
			return [
				'flushed' => false,
				'message' => __('Object cache is not enabled.', 'speedycache'),
			];
		}

		try{
			ObjectCache::boot();
		} catch(\Exception $e){
			return new \WP_Error('object_connect_failed', $e->getMessage());
		}

		$res = ObjectCache::flush_db();

		if($res){
			return [
				'flushed' => true,
				'message' => __('The object cache has been flushed.', 'speedycache'),
			];
		}

		return [
			'flushed' => false,
			'message' => __('The object cache could not be flushed.', 'speedycache'),
		];
	}

	/**
	 * Execute callback for speedycache-object/status.
	 *
	 * @return array
	 */
	public static function object_cache_status(){
		global $speedycache;

		$enabled = !empty($speedycache->object['enable']);
		$memory = 'None';
		$connected = false;
		$error = null;

		if($enabled && class_exists('\SpeedyCache\ObjectCache') && class_exists('Redis')){
			try{
				ObjectCache::boot();
				$memory = ObjectCache::get_memory();
				$connected = true;
			} catch(\Exception $e){
				$error = $e->getMessage();
			}
		}

		return [
			'enabled' => $enabled,
			'connected' => $connected,
			'memory' => is_string($memory) ? $memory : null,
			'error' => $error,
		];
	}

	/**
	 * Execute callback for speedycache-bloat/get.
	 *
	 * @return array
	 */
	public static function get_bloat_settings(){
		$bloat = get_option('speedycache_bloat', []);

		return [
			'disable_xmlrpc' => !empty($bloat['disable_xmlrpc']),
			'remove_gfonts' => !empty($bloat['remove_gfonts']),
			'disable_dashicons' => !empty($bloat['disable_dashicons']),
			'remove_jquery_migrate' => !empty($bloat['remove_jquery_migrate']),
			'disable_oembeds' => !empty($bloat['disable_oembeds']),
			'disable_block_editor_css' => !empty($bloat['disable_block_editor_css']),
			'limit_post_revisions' => isset($bloat['limit_post_revisions']) ? $bloat['limit_post_revisions'] : false,
			'disable_heartbeat' => isset($bloat['disable_heartbeat']) ? $bloat['disable_heartbeat'] : false,
			'disable_cart_fragment' => !empty($bloat['disable_cart_fragment']),
			'disable_woocommerce_assets' => !empty($bloat['disable_woocommerce_assets']),
			'disable_wp_feeds' => !empty($bloat['disable_wp_feeds']),
			'disable_gutenberg' => !empty($bloat['disable_gutenberg']),
		];
	}

	// =========================================================================
	// Execute callbacks — License
	// =========================================================================

	/**
	 * Execute callback for speedycache-license/get.
	 *
	 * @return array
	 */
	public static function get_license(){
		global $speedycache;

		$license = isset($speedycache->license['license']) ? $speedycache->license['license'] : null;
		$active = !empty($speedycache->license['active']);
		$expires = '';

		if(!empty($speedycache->license['expires'])){
			$raw = $speedycache->license['expires'];
			$expires = substr($raw, 0, 4) . '/' . substr($raw, 4, 2) . '/' . substr($raw, 6);
		}

		$truncated = null;
		if(!empty($license)){
			$truncated = strlen($license) > 8 ? substr($license, 0, 4) . '••••' . substr($license, -4) : $license;
		}

		return [
			'active' => $active,
			'license' => $truncated,
			'expires' => $expires ?: null,
			'version' => defined('SPEEDYCACHE_PRO_VERSION') ? SPEEDYCACHE_PRO_VERSION : null,
		];
	}

	// =========================================================================
	// Filter — merge Pro abilities into the UI catalogue
	// =========================================================================

	/**
	 * Merge the Pro ability catalogue into the Free catalogue shown on the
	 * AI Abilities settings page.
	 *
	 * @param array $free
	 * @return array
	 */
	static function abilities($free){
		$pro = [
			esc_html__('Database', 'speedycache') => [
				[
					'label' => esc_html__('Optimize the Database', 'speedycache'),
					'description' => esc_html__('Runs a database cleanup of the given type (revisions, trash, spam, transients, etc).', 'speedycache'),
					'pro' => true,
				],
				[
					'label' => esc_html__('Get Database Optimization Settings', 'speedycache'),
					'description' => esc_html__('Returns the automatic database optimization settings and cron schedule.', 'speedycache'),
					'pro' => true,
				],
			],
			esc_html__('Cache Logs', 'speedycache') => [
				[
					'label' => esc_html__('List Cache Delete Logs', 'speedycache'),
					'description' => esc_html__('Returns the cache deletion log entries with date and triggering action.', 'speedycache'),
					'pro' => true,
				],
			],
			esc_html__('Statistics', 'speedycache') => [
				[
					'label' => esc_html__('Get Cache Statistics', 'speedycache'),
					'description' => esc_html__('Returns detailed cache stats for desktop, mobile, CSS and JS (file count + size).', 'speedycache'),
					'pro' => true,
				],
			],
			esc_html__('Image Optimization', 'speedycache') => [
				[
					'label' => esc_html__('Get Image Optimization Stats', 'speedycache'),
					'description' => esc_html__('Returns total / optimized / error / uncompressed image counts and total size reduction.', 'speedycache'),
					'pro' => true,
				],
				[
					'label' => esc_html__('Get Image Optimization Settings', 'speedycache'),
					'description' => esc_html__('Returns compression quality, WebP conversion, auto-optimization and lazy load toggles.', 'speedycache'),
					'pro' => true,
				],
				[
					'label' => esc_html__('Revert All Image Optimizations', 'speedycache'),
					'description' => esc_html__('Restores all optimized images to their originals from the backup folder.', 'speedycache'),
					'pro' => true,
				],
			],
			esc_html__('Object Cache', 'speedycache') => [
				[
					'label' => esc_html__('Get Object Cache Settings', 'speedycache'),
					'description' => esc_html__('Returns the Redis object cache configuration (host, port, TTL, serialization, etc).', 'speedycache'),
					'pro' => true,
				],
				[
					'label' => esc_html__('Flush the Object Cache', 'speedycache'),
					'description' => esc_html__('Flushes the Redis object cache.', 'speedycache'),
					'pro' => true,
				],
				[
					'label' => esc_html__('Get Object Cache Status', 'speedycache'),
					'description' => esc_html__('Returns whether the object cache is enabled, Redis is reachable and the memory usage.', 'speedycache'),
					'pro' => true,
				],
			],
			esc_html__('Bloat', 'speedycache') => [
				[
					'label' => esc_html__('Get Bloat Settings', 'speedycache'),
					'description' => esc_html__('Returns all the bloat reduction toggles (XML-RPC, jQuery Migrate, oEmbeds, heartbeat, etc).', 'speedycache'),
					'pro' => true,
				],
			],
			esc_html__('License', 'speedycache') => [
				[
					'label' => esc_html__('Get License Status', 'speedycache'),
					'description' => esc_html__('Returns the SpeedyCache Pro license status, expiry date and version.', 'speedycache'),
					'pro' => true,
				],
			],
		];

		return array_merge($free, $pro);
	}
}
