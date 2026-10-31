<?php
/*
* GoSMTP Pro
* https://gosmtp.net
* (c) Softaculous Team
*/

namespace GOSMTP;

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

/**
 * Registers GoSMTP PRO abilities with the WordPress 6.9+ Abilities API.
 *
 * These abilities are ONLY registered when the GoSMTP Pro plugin is installed
 * and active (GOSMTP_PREMIUM is defined). They cover Email Logs, Email Reports
 * and the CSV Exporter — features exclusive to the Pro version.
 *
 * The Free abilities (settings, mailer list, test email) are registered by
 * GOSMTP\AbilitiesRegister and remain available regardless of Pro.
 */
class AbilitiesPro{

	/**
	 * Register the GoSMTP Pro ability categories.
	 *
	 * @return void
	 */
	static function register_categories(){
		$categories = [
			'gosmtp-logs'    => __('GoSMTP — Email Logs', 'gosmtp'),
			'gosmtp-reports' => __('GoSMTP — Email Reports', 'gosmtp'),
			'gosmtp-export'  => __('GoSMTP — Export', 'gosmtp'),
		];

		foreach($categories as $slug => $label){
			wp_register_ability_category($slug, [
				'label'       => $label,
				'description' => __('Pro email management abilities provided by GoSMTP Pro.', 'gosmtp'),
			]);
		}
	}

	/**
	 * Register all Pro GoSMTP abilities.
	 *
	 * @return void
	 */
	static function register_abilities(){
		self::register_logs_abilities();
		self::register_reports_abilities();
		self::register_export_abilities();
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
	// Email Logs abilities
	// =========================================================================

	protected static function register_logs_abilities(){
		// gosmtp-logs/list
		wp_register_ability('gosmtp-logs/list', [
			'label'               => __('List Email Logs', 'gosmtp'),
			'description'         => __('Returns a paginated list of recent emails logged by GoSMTP Pro: id, recipient, sender, subject, status (sent/failed), provider and created timestamp. Useful for "show recent failed emails" prompts.', 'gosmtp'),
			'category'            => 'gosmtp-logs',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'limit'  => [
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					],
					'offset' => [
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					],
					'filter' => [
						'type' => 'string',
						'enum' => ['sent', 'failed'],
					],
					'search' => ['type' => 'string'],
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'logs' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'         => ['type' => 'integer'],
								'to'         => ['type' => ['string', 'null']],
								'from'       => ['type' => ['string', 'null']],
								'subject'    => ['type' => ['string', 'null']],
								'status'     => ['type' => 'string'],
								'provider'   => ['type' => ['string', 'null']],
								'created_at' => ['type' => ['string', 'null']],
							],
						],
					],
					'total' => ['type' => 'integer'],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesPro::list_logs',
			'permission_callback' => '\GOSMTP\AbilitiesPro::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);

		// gosmtp-logs/get
		wp_register_ability('gosmtp-logs/get', [
			'label'               => __('Get Email Log Details', 'gosmtp'),
			'description'         => __('Returns the full detail of a single email log entry: headers, body, attachments list, provider response and status. Useful for "show me why this email failed" prompts.', 'gosmtp'),
			'category'            => 'gosmtp-logs',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id' => [
						'type'        => 'integer',
						'description' => __('The email log ID.', 'gosmtp'),
					],
				],
				'required'             => ['id'],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'         => ['type' => 'integer'],
					'to'         => ['type' => ['string', 'null']],
					'from'       => ['type' => ['string', 'null']],
					'subject'    => ['type' => ['string', 'null']],
					'body'       => ['type' => ['string', 'null']],
					'status'     => ['type' => 'string'],
					'provider'   => ['type' => ['string', 'null']],
					'response'   => ['type' => ['string', 'null']],
					'created_at' => ['type' => ['string', 'null']],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesPro::get_log',
			'permission_callback' => '\GOSMTP\AbilitiesPro::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);

		// gosmtp-logs/resend
		wp_register_ability('gosmtp-logs/resend', [
			'label'               => __('Resend an Email Log', 'gosmtp'),
			'description'         => __('Re-sends a previously logged email by ID, optionally to a new recipient. Uses the original subject, body and attachments.', 'gosmtp'),
			'category'            => 'gosmtp-logs',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id'        => [
						'type'        => 'integer',
						'description' => __('The email log ID to resend.', 'gosmtp'),
					],
					'recipient' => [
						'type'        => 'string',
						'description' => __('Optional new recipient email address. If omitted, the original recipient is used.', 'gosmtp'),
					],
				],
				'required'             => ['id'],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'sent'  => ['type' => 'boolean'],
					'error' => ['type' => ['string', 'null']],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesPro::resend_log',
			'permission_callback' => '\GOSMTP\AbilitiesPro::can_manage_options',
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => ['public' => true],
			],
		]);
	}

	// =========================================================================
	// Email Reports abilities
	// =========================================================================

	protected static function register_reports_abilities(){
		// gosmtp-reports/get
		wp_register_ability('gosmtp-reports/get', [
			'label'               => __('Get Email Sending Report', 'gosmtp'),
			'description'         => __('Returns an aggregated email sending report for a date range: total sent, total failed, success rate and a daily breakdown. Useful for "how are my emails doing this week?" prompts.', 'gosmtp'),
			'category'            => 'gosmtp-reports',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'start' => [
						'type'        => 'string',
						'description' => __('Start date (YYYY-MM-DD). Defaults to 7 days ago.', 'gosmtp'),
					],
					'end'   => [
						'type'        => 'string',
						'description' => __('End date (YYYY-MM-DD). Defaults to today.', 'gosmtp'),
					],
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_sent'    => ['type' => 'integer'],
					'total_failed'  => ['type' => 'integer'],
					'success_rate'  => ['type' => 'number'],
					'range'         => [
						'type'       => 'object',
						'properties' => [
							'start' => ['type' => 'string'],
							'end'   => ['type' => 'string'],
						],
					],
					'daily' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'date'    => ['type' => 'string'],
								'sent'    => ['type' => 'integer'],
								'failed'  => ['type' => 'integer'],
							],
						],
					],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesPro::get_report',
			'permission_callback' => '\GOSMTP\AbilitiesPro::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Export abilities
	// =========================================================================

	protected static function register_export_abilities(){
		// gosmtp-export/csv
		wp_register_ability('gosmtp-export/csv', [
			'label'               => __('Export Email Logs (CSV)', 'gosmtp'),
			'description'         => __('Exports the email logs matching the given filters as a CSV string (one row per email). Returns the CSV content directly so an AI client can save or display it. Useful for "export my failed emails as CSV" prompts.', 'gosmtp'),
			'category'            => 'gosmtp-export',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'start' => [
						'type'        => 'string',
						'description' => __('Start date (YYYY-MM-DD). Defaults to 30 days ago.', 'gosmtp'),
					],
					'end'   => [
						'type'        => 'string',
						'description' => __('End date (YYYY-MM-DD). Defaults to today.', 'gosmtp'),
					],
					'filter' => [
						'type' => 'string',
						'enum' => ['sent', 'failed'],
					],
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'format' => ['type' => 'string'],
					'rows'   => ['type' => 'integer'],
					'csv'    => ['type' => 'string'],
				],
			],
			'execute_callback'    => '\GOSMTP\AbilitiesPro::export_csv',
			'permission_callback' => '\GOSMTP\AbilitiesPro::can_manage_options',
			'meta'                => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Execute callbacks — Email Logs
	// =========================================================================

	/**
	 * Get a Logger instance. The Logger class only exists in Pro.
	 *
	 * @return \GOSMTP\Logger|null
	 */
	protected static function logger(){
		if(!class_exists('GOSMTP\Logger')){
			return null;
		}
		return new Logger();
	}

	/**
	 * Execute callback for gosmtp-logs/list.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function list_logs($input){
		$input = is_array($input) ? $input : [];

		$logger = self::logger();
		if(!$logger){
			return new \WP_Error('logs_unavailable', __('Email logs are not available. GoSMTP Pro is not active or logging is disabled.', 'gosmtp'));
		}

		$limit  = isset($input['limit']) ? max(1, min(100, (int)$input['limit'])) : 20;
		$offset = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;

		$args = [
			'limit'      => $limit,
			'offset'      => $offset,
			'pagination'  => false,
		];

		if(!empty($input['filter'])){
			$args['filter'] = sanitize_text_field($input['filter']);
		}
		if(!empty($input['search'])){
			$args['search'] = sanitize_text_field($input['search']);
		}

		$records = $logger->get_logs('records', '', $args);
		$count   = $logger->get_logs('count', '', $args);

		$logs = [];
		if(!empty($records) && is_array($records)){
			foreach($records as $row){
				$logs[] = [
					'id'         => (int)$row->id,
					'to'         => self::flatten_recipients($row->to),
					'from'       => isset($row->from) ? $row->from : null,
					'subject'    => isset($row->subject) ? $row->subject : null,
					'status'     => isset($row->status) ? $row->status : '',
					'provider'   => isset($row->provider) ? $row->provider : null,
					'created_at' => isset($row->created_at) ? $row->created_at : null,
				];
			}
		}

		return [
			'logs'  => $logs,
			'total' => $count ? (int)$count->records : count($logs),
		];
	}

	/**
	 * Execute callback for gosmtp-logs/get.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function get_log($input){
		$input = is_array($input) ? $input : [];
		$id = isset($input['id']) ? (int)$input['id'] : 0;

		if(!$id){
			return new \WP_Error('invalid_id', __('A valid log ID is required.', 'gosmtp'));
		}

		$logger = self::logger();
		if(!$logger){
			return new \WP_Error('logs_unavailable', __('Email logs are not available.', 'gosmtp'));
		}

		$records = $logger->get_logs('records', $id);
		if(empty($records) || empty($records[0])){
			return new \WP_Error('not_found', __('Email log not found.', 'gosmtp'));
		}

		$row = $records[0];

		$response = maybe_unserialize($row->response);
		if(is_array($response)){
			$response = wp_json_encode($response);
		}

		return [
			'id'         => (int)$row->id,
			'to'         => self::flatten_recipients($row->to),
			'from'       => isset($row->from) ? $row->from : null,
			'subject'    => isset($row->subject) ? $row->subject : null,
			'body'       => isset($row->body) ? $row->body : null,
			'status'     => isset($row->status) ? $row->status : '',
			'provider'   => isset($row->provider) ? $row->provider : null,
			'response'   => is_string($response) ? $response : null,
			'created_at' => isset($row->created_at) ? $row->created_at : null,
		];
	}

	/**
	 * Execute callback for gosmtp-logs/resend.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function resend_log($input){
		$input = is_array($input) ? $input : [];
		$id = isset($input['id']) ? (int)$input['id'] : 0;

		if(!$id){
			return new \WP_Error('invalid_id', __('A valid log ID is required.', 'gosmtp'));
		}

		$logger = self::logger();
		if(!$logger){
			return new \WP_Error('logs_unavailable', __('Email logs are not available.', 'gosmtp'));
		}

		$records = $logger->get_logs('records', $id);
		if(empty($records) || empty($records[0])){
			return new \WP_Error('not_found', __('Email log not found.', 'gosmtp'));
		}

		$row = $records[0];

		$tos = maybe_unserialize($row->to);
		$subject = isset($row->subject) ? $row->subject : '';
		$body = isset($row->body) ? $row->body : '';
		$headers = maybe_unserialize($row->headers);
		$attachments = maybe_unserialize($row->attachments);

		$to_list = [];
		if(is_array($tos)){
			foreach($tos as $to){
				if(is_array($to)){
					$to_list[] = isset($to[0]) ? $to[0] : '';
				}else{
					$to_list[] = (string)$to;
				}
			}
		}else{
			$to_list[] = (string)$tos;
		}

		$recipient = isset($input['recipient']) ? sanitize_email($input['recipient']) : '';
		if(!empty($recipient) && is_email($recipient)){
			$to_list = [$recipient];
		}

		$att_files = [];
		if(is_array($attachments)){
			foreach($attachments as $att){
				if(is_array($att) && isset($att[0])){
					$att_files[] = $att[0];
				}
			}
		}

		$header_lines = [];
		if(is_array($headers)){
			foreach ( $headers as $key => $val ){ 

				if(empty($val)){
					continue;
				}

				if(is_array($val)){
					$val = implode( ', ', array_filter(array_map('trim', $val)));

					if(empty($val)){
						continue;
					}
				}

				if(is_string($key) && !is_numeric($key)){
					$header_lines[] = $key . ': ' . $val;
				}else{
					$header_lines[] = (string) $val;
				}
			}

		}elseif(is_string($headers)){
			$header_lines = $headers;
		}

		$sent = wp_mail($to_list, $subject, $body, $header_lines, $att_files);

		return [
			'sent'  => (bool)$sent,
			'error' => $sent ? null : __('Unable to resend the email.', 'gosmtp'),
		];
	}

	// =========================================================================
	// Execute callbacks — Email Reports
	// =========================================================================

	/**
	 * Execute callback for gosmtp-reports/get.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function get_report($input){
		$input = is_array($input) ? $input : [];

		$logger = self::logger();
		if(!$logger){
			return new \WP_Error('logs_unavailable', __('Email logs are not available. GoSMTP Pro is not active or logging is disabled.', 'gosmtp'));
		}

		$end   = !empty($input['end']) ? sanitize_text_field($input['end']) : date('Y-m-d');
		$start = !empty($input['start']) ? sanitize_text_field($input['start']) : date('Y-m-d', strtotime('-7 days'));

		$args = [
			'interval'    => ['start' => $start, 'end' => $end],
			'pagination'   => false,
			'limit'        => 1000,
		];

		$records = $logger->get_logs('records', '', $args);

		$total_sent = 0;
		$total_failed = 0;
		$daily = [];

		if(!empty($records) && is_array($records)){
			foreach($records as $row){
				$status = isset($row->status) ? $row->status : '';
				$day = isset($row->created_at) ? substr($row->created_at, 0, 10) : date('Y-m-d');

				if(!isset($daily[$day])){
					$daily[$day] = ['date' => $day, 'sent' => 0, 'failed' => 0];
				}

				if($status === 'sent'){
					$total_sent++;
					$daily[$day]['sent']++;
				}else{
					$total_failed++;
					$daily[$day]['failed']++;
				}
			}
		}

		ksort($daily);

		$grand_total = $total_sent + $total_failed;
		$success_rate = $grand_total > 0 ? round(($total_sent / $grand_total) * 100, 2) : 0;

		return [
			'total_sent'   => $total_sent,
			'total_failed' => $total_failed,
			'success_rate'  => $success_rate,
			'range'         => ['start' => $start, 'end' => $end],
			'daily'         => array_values($daily),
		];
	}

	// =========================================================================
	// Execute callbacks — Export
	// =========================================================================

	/**
	 * Execute callback for gosmtp-export/csv.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function export_csv($input){
		$input = is_array($input) ? $input : [];

		$logger = self::logger();
		if(!$logger){
			return new \WP_Error('logs_unavailable', __('Email logs are not available.', 'gosmtp'));
		}

		$end   = !empty($input['end']) ? sanitize_text_field($input['end']) : date('Y-m-d');
		$start = !empty($input['start']) ? sanitize_text_field($input['start']) : date('Y-m-d', strtotime('-30 days'));

		$args = [
			'interval'  => ['start' => $start, 'end' => $end],
			'pagination' => false,
			'limit'      => 5000,
		];

		if(!empty($input['filter'])){
			$args['filter'] = sanitize_text_field($input['filter']);
		}

		$records = $logger->get_logs('records', '', $args);

		$rows = [];
		$rows[] = ['ID', 'To', 'From', 'Subject', 'Status', 'Provider', 'Created At'];

		$count = 0;
		if(!empty($records) && is_array($records)){
			foreach($records as $row){
				$rows[] = [
					(int)$row->id,
					self::flatten_recipients($row->to),
					isset($row->from) ? $row->from : '',
					isset($row->subject) ? $row->subject : '',
					isset($row->status) ? $row->status : '',
					isset($row->provider) ? $row->provider : '',
					isset($row->created_at) ? $row->created_at : '',
				];
				$count++;
			}
		}

		$csv = self::array_to_csv($rows);

		return [
			'format' => 'csv',
			'rows'   => $count,
			'csv'    => $csv,
		];
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Flatten the serialized `to` field of a log row to a comma-separated string.
	 *
	 * @param mixed $to
	 * @return string|null
	 */
	protected static function flatten_recipients($to){
		$list = maybe_unserialize($to);
		$emails = [];

		if(is_array($list)){
			foreach($list as $entry){
				if(is_array($entry) && isset($entry[0])){
					$emails[] = $entry[0];
				}elseif(is_string($entry)){
					$emails[] = $entry;
				}
			}
		}elseif(is_string($list)){
			$emails[] = $list;
		}

		return empty($emails) ? null : implode(', ', $emails);
	}

	/**
	 * Convert an array of rows to a CSV string.
	 *
	 * @param array $rows
	 * @return string
	 */
	protected static function array_to_csv($rows){
		$out = '';
		foreach($rows as $row){
			$cells = [];
			foreach($row as $cell){
				$cell = (string)$cell;
				if(strpos($cell, ',') !== false || strpos($cell, '"') !== false || strpos($cell, "\n") !== false){
					$cell = '"' . str_replace('"', '""', $cell) . '"';
				}
				$cells[] = $cell;
			}
			$out .= implode(',', $cells) . "\n";
		}
		return $out;
	}

	static function abilities($free){
		$pro = [
			esc_html__('Email Reports', 'gosmtp') => [
				[
					'label'       => esc_html__('Get Email Sending Report', 'gosmtp'),
					'description' => esc_html__('Returns an aggregated report: total sent/failed, success rate and a daily breakdown.', 'gosmtp'),
					'pro'         => true,
				],
			],
			esc_html__('Export', 'gosmtp') => [
				[
					'label'       => esc_html__('Export Email Logs (CSV)', 'gosmtp'),
					'description' => esc_html__('Exports the email logs matching the filters as a CSV string.', 'gosmtp'),
					'pro'         => true,
				],
			],esc_html__('Email Logs', 'gosmtp') => [
				[
					'label'       => esc_html__('List Email Logs', 'gosmtp'),
					'description' => esc_html__('Returns a paginated list of logged emails with status, provider and timestamp.', 'gosmtp'),
					'pro'         => true,
				],
				[
					'label'       => esc_html__('Get Email Log Details', 'gosmtp'),
					'description' => esc_html__('Returns the full detail of a single email log entry including body and response.', 'gosmtp'),
					'pro'         => true,
				],
				[
					'label'       => esc_html__('Resend an Email Log', 'gosmtp'),
					'description' => esc_html__('Re-sends a previously logged email, optionally to a new recipient.', 'gosmtp'),
					'pro'         => true,
				],
			],
		];

		return array_merge($free, $pro);
	}
}