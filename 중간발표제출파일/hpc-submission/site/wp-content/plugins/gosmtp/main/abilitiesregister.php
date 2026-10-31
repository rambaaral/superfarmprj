<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

namespace GOSMTP;

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

/**
 * Registers GoSMTP FREE abilities with the WordPress 6.9+ Abilities API.
 *
 * These abilities are always available (Free version) and provide read-only
 * access to the SMTP configuration plus the ability to send a test email.
 *
 * Pro-only abilities are registered by GOSMTP\AbilitiesPro when the
 * GoSMTP Pro plugin is installed and active.
 */
class AbilitiesRegister{

	/**
	 * Register the GoSMTP ability categories (Free subset).
	 *
	 * @return void
	 */
	static function register_categories(){
		$categories = [
			'gosmtp-settings' => __('GoSMTP — Settings', 'gosmtp'),
			'gosmtp-mailer'    => __('GoSMTP — Mailer', 'gosmtp'),
			'gosmtp-test'      => __('GoSMTP — Test', 'gosmtp'),
		];

		foreach($categories as $slug => $label){
			wp_register_ability_category($slug, [
				'label'       => $label,
				'description' => __('Email management abilities provided by GoSMTP.', 'gosmtp'),
			]);
		}
	}

	/**
	 * Register all Free GoSMTP abilities.
	 *
	 * @return void
	 */
	static function register_abilities(){
		self::register_settings_abilities();
		self::register_mailer_abilities();
		self::register_test_abilities();
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
	// Settings abilities (read-only)
	// =========================================================================

	protected static function register_settings_abilities(){
		// gosmtp-settings/get
		wp_register_ability('gosmtp-settings/get', [
			'label'               => __('Get GoSMTP Settings', 'gosmtp'),
			'description'         => __('Returns the GoSMTP global settings tree: from email/name, force flags, return path and logging toggles. Sensitive mailer credentials (API keys, passwords) are never exposed. Read-only.', 'gosmtp'),
			'category'            => 'gosmtp-settings',
			'input_schema'        => self::no_input_schema(),
			'output_schema'        => [
				'type'       => 'object',
				'properties' => [
					'from_email'       => ['type' => ['string', 'null']],
					'from_name'        => ['type' => ['string', 'null']],
					'force_from_email' => ['type' => 'boolean'],
					'force_from_name'  => ['type' => 'boolean'],
					'return_path'      => ['type' => 'boolean'],
					'logs_enabled'     => ['type' => 'boolean'],
					'ai_abilities'     => ['type' => 'boolean'],
					'smart_routing'    => ['type' => 'boolean'],
					'notifications'    => ['type' => 'boolean'],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesRegister::get_settings',
			'permission_callback' => '\GOSMTP\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Mailer abilities (read-only)
	// =========================================================================

	protected static function register_mailer_abilities(){
		// gosmtp-mailer/list
		wp_register_ability('gosmtp-mailer/list', [
			'label'               => __('List Configured Mailers', 'gosmtp'),
			'description'         => __('Lists the configured SMTP connections (mailers) on the site. Returns each connection id, nickname, mailer type, whether it is the primary connection and any backup connection id. Credentials are never exposed.', 'gosmtp'),
			'category'            => 'gosmtp-mailer',
			'input_schema'         => self::no_input_schema(),
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'mailers' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'conn_id'          => ['type' => 'integer'],
								'nickname'         => ['type' => ['string', 'null']],
								'mail_type'        => ['type' => 'string'],
								'is_primary'       => ['type' => 'boolean'],
								'backup_connection' => ['type' => ['integer', 'null']],
							],
						],
					],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesRegister::list_mailers',
			'permission_callback' => '\GOSMTP\AbilitiesRegister::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Test abilities
	// =========================================================================

	protected static function register_test_abilities(){
		// gosmtp-test/send-mail
		wp_register_ability('gosmtp-test/send-mail', [
			'label'               => __('Send a Test Email', 'gosmtp'),
			'description'         => __('Sends a test email through the currently configured GoSMTP mailer and reports whether the delivery succeeded. Useful for "test my SMTP connection" prompts.', 'gosmtp'),
			'category'            => 'gosmtp-test',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'to'      => [
						'type'        => 'string',
						'description' => __('The recipient email address.', 'gosmtp'),
					],
					'subject' => [
						'type'        => 'string',
						'description' => __('The email subject line.', 'gosmtp'),
						'default'     => 'GoSMTP Test',
					],
					'message' => [
						'type'        => 'string',
						'description' => __('The email body (plain text).', 'gosmtp'),
						'default'     => 'This is a test email sent via the GoSMTP Abilities API.',
					],
				],
				'required'             => ['to'],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'sent'      => ['type' => 'boolean'],
					'error'     => ['type' => ['string', 'null']],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesRegister::send_test_mail',
			'permission_callback' => '\GOSMTP\AbilitiesRegister::can_manage_options',
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => ['public' => true],
			],
		]);
	}

	// =========================================================================
	// Execute callbacks — Settings
	// =========================================================================

	/**
	 * Execute callback for gosmtp-settings/get.
	 *
	 * @return array
	 */
	public static function get_settings(){
		$options = get_option('gosmtp_options', []);

		$settings = [
			'from_email'       => isset($options['from_email']) ? $options['from_email'] : null,
			'from_name'        => isset($options['from_name']) ? $options['from_name'] : null,
			'force_from_email' => !empty($options['force_from_email']),
			'force_from_name'  => !empty($options['force_from_name']),
			'return_path'      => !empty($options['return_path']),
			'ai_abilities'     => !empty($options['ai_abilities']['enabled']),
		];

		if(defined('GOSMTP_PREMIUM')){
			$settings['logs_enabled'] = !empty($options['logs']['enable_logs']);
			$settings['smart_routing'] = !empty($options['smart_routing']['enabled']);
			$settings['notifications'] = !empty($options['notifications']['notifications_enabled']);
		}
		
		return $settings;
	}

	// =========================================================================
	// Execute callbacks — Mailer
	// =========================================================================

	/**
	 * Execute callback for gosmtp-mailer/list.
	 *
	 * @return array
	 */
	public static function list_mailers(){
		$options = get_option('gosmtp_options', []);
		$mailers = [];

		if(!empty($options['mailer']) && is_array($options['mailer'])){
			$backup_connection = isset($options['mailer'][0]['backup_connection']) ? $options['mailer'][0]['backup_connection'] : null;

			foreach($options['mailer'] as $conn_id => $mailer){
				$conn_id = (int)$conn_id;
				$mailers[] = [
					'conn_id'          => $conn_id,
					'nickname'         => isset($mailer['nickname']) ? $mailer['nickname'] : null,
					'mail_type'        => isset($mailer['mail_type']) ? $mailer['mail_type'] : '',
					'is_primary'       => $conn_id === 0,
					'backup_connection' => ($conn_id === 0 && $backup_connection !== null) ? (int)$backup_connection : null,
				];
			}
		}

		return ['mailers' => $mailers];
	}

	// =========================================================================
	// Execute callbacks — Test
	// =========================================================================

	/**
	 * Execute callback for gosmtp-test/send-mail.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function send_test_mail($input){
		$input   = is_array($input) ? $input : [];
		$to      = isset($input['to']) ? sanitize_email($input['to']) : '';
		$subject = isset($input['subject']) ? sanitize_text_field($input['subject']) : 'GoSMTP Test';
		$message = isset($input['message']) ? sanitize_textarea_field($input['message']) : 'This is a test email sent via the GoSMTP Abilities API.';

		if(empty($to) || !is_email($to)){
			return new \WP_Error('invalid_recipient', __('A valid recipient email address is required.', 'gosmtp'));
		}

		$options = get_option('gosmtp_options', []);

		if(empty($options['mailer'])){
			return [
				'sent'  => false,
				'error' => __('GoSMTP is not configured yet.', 'gosmtp'),
			];
		}

		$sent = wp_mail($to, $subject, $message);

		if($sent){
			return [
				'sent'  => true,
				'error' => null,
			];
		}

		global $phpmailer;
		$error = '';
		if($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer){
			$error = $phpmailer->ErrorInfo;
		}
		if(empty($error)){
			$error = __('Unable to send the test email. Check your mailer configuration.', 'gosmtp');
		}

		return [
			'sent'  => false,
			'error' => $error,
		];
	}
}