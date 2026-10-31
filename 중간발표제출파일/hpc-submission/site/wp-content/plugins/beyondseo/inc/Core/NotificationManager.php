<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class NotificationManager
 */
class NotificationManager {

	/**
	 * Singleton instance of NotificationManager.
	 *
	 * @var NotificationManager|null
	 */
	private static ?NotificationManager $instance = null;

	/**
	 * Returns the singleton instance of NotificationManager.
	 * @return NotificationManager|null
	 */
	public static function instance(): ?NotificationManager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Option name to store notifications in.
	 *
	 * @var string
	 */
	private string $storage_key;

	/**
	 * Notifications.
	 *
	 * @var array
	 */
	private array $notifications = [];

	/**
	 * Stores whether we need to clear storage or not.
	 *
	 * @var bool
	 */
	private bool $should_clear_storage = true;

	/**
	 * Stores already displayed notice texts to avoid duplication.
	 *
	 * @var array
	 */
	private array $displayed_notifications = [];

	/**
	 * Internal flag for whether notifications have been retrieved from storage.
	 *
	 * @var bool
	 */
	private bool $retrieved = false;

	/**
	 * Construct
	 *
	 * @param string $storage_key Option name to store notification in.
	 */
	public function __construct( string $storage_key = BaseConstants::OPTION_NOTIFICATIONS ) {
		$this->storage_key = $storage_key;
		add_action( 'plugins_loaded', [ $this, 'get_from_storage' ], 5 );
		add_action( 'admin_notices', [ $this, 'display' ] );
		add_action( 'shutdown', [ $this, 'update_storage' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dismiss_script' ] );
		add_action( 'wp_ajax_rankingcoach_helpers_notice_dismissible', [ $this, 'notice_dismissible' ] );
	}

	/**
	 * Retrieve the notifications from storage
	 *
	 * @return void Notification[] Notifications
	 */
	public function get_from_storage(): void {
		if ( $this->retrieved ) {
			return;
		}

		$this->retrieved = true;
		$notifications   = get_option( $this->storage_key );

		// Check if notifications are stored.
		if ( empty( $notifications ) ) {
			$this->should_clear_storage = false;
			return;
		}

		if ( is_array( $notifications ) ) {
			foreach ( $notifications as $notification ) {
				$this->notifications[] = new Notification(
					$notification['message'],
					$notification['options']
				);
			}
		}
	}

	/**
	 * Display the notifications.
	 */
	public function display(): void {

		// Never display notifications for network admin.
		if ( $this->is_network_admin() ) {
			return;
		}

        global $pagenow;
        $allowed_pages = [
            'index.php',               // Dashboard
            'plugins.php',             // Plugins
            'admin.php',               // For custom admin pages
            'edit.php',                // Posts / Pages list
            'post.php',                // Edit post
            'post-new.php',            // New post
        ];

        $allowed_subpages = [
            //'rankingcoach-settings',
            //'rankingcoach-activation'
        ];

        if ( ! in_array( $pagenow, $allowed_pages, true ) ) {
            return;
        }

        if ( $pagenow === 'admin.php' ) {
            $page = WordpressHelpers::sanitize_input( 'GET', 'page' );

            if ( $page && ! in_array( $page, $allowed_subpages, true ) ) {
                return;
            }
        }

		$notifications = $this->get_sorted_notifications();
		if ( empty( $notifications ) ) {
			return;
		}

		foreach ( $notifications as $notification ) {
			if ( $notification->can_display() && ! in_array( (string) $notification, $this->displayed_notifications, true ) ) {
				$allowed_html = wp_kses_allowed_html( 'post' );
				if ( ! isset( $allowed_html['div'] ) ) {
					$allowed_html['div'] = [];
				}
				$allowed_html['div']['class']         = true;
				$allowed_html['div']['id']            = true;
				$allowed_html['div']['data-security'] = true;
                // add style attribute additionally for backward compatibility
                $allowed_html['style']                = true;

				echo wp_kses( (string) $notification, $allowed_html );
				$this->displayed_notifications[] = (string) $notification;
			}
		}
	}

	/**
	 * Enqueue JS for dismissive.
	 *
	 */
	public function enqueue_dismiss_script() {
        // Only enqueue on pages where notifications can display
        global $pagenow;
        $allowed_pages = [
            'index.php',
            'plugins.php',
            'admin.php',
            'edit.php',
            'post.php',
            'post-new.php',
        ];

        if ( ! in_array( $pagenow, $allowed_pages, true ) ) {
            return;
        }

        $notifications = $this->get_notifications();
        if ( empty( $notifications ) ) {
            return;
        }

        $script = "
            ;(function($) {
                $( '.wp-helpers-notice.is-dismissible' ).on( 'click', '.notice-dismiss', function() {
                    let notice = $( this ).parent();
                    let notificationId = notice.attr( 'id' );

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'rankingcoach_helpers_notice_dismissible',
                            security: notice.data('security') || '',
                            notificationId: notificationId
                        },
                        success: function(response) {
                            if (response.success) {
                                notice.fadeOut();
                            }
                        },
                        error: function() {
                            console.log('Error dismissing notification');
                        }
                    });
                });
            })(jQuery);";

        wp_register_script( 'rankingcoach-dismiss-notice', '', ['jquery'], RANKINGCOACH_VERSION, true );
        wp_enqueue_script( 'rankingcoach-dismiss-notice' );
        wp_add_inline_script( 'rankingcoach-dismiss-notice', $script );
	}

	/**
	 * Save persistent or transactional notifications to storage.
	 *
	 * We need to be able to retrieve these so they can be dismissed at any time during the execution.
	 */
	public function update_storage(): void {
		$notifications = $this->get_notifications();
		$notifications = array_filter( $notifications, [ $this, 'remove_notification' ] );

		/**
		 * Filter: 'rankingcoach_helpers_notifications_before_storage' - Allows a developer to filter notifications before saving them.
		 *
		 * @param Notification[] $notifications
		 */
		$notifications = apply_filters( 'rankingcoach_helpers_notifications_before_storage', $notifications );

		// No notifications to store, clear storage.
		if ( empty( $notifications ) && $this->should_clear_storage ) {
			delete_option( $this->storage_key );
			return;
		}

		$notifications = array_map( [ $this, 'notification_to_array' ], $notifications );

		// Save the notifications to the storage.
        if ( !empty( $notifications ) ) {
            update_option($this->storage_key, $notifications);
        }
	}

	/**
	 * Dismiss persistent notice.
	 */
	public function notice_dismissible(): void {
		// Check user permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'beyondseo')));
			return;
		}
		
		$notification_id = sanitize_text_field(WordpressHelpers::sanitize_input( 'POST', 'notificationId' ));
		
		// Verify nonce with proper action name
        $nonce = WordpressHelpers::sanitize_input('POST', 'security');

        if ( ! wp_verify_nonce( $nonce, 'rankingcoach_helpers_notice_dismissible' . $notification_id ) && ! wp_verify_nonce( $nonce, $notification_id ) ) {
            wp_send_json_error([
                    'message' => __( 'Security check failed. Please try again.', 'beyondseo' ),
            ]);
            return;
        }

		$notification = $this->remove_by_id( $notification_id );

		/**
		 * Filter: 'rankingcoach_helpers_notification_dismissed' - Allows a developer to perform action after dismissed.
		 *
		 * @param Notification[] $notifications
		 */
		do_action( 'rankingcoach_helpers_notification_dismissed', $notification_id, $notification );
		
		wp_send_json_success(array('message' => __('Notification dismissed successfully.', 'beyondseo')));
	}

	/**
	 * Add notification
	 *
	 * @param string $message Message string.
	 * @param array $options Set of options.
	 */
	public function add( string $message, array $options = [] ): void {
		if ( isset( $options['id'] ) && ! is_null( $this->get_notification_by_id( $options['id'] ) ) ) {
			return;
		}

		$this->notifications[] = new Notification(
			$message,
			$options
		);
	}

	/**
	 * Provide a way to verify present notifications
	 *
	 * @return array|Notification[] Registered notifications.
	 */
	public function get_notifications(): array {
		return $this->notifications;
	}

	/**
	 * Get the notification by ID
	 *
	 * @param string $notification_id The ID of the notification to search for.
	 *
	 * @return null|Notification
	 */
	public function get_notification_by_id( string $notification_id ): ?Notification {
		foreach ( $this->notifications as $notification ) {
			if ( $notification_id === $notification->args( 'id' ) ) {
				return $notification;
			}
		}
		return null;
	}

	/**
	 * Remove the notification by ID
	 *
	 * @param string $notification_id The ID of the notification to search for.
	 *
	 * @return Notification|null Instance of delete notification.
	 */
	public function remove_by_id( string $notification_id ): ?Notification {
		$notification = $this->get_notification_by_id( $notification_id );
		if ( ! is_null( $notification ) ) {
			$notification->dismiss();
		}

		return $notification;
	}

	/**
	 * Remove a notification after it has been displayed.
	 *
	 * @param Notification $notification Notification to remove.
	 */
	public function remove_notification( Notification $notification ): bool {
		if ( ! $notification->is_displayed() ) {
			return true;
		}

		if ( $notification->is_persistent() ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the notifications sorted on type and priority
	 *
	 * @return array|Notification[] Sorted Notifications
	 */
	private function get_sorted_notifications(): array {
		$notifications = $this->get_notifications();
		if ( empty( $notifications ) ) {
			return [];
		}

		// Sort by severity, error first.
		usort( $notifications, [ $this, 'sort_notifications' ] );

		return $notifications;
	}

	/**
	 * Sort on type then priority
	 *
	 * @param  Notification $first  Compare with B.
	 * @param  Notification $second Compare with A.
	 * @return int 1, 0 or -1 for sorting offset.
	 */
	private function sort_notifications( Notification $first, Notification $second ): int {

		if ( 'error' === $first->args( 'type' ) ) {
			return -1;
		}

		if ( 'error' === $second->args( 'type' ) ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Convert Notification to array representation
	 *
	 * @param  Notification $notification Notification to convert.
	 * @return array
	 */
	private function notification_to_array( Notification $notification ): array {
		return $notification->to_array();
	}

	/**
	 * Check if is network admin.
	 *
	 * @return bool
	 */
	private function is_network_admin(): bool {
		return function_exists( 'is_network_admin' ) && is_network_admin();
	}

	/**
	 * Check if a notification with the given ID exists.
	 *
	 * @param string $id Notification ID.
	 *
	 * @return bool
	 */
	public function has_notification( string $id ): bool {
		$notifications = $this->get_notifications();
		foreach ( $notifications as $notification ) {
			if ( isset( $notification->options['id'] ) && $notification->options['id'] === $id ) {
				return true;
			}
		}
		return false;
	}

    /**
     * Remove all notifications.
     *
     * @return void
     */
    public function removeAllNotifications(): void {
        $this->notifications = [];
        $this->update_storage();
    }
}
