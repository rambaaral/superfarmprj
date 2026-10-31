<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\CoreHelper;

/**
 * Class Notification
 */
class Notification {

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public const ERROR = 'error';

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public const SUCCESS = 'success';

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public const INFO = 'info';

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public const WARNING = 'warning';

	/**
	 * Screen check.
	 *
	 * @var string
	 */
	public const SCREEN_ANY = 'any';

    /**
     * Screen check.
     *
     * @var string
     */
    public const SCREEN_DASHBOARD = 'dashboard';

	/**
	 * User capability check.
	 *
	 * @var string
	 */
	public const CAPABILITY_ANY = '';

	/**
	 * The notification message.
	 *
	 * @var string
	 */
	public string $message = '';

	/**
	 * Contains optional arguments:

	 * @var array Options of this Notification.
	 */
	public array $options = [];

	/**
	 * Internal flag for whether notifications have been displayed.
	 *
	 * @var bool
	 */
	private bool $displayed = false;

	/**
	 * Notification class constructor.
	 *
	 * @param string $message Message string.
	 * @param array $options Set of options.
	 */
	public function __construct( string $message, array $options = [] ) {
		$this->message = $message;
		$this->options = wp_parse_args(
			$options,
			[
				'id'         => '',
				'classes'    => '',
				'type'       => self::SUCCESS,
				'screen'     => self::SCREEN_ANY,
				'capability' => self::CAPABILITY_ANY,
			]
		);
	}

	/**
	 * Adds string (view) behavior to the Notification.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->render();
	}

	/**
	 * Return data from options.
	 *
	 * @param string $id Data to get.
	 *
	 * @return mixed
	 */
	public function args( string $id ): mixed {
		return $this->options[ $id ];
	}

	/**
	 * Is this Notification persistent?
	 *
	 * @codeCoverageIgnore
	 *
	 * @return bool True if persistent, False if fire and forget.
	 */
	public function is_persistent(): bool {
		return ! empty( $this->args( 'id' ) );
	}

	/**
	 * Is this notification displayed?
	 *
	 * @codeCoverageIgnore
	 *
	 * @return bool
	 */
	public function is_displayed(): bool {
		return $this->displayed;
	}

	/**
	 * Can display on the current screen.
	 *
	 * @codeCoverageIgnore
	 *
	 * @return bool
	 */
	public function can_display(): bool {
		// Removed.
		if ( $this->displayed ) {
			return false;
		}

		$screen = get_current_screen();
		if ( self::SCREEN_ANY === $this->args( 'screen' ) || CoreHelper::string_contains( $this->args( 'screen' ), $screen->id ) ) {
			$this->displayed = true;
		}

		if ( self::CAPABILITY_ANY !== $this->args( 'capability' ) && ! current_user_can( $this->args( 'capability' ) ) ) {
			$this->displayed = false;
		}

		return $this->displayed;
	}

	/**
	 * Dismiss persistent notification.
	 *
	 * @codeCoverageIgnore
	 */
	public function dismiss(): void {
		$this->displayed     = true;
		$this->options['id'] = '';
	}

	/**
	 * Return the object properties as an array.
	 *
	 * @codeCoverageIgnore
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'message' => $this->message,
			'options' => $this->options,
		];
	}

	/**
	 * Renders the notification as a string.
	 *
	 * @codeCoverageIgnore
	 *
	 * @return string The rendered notification.
	 */
	public function render(): string {
		$attributes = [];

		// Default notification classes.
		$classes = [
			'notice',
			'notice-' . $this->args( 'type' ),
			$this->args( 'classes' ),
		];

		if ( $this->is_persistent() ) {
			$classes[]                   = 'is-dismissible';
			$classes[]                   = 'wp-helpers-notice';
			$attributes['id']            = $this->args( 'id' );
			$attributes['data-security'] = wp_create_nonce( $this->args( 'id' ) );
		}

		if ( ! empty( $classes ) ) {
			$attributes['class'] = implode( ' ', array_filter( $classes ) );
		}

		// Build the output DIV.
		$output = '<div' . CoreHelper::html_attributes_to_string( $attributes ) . '>' . wpautop( $this->message ) . '</div>' . PHP_EOL;

		/**
		 * Filter: 'rankingcoach_helpers_notifications_render' - Allows a developer to filter notifications before the output is finalized.
		 *
		 * @param string $output  HTML output.
		 * @param array  $message Notice message.
		 * @param array  $options Notice args.
		 */
		return apply_filters( 'rankingcoach_helpers_notifications_render', $output, $this->message, $this->options );
	}
}